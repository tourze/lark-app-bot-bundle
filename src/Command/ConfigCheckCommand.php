<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\ApiConnectionChecker;
use Tourze\LarkAppBotBundle\Command\Checker\AuthConfigChecker;
use Tourze\LarkAppBotBundle\Command\Checker\BaseChecker;
use Tourze\LarkAppBotBundle\Command\Checker\BasicConfigChecker;
use Tourze\LarkAppBotBundle\Command\Checker\CacheConfigChecker;
use Tourze\LarkAppBotBundle\Command\Checker\PermissionsChecker;
use Tourze\LarkAppBotBundle\Command\Checker\WebhookConfigChecker;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * 配置检查的控制台命令.
 */
#[AsCommand(name: self::NAME, description: '检查飞书应用机器人配置和连接状态', aliases: ['lark:check'], help: <<<'TXT'
    <info>基本检查：</info>
      <comment>%command.full_name%</comment>

    <info>测试API连接：</info>
      <comment>%command.full_name% --test-api</comment>

    <info>显示详细信息（包括Token）：</info>
      <comment>%command.full_name% --show-token</comment>

    <info>尝试修复问题：</info>
      <comment>%command.full_name% --fix</comment>

    此命令会检查：
    - 必需的配置参数是否设置
    - API凭据是否有效
    - Token缓存是否正常工作
    - API连接是否正常
    - Webhook配置是否正确
    - 必要的权限是否已授予
    TXT)]
class ConfigCheckCommand extends Command
{
    public const NAME = 'lark:config:check';

    public function __construct(
        private readonly TokenProviderInterface $tokenManager,
        private readonly LarkClientInterface $larkClient,
        /** @var array<string, mixed> */ private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'test-api',
                't',
                InputOption::VALUE_NONE,
                '测试API连接'
            )
            ->addOption(
                'fix',
                'f',
                InputOption::VALUE_NONE,
                '尝试修复常见问题'
            )
            ->addOption(
                'show-token',
                null,
                InputOption::VALUE_NONE,
                '显示当前Access Token（敏感信息）'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $options = $this->extractOptions($input);

        $io->title('飞书应用机器人配置检查');

        $hasError = $this->runAllChecks($io, $options);

        return $this->determineExitCode($hasError, $io);
    }

    /**
     * 创建检查器实例.
     *
     * @return array<string, BaseChecker>
     */
    private function createCheckers(): array
    {
        return [
            'basic' => new BasicConfigChecker($this->config),
            'auth' => new AuthConfigChecker($this->config, $this->tokenManager),
            'webhook' => new WebhookConfigChecker($this->config),
            'cache' => new CacheConfigChecker($this->config),
            'permissions' => new PermissionsChecker($this->config),
            'api' => new ApiConnectionChecker($this->config, $this->larkClient),
        ];
    }

    /**
     * 提取命令选项.
     *
     * @return array{testApi: bool, fix: bool, showToken: bool}
     */
    private function extractOptions(InputInterface $input): array
    {
        return [
            'testApi' => (bool) $input->getOption('test-api'),
            'fix' => (bool) $input->getOption('fix'),
            'showToken' => (bool) $input->getOption('show-token'),
        ];
    }

    /**
     * 运行所有检查.
     *
     * @param array{testApi: bool, fix: bool, showToken: bool} $options
     */
    private function runAllChecks(SymfonyStyle $io, array $options): bool
    {
        $checkers = $this->createCheckers();
        $hasError = false;

        // 基础检查
        foreach (['basic', 'auth', 'webhook', 'cache', 'permissions'] as $checkerKey) {
            $checker = $checkers[$checkerKey];
            $io->section($checker->getName());

            if ('auth' === $checkerKey && $checker instanceof AuthConfigChecker) {
                $hasError = $checker->checkWithTokenVisibility($io, $options['fix'], $options['showToken']) || $hasError;
            } else {
                $hasError = $checker->check($io, $options['fix']) || $hasError;
            }
        }

        // API连接测试（可选）
        if ($options['testApi']) {
            $apiChecker = $checkers['api'];
            $io->section($apiChecker->getName());
            $hasError = $apiChecker->check($io, $options['fix']) || $hasError;
        }

        return $hasError;
    }

    private function determineExitCode(bool $hasError, SymfonyStyle $io): int
    {
        $io->newLine();

        if ($hasError) {
            $io->error('配置检查发现问题，请根据上述提示进行修复');

            return Command::FAILURE;
        }

        $io->success('所有配置检查通过！');

        return Command::SUCCESS;
    }
}
