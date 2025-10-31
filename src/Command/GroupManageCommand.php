<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Handler\ActionHandlerRegistry;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;

/**
 * 群组管理的控制台命令.
 */
#[AsCommand(name: self::NAME, description: '管理飞书群组（查看、创建、更新、添加成员等）', aliases: ['lark:group'], help: <<<'TXT'
    <info>列出所有群组：</info>
      <comment>%command.full_name% list</comment>
      <comment>%command.full_name% list --page=2 --size=50</comment>

    <info>查看群组信息：</info>
      <comment>%command.full_name% info oc_abc123</comment>

    <info>创建群组：</info>
      <comment>%command.full_name% create --name="技术讨论组" --description="技术团队内部讨论"</comment>
      <comment>%command.full_name% create --name="项目组" --member=open_123 --member=open_456 --public</comment>

    <info>更新群组信息：</info>
      <comment>%command.full_name% update oc_abc123 --name="新名称" --description="新描述"</comment>
      <comment>%command.full_name% update oc_abc123 --owner=open_789</comment>

    <info>添加群成员：</info>
      <comment>%command.full_name% add-member oc_abc123 --member=open_123 --member=open_456</comment>
      <comment>%command.full_name% add-member oc_abc123 --member=user@example.com --member-type=email</comment>

    <info>移除群成员：</info>
      <comment>%command.full_name% remove-member oc_abc123 --member=open_123</comment>

    <info>查看群成员列表：</info>
      <comment>%command.full_name% members oc_abc123</comment>
      <comment>%command.full_name% members oc_abc123 --page=2</comment>
    TXT)]
class GroupManageCommand extends Command
{
    public const NAME = 'lark:group:manage';

    public function __construct(
        private readonly ActionHandlerRegistry $handlerRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                '操作类型：list、info、create、update、add-member、remove-member、members'
            )
            ->addArgument(
                'chat_id',
                InputArgument::OPTIONAL,
                '群组ID（某些操作需要）'
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                '群组名称（创建或更新时使用）'
            )
            ->addOption(
                'description',
                null,
                InputOption::VALUE_REQUIRED,
                '群组描述'
            )
            ->addOption(
                'member',
                'm',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                '成员ID（可多次使用）',
                []
            )
            ->addOption(
                'member-type',
                null,
                InputOption::VALUE_REQUIRED,
                '成员ID类型：open_id、user_id、email',
                'open_id'
            )
            ->addOption(
                'owner',
                null,
                InputOption::VALUE_REQUIRED,
                '群主ID'
            )
            ->addOption(
                'public',
                null,
                InputOption::VALUE_NONE,
                '设置为公开群'
            )
            ->addOption(
                'external',
                null,
                InputOption::VALUE_NONE,
                '允许外部用户加入'
            )
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                '页码（用于列表分页）',
                '1'
            )
            ->addOption(
                'size',
                's',
                InputOption::VALUE_REQUIRED,
                '每页数量',
                '20'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $action = $input->getArgument('action');
        $chatId = $input->getArgument('chat_id');

        // Ensure action is a string
        if (!\is_string($action)) {
            throw InvalidCommandUsageException::invalidType('action', 'string', \gettype($action));
        }

        // chatId can be string or null, but not other types
        if (null !== $chatId && !\is_string($chatId)) {
            throw InvalidCommandUsageException::invalidType('chat_id', 'string|null', \gettype($chatId));
        }

        try {
            $handler = $this->handlerRegistry->getHandler($action);

            return $handler->execute($chatId, $input, $io);
        } catch (\Exception $e) {
            $io->error(\sprintf('操作失败: %s', $e->getMessage()));

            if ($output->isVeryVerbose()) {
                $io->section('错误详情');
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
