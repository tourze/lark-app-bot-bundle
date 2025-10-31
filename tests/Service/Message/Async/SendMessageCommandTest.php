<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Async;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageCommand;

/**
 * @internal
 */
#[CoversClass(SendMessageCommand::class)]
final class SendMessageCommandTest extends TestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $receiveId = 'user123';
        $msgType = 'text';
        $content = ['text' => 'Hello World'];
        $receiveIdType = 'open_id';

        $command = new SendMessageCommand(
            $receiveId,
            $msgType,
            $content,
            $receiveIdType
        );

        $this->assertSame($receiveId, $command->getReceiveId());
        $this->assertSame($msgType, $command->getMsgType());
        $this->assertSame($content, $command->getContent());
        $this->assertSame($receiveIdType, $command->getReceiveIdType());
        $this->assertSame([], $command->getOptions());
        $this->assertNull($command->getCorrelationId());
        $this->assertSame(0, $command->getRetryCount());
    }

    public function testConstructorWithAllParameters(): void
    {
        $receiveId = 'user456';
        $msgType = 'interactive';
        $content = ['card' => ['header' => ['title' => ['content' => 'Test Card']]]];
        $receiveIdType = 'user_id';
        $options = ['uuid' => 'unique-id', 'priority' => 'high'];
        $correlationId = 'corr_456';
        $retryCount = 3;
        $timestamp = microtime(true);

        $command = new SendMessageCommand(
            $receiveId,
            $msgType,
            $content,
            $receiveIdType,
            $options,
            $correlationId,
            $retryCount,
            $timestamp
        );

        $this->assertSame($receiveId, $command->getReceiveId());
        $this->assertSame($msgType, $command->getMsgType());
        $this->assertSame($content, $command->getContent());
        $this->assertSame($receiveIdType, $command->getReceiveIdType());
        $this->assertSame($options, $command->getOptions());
        $this->assertSame($correlationId, $command->getCorrelationId());
        $this->assertSame($retryCount, $command->getRetryCount());
        $this->assertSame($timestamp, $command->getTimestamp());
    }

    public function testIncrementRetryCount(): void
    {
        $command = new SendMessageCommand(
            'user789',
            'text',
            'Test message',
            'open_id',
            ['key' => 'value'],
            'corr_789',
            2
        );

        $this->assertSame(2, $command->getRetryCount());

        $newCommand = $command->incrementRetryCount();

        // 验证返回新实例
        $this->assertNotSame($command, $newCommand);

        // 验证原实例未变
        $this->assertSame(2, $command->getRetryCount());

        // 验证新实例重试次数增加
        $this->assertSame(3, $newCommand->getRetryCount());

        // 验证其他属性保持不变
        $this->assertSame($command->getReceiveId(), $newCommand->getReceiveId());
        $this->assertSame($command->getMsgType(), $newCommand->getMsgType());
        $this->assertSame($command->getContent(), $newCommand->getContent());
        $this->assertSame($command->getReceiveIdType(), $newCommand->getReceiveIdType());
        $this->assertSame($command->getOptions(), $newCommand->getOptions());
        $this->assertSame($command->getCorrelationId(), $newCommand->getCorrelationId());
    }

    public function testGetAge(): void
    {
        $timestamp = microtime(true) - 10.0; // 10秒前
        $command = new SendMessageCommand(
            'user_old',
            'text',
            'Old message',
            'open_id',
            [],
            null,
            0,
            $timestamp
        );

        $age = $command->getAge();
        $this->assertGreaterThanOrEqual(10.0, $age);
        $this->assertLessThan(11.0, $age); // 考虑执行时间
    }

    public function testGetTimestampWithProvidedValue(): void
    {
        $timestamp = 1234567890.456;
        $command = new SendMessageCommand(
            'user_timestamp',
            'text',
            'Message with timestamp',
            'open_id',
            [],
            null,
            0,
            $timestamp
        );

        $this->assertSame($timestamp, $command->getTimestamp());
    }

    public function testGetTimestampWithDefault(): void
    {
        $beforeTime = microtime(true);
        $command = new SendMessageCommand(
            'user_default',
            'text',
            'Message without timestamp',
            'open_id'
        );
        $afterTime = microtime(true);

        $timestamp = $command->getTimestamp();
        // 添加小的容差来处理浮点数精度问题
        $this->assertGreaterThanOrEqual($beforeTime - 0.000001, $timestamp);
        $this->assertLessThanOrEqual($afterTime + 0.000001, $timestamp);
    }

    public function testContentCanBeString(): void
    {
        $content = 'Simple text message';
        $command = new SendMessageCommand(
            'user_string',
            'text',
            $content,
            'open_id'
        );

        $this->assertSame($content, $command->getContent());
        $this->assertIsString($command->getContent());
    }

    public function testContentCanBeArray(): void
    {
        $content = [
            'text' => 'Rich message',
            'mentions' => ['@user1', '@user2'],
            'at_all' => false,
        ];
        $command = new SendMessageCommand(
            'user_array',
            'text',
            $content,
            'open_id'
        );

        $this->assertSame($content, $command->getContent());
        $this->assertIsArray($command->getContent());
    }

    public function testDifferentMessageTypes(): void
    {
        $messageTypes = ['text', 'interactive', 'post', 'image', 'file'];

        foreach ($messageTypes as $msgType) {
            $command = new SendMessageCommand(
                'test_user',
                $msgType,
                ['content' => 'test'],
                'open_id'
            );
            $this->assertSame($msgType, $command->getMsgType());
        }
    }

    public function testDifferentReceiveIdTypes(): void
    {
        $idTypes = ['open_id', 'user_id', 'union_id', 'email', 'chat_id'];

        foreach ($idTypes as $idType) {
            $command = new SendMessageCommand(
                'test_receiver',
                'text',
                'test',
                $idType
            );
            $this->assertSame($idType, $command->getReceiveIdType());
        }
    }

    public function testOptionsHandling(): void
    {
        $emptyOptions = [];
        $simpleOptions = ['key' => 'value'];
        $complexOptions = [
            'uuid' => 'unique-123',
            'priority' => 'high',
            'metadata' => ['source' => 'api', 'version' => '1.0'],
            'retry_config' => ['max_attempts' => 3, 'delay' => 1000],
        ];

        $command1 = new SendMessageCommand('user1', 'text', 'msg', 'open_id', $emptyOptions);
        $this->assertSame($emptyOptions, $command1->getOptions());
        $this->assertIsArray($command1);
        $this->assertCount(0, $command1->getOptions());

        $command2 = new SendMessageCommand('user2', 'text', 'msg', 'open_id', $simpleOptions);
        $this->assertSame($simpleOptions, $command2->getOptions());
        $this->assertIsArray($command2);
        $this->assertCount(1, $command2->getOptions());

        $command3 = new SendMessageCommand('user3', 'text', 'msg', 'open_id', $complexOptions);
        $this->assertSame($complexOptions, $command3->getOptions());
        $this->assertIsArray($command3);
        $this->assertCount(4, $command3->getOptions());
    }

    protected function onSetUp(): void
    {// 无需特殊初始化
    }
}
