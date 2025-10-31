<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\LarkAppBotBundle\Command\GroupManageCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 群组管理命令测试.
 *
 * @internal
 */
#[CoversClass(GroupManageCommand::class)]
#[RunTestsInSeparateProcesses]
final class GroupManageCommandTest extends AbstractCommandTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // 设置测试环境变量
        $_ENV['LARK_APP_ID'] = 'test_app_id';
        $_ENV['LARK_APP_SECRET'] = 'test_app_secret';
        $_ENV['LARK_CACHE_DIR'] = '/tmp/lark-app-bot-test';
        $_ENV['LARK_VERIFICATION_TOKEN'] = 'test_verification_token';
        $_ENV['LARK_ENCRYPT_KEY'] = 'test_encrypt_key';
    }

    public function testCommandExists(): void
    {
        $command = $this->getCommand();
        $this->assertInstanceOf(GroupManageCommand::class, $command);
    }

    public function testExecuteWithoutArguments(): void
    {
        $command = $this->getCommand();
        $commandTester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "action")');
        $commandTester->execute([]);
    }

    public function testExecuteWithHelpOption(): void
    {
        $command = $this->getCommand();
        $commandTester = new CommandTester($command);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "--help" option does not exist');
        $commandTester->execute(['--help' => true]);
    }

    public function testArgumentAction(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that action argument exists and is required
        $this->assertTrue($definition->hasArgument('action'));
        $argument = $definition->getArgument('action');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('操作类型：list、info、create、update、add-member、remove-member、members', $argument->getDescription());
    }

    public function testArgumentChatId(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that chat_id argument exists and is optional
        $this->assertTrue($definition->hasArgument('chat_id'));
        $argument = $definition->getArgument('chat_id');
        $this->assertFalse($argument->isRequired());
        $this->assertSame('群组ID（某些操作需要）', $argument->getDescription());
    }

    public function testOptionName(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that name option exists
        $this->assertTrue($definition->hasOption('name'));
        $option = $definition->getOption('name');
        $this->assertSame('群组名称（创建或更新时使用）', $option->getDescription());
    }

    public function testOptionDescription(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that description option exists
        $this->assertTrue($definition->hasOption('description'));
        $option = $definition->getOption('description');
        $this->assertSame('群组描述', $option->getDescription());
    }

    public function testOptionMember(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that member option exists and is array type
        $this->assertTrue($definition->hasOption('member'));
        $option = $definition->getOption('member');
        $this->assertTrue($option->isArray());
    }

    public function testOptionMemberType(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that member-type option exists with default value
        $this->assertTrue($definition->hasOption('member-type'));
        $option = $definition->getOption('member-type');
        $this->assertSame('open_id', $option->getDefault());
    }

    public function testOptionPage(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that page option exists with default value '1'
        $this->assertTrue($definition->hasOption('page'));
        $option = $definition->getOption('page');
        $this->assertSame('1', $option->getDefault());
    }

    public function testOptionSize(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that size option exists with default value '20'
        $this->assertTrue($definition->hasOption('size'));
        $option = $definition->getOption('size');
        $this->assertSame('20', $option->getDefault());
    }

    public function testOptionOwner(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that owner option exists
        $this->assertTrue($definition->hasOption('owner'));
        $option = $definition->getOption('owner');
        $this->assertSame('群主ID', $option->getDescription());
    }

    public function testOptionPublic(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that public option exists and is a flag (VALUE_NONE)
        $this->assertTrue($definition->hasOption('public'));
        $option = $definition->getOption('public');
        $this->assertFalse($option->acceptValue());
    }

    public function testOptionExternal(): void
    {
        $command = $this->getCommand();
        $definition = $command->getDefinition();

        // Test that external option exists and is a flag (VALUE_NONE)
        $this->assertTrue($definition->hasOption('external'));
        $option = $definition->getOption('external');
        $this->assertFalse($option->acceptValue());
    }

    protected function onSetUp(): void
    {        // 可以为空，或者添加必要的初始化逻辑
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(GroupManageCommand::class);

        self::assertInstanceOf(GroupManageCommand::class, $command);

        return new CommandTester($command);
    }

    private function getCommand(): GroupManageCommand
    {
        $command = self::getContainer()->get(GroupManageCommand::class);

        self::assertInstanceOf(GroupManageCommand::class, $command);

        return $command;
    }
}
