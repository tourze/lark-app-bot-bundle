<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Handler\RemoveMembersActionHandler;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoveMembersActionHandler::class)]
#[RunTestsInSeparateProcesses]
final class RemoveMembersActionHandlerTest extends AbstractIntegrationTestCase
{
    protected function createInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('name', null, InputOption::VALUE_REQUIRED),
            new InputOption('description', null, InputOption::VALUE_REQUIRED),
            new InputOption('member', 'm', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '', []),
            new InputOption('member-type', null, InputOption::VALUE_REQUIRED, '', 'open_id'),
            new InputOption('owner', null, InputOption::VALUE_REQUIRED),
            new InputOption('public', null, InputOption::VALUE_NONE),
            new InputOption('external', null, InputOption::VALUE_NONE),
            new InputOption('page', 'p', InputOption::VALUE_REQUIRED, '', '1'),
            new InputOption('size', 's', InputOption::VALUE_REQUIRED, '', '20'),
            new InputOption('verbose', 'v', InputOption::VALUE_NONE),
        ]);
    }

    public function testGetActionName(): void
    {
        $handler = self::getService(RemoveMembersActionHandler::class);
        $this->assertSame('remove-member', $handler->getActionName());
    }

    public function testExecuteRequiresChatId(): void
    {
        $groupService = $this->createMock(GroupService::class);
        self::getContainer()->set(GroupService::class, $groupService);
        $handler = self::getService(RemoveMembersActionHandler::class);

        $input = new ArrayInput(['--member' => ['user1']], $this->createInputDefinition());
        $output = new BufferedOutput();
        $io = new SymfonyStyle($input, $output);

        $this->expectException(InvalidCommandUsageException::class);
        $handler->execute(null, $input, $io);
    }

    public function testExecuteRemovesMember(): void
    {
        $groupService = $this->createMock(GroupService::class);
        $groupService->expects($this->once())->method('removeMembers');
        self::getContainer()->set(GroupService::class, $groupService);
        $handler = self::getService(RemoveMembersActionHandler::class);

        $input = new ArrayInput(['--member' => ['user1']], $this->createInputDefinition());
        $output = new BufferedOutput();
        $io = new SymfonyStyle($input, $output);

        $result = $handler->execute('oc_test123', $input, $io);

        $this->assertSame(Command::SUCCESS, $result);
    }

    protected function onSetUp(): void
    {
        // 由于不同测试方法需要不同的mock配置，不在此处初始化handler
    }
}
