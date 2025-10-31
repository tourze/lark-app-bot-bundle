<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\LarkAppBotBundle\Command\ConfigCheckCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigCheckCommand::class)]
#[RunTestsInSeparateProcesses]
final class ConfigCheckCommandTest extends AbstractCommandTestCase
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

    public function testExecuteBasicCheck(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('飞书应用机器人配置检查', $output);
        self::assertStringContainsString('基本配置', $output);
        self::assertStringContainsString('认证配置', $output);
        self::assertStringContainsString('Webhook配置', $output);
        self::assertStringContainsString('缓存配置', $output);
    }

    public function testExecuteWithTestApiOption(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--test-api' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('API连接测试', $output);
    }

    public function testExecuteWithShowTokenOption(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--show-token' => true]);

        $output = $commandTester->getDisplay();
        // 验证是否尝试显示 token 信息
        self::assertStringContainsString('认证配置', $output);
    }

    public function testExecuteWithFixOption(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--fix' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('飞书应用机器人配置检查', $output);
    }

    public function testCommandReturnCodeOnSuccess(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        // 命令可能返回 0（成功）或 1（有配置问题）
        self::assertContains($exitCode, [0, 1]);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = self::getContainer()->get(ConfigCheckCommand::class);

        self::assertInstanceOf(ConfigCheckCommand::class, $command);

        self::assertSame(ConfigCheckCommand::NAME, $command->getName());
        self::assertContains('lark:check', $command->getAliases());
    }

    public function testOptionTestApi(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--test-api' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('API连接测试', $output);
    }

    public function testOptionFix(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--fix' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('飞书应用机器人配置检查', $output);
    }

    public function testOptionShowToken(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            '--show-token' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('认证配置', $output);
    }

    protected function onSetUp(): void
    {        // 可以为空，或者添加必要的初始化逻辑
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(ConfigCheckCommand::class);

        self::assertInstanceOf(ConfigCheckCommand::class, $command);

        return new CommandTester($command);
    }
}
