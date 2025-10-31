<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\LarkAppBotBundle\Command\SendMessageCommand;
use Tourze\LarkAppBotBundle\Exception\JsonEncodingException;
use Tourze\LarkAppBotBundle\Service\Message\Builder\RichTextBuilder;
use Tourze\LarkAppBotBundle\Service\Message\Builder\TextMessageBuilder;
use Tourze\LarkAppBotBundle\Tests\TestDouble\StubLarkClient;
use Tourze\LarkAppBotBundle\Tests\TestDouble\StubMessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 发送消息命令测试.
 *
 * @internal
 */
#[CoversClass(SendMessageCommand::class)]
#[RunTestsInSeparateProcesses]
final class SendMessageCommandTest extends AbstractCommandTestCase
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

    public function testCommandHasCorrectName(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $this->assertSame('lark:message:send', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $this->assertSame('发送消息到飞书用户或群组', $command->getDescription());
    }

    public function testCommandHasCorrectAliases(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $this->assertContains('lark:send', $command->getAliases());
    }

    public function testExecuteWithMissingArguments(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "receiver, message")');
        $commandTester = $this->getCommandTester();
        $commandTester->execute([]);
    }

    public function testExecuteWithInvalidReceiver(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            'receiver' => 'invalid-receiver',
            'message' => 'Test message']);

        // 应该返回错误状态码
        $this->assertNotSame(0, $commandTester->getStatusCode());
    }

    public function testArgumentReceiver(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('receiver'));
        $argument = $definition->getArgument('receiver');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('接收者ID（用户ID、邮箱或群组ID）', $argument->getDescription());
    }

    public function testArgumentMessage(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('message'));
        $argument = $definition->getArgument('message');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('要发送的消息内容', $argument->getDescription());
    }

    public function testOptionType(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('type'));
        $option = $definition->getOption('type');
        $this->assertSame('text', $option->getDefault());
        $this->assertSame('消息类型：text（文本）、rich（富文本）、card（卡片）', $option->getDescription());
    }

    public function testOptionReceiverType(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('receiver-type'));
        $option = $definition->getOption('receiver-type');
        $this->assertSame('open_id', $option->getDefault());
        $this->assertSame('接收者类型：open_id、user_id、email、chat_id', $option->getDescription());
    }

    public function testOptionTitle(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('title'));
        $option = $definition->getOption('title');
        $this->assertSame('消息标题（用于卡片消息）', $option->getDescription());
    }

    public function testOptionTemplate(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('template'));
        $option = $definition->getOption('template');
        $this->assertSame('使用预定义模板：welcome、notification', $option->getDescription());
    }

    public function testOptionUrgent(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('urgent'));
        $option = $definition->getOption('urgent');
        $this->assertSame('标记为紧急消息', $option->getDescription());
        $this->assertFalse($option->acceptValue()); // VALUE_NONE
    }

    public function testOptionMention(): void
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('mention'));
        $option = $definition->getOption('mention');
        $this->assertSame('要@的用户ID（可多次使用）', $option->getDescription());
        $this->assertTrue($option->isArray()); // VALUE_IS_ARRAY
    }

    protected function onSetUp(): void
    {        // 注册 Mock 服务到容器
        $container = self::getContainer();

        // 创建 Mock LarkClient
        $responseData = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'message_id' => 'mock_message_id_123',
                'create_time' => '1640995200',
                'update_time' => '1640995200',
            ],
        ];
        $jsonResponse = json_encode($responseData);
        if (false === $jsonResponse) {
            throw JsonEncodingException::fromError('Failed to encode response data');
        }
        $mockResponse = new MockResponse($jsonResponse);
        $mockLarkClient = new StubLarkClient($mockResponse);

        // 使用内置的 NullLogger
        $mockLogger = new NullLogger();

        // 创建 Mock MessageService
        $messageService = new StubMessageService($mockLarkClient, $mockLogger);

        $textMessageBuilder = new TextMessageBuilder();
        $richTextBuilder = new RichTextBuilder();

        $container->set('Tourze\LarkAppBotBundle\Message\MessageService', $messageService);
        $container->set('Tourze\LarkAppBotBundle\Message\Builder\TextMessageBuilder', $textMessageBuilder);
        $container->set('Tourze\LarkAppBotBundle\Message\Builder\RichTextBuilder', $richTextBuilder);
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(SendMessageCommand::class);

        self::assertInstanceOf(SendMessageCommand::class, $command);

        return new CommandTester($command);
    }
}
