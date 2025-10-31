<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Async;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Async\BatchSendMessageCommand;

/**
 * @internal
 */
#[CoversClass(BatchSendMessageCommand::class)]
final class BatchSendMessageCommandTest extends TestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $receiveIds = ['user1', 'user2', 'user3'];
        $msgType = 'text';
        $content = ['text' => 'Hello World'];
        $receiveIdType = 'open_id';

        $command = new BatchSendMessageCommand(
            $receiveIds,
            $msgType,
            $content,
            $receiveIdType
        );

        $this->assertSame($receiveIds, $command->getReceiveIds());
        $this->assertSame($msgType, $command->getMsgType());
        $this->assertSame($content, $command->getContent());
        $this->assertSame($receiveIdType, $command->getReceiveIdType());
        $this->assertSame([], $command->getOptions());
        $this->assertNull($command->getCorrelationId());
        $this->assertSame(0, $command->getRetryCount());
    }

    public function testConstructorWithAllParameters(): void
    {
        $receiveIds = ['user1', 'user2'];
        $msgType = 'card';
        $content = ['card' => ['header' => ['title' => 'Test']]];
        $receiveIdType = 'user_id';
        $options = ['uuid' => 'test-uuid', 'priority' => 'high'];
        $correlationId = 'corr_123';
        $retryCount = 2;
        $timestamp = microtime(true);

        $command = new BatchSendMessageCommand(
            $receiveIds,
            $msgType,
            $content,
            $receiveIdType,
            $options,
            $correlationId,
            $retryCount,
            $timestamp
        );

        $this->assertSame($receiveIds, $command->getReceiveIds());
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
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
            'open_id',
            [],
            'corr_123',
            1
        );

        $this->assertSame(1, $command->getRetryCount());

        $newCommand = $command->incrementRetryCount();

        // 验证返回新实例
        $this->assertNotSame($command, $newCommand);

        // 验证原实例未变
        $this->assertSame(1, $command->getRetryCount());

        // 验证新实例重试次数增加
        $this->assertSame(2, $newCommand->getRetryCount());

        // 验证其他属性保持不变
        $this->assertSame($command->getReceiveIds(), $newCommand->getReceiveIds());
        $this->assertSame($command->getMsgType(), $newCommand->getMsgType());
        $this->assertSame($command->getContent(), $newCommand->getContent());
        $this->assertSame($command->getReceiveIdType(), $newCommand->getReceiveIdType());
        $this->assertSame($command->getOptions(), $newCommand->getOptions());
        $this->assertSame($command->getCorrelationId(), $newCommand->getCorrelationId());
    }

    public function testGetAge(): void
    {
        $timestamp = microtime(true) - 5.0; // 5秒前
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
            'open_id',
            [],
            null,
            0,
            $timestamp
        );

        $age = $command->getAge();
        $this->assertGreaterThanOrEqual(5.0, $age);
        $this->assertLessThan(6.0, $age); // 考虑执行时间
    }

    public function testGetTimestampWithProvidedValue(): void
    {
        $timestamp = 1234567890.123;
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
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
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
            'open_id'
        );

        $timestamp1 = $command->getTimestamp();
        usleep(1000); // 等待1毫秒
        $timestamp2 = $command->getTimestamp();

        // 验证 getTimestamp() 每次调用都返回当前时间，而不是缓存值
        $this->assertIsFloat($timestamp1);
        $this->assertIsFloat($timestamp2);
        $this->assertGreaterThan(0, $timestamp1);
        $this->assertGreaterThan(0, $timestamp2);
        $this->assertGreaterThanOrEqual($timestamp1, $timestamp2);
    }

    public function testSplitWithDefaultBatchSize(): void
    {
        $receiveIds = array_map(fn ($i) => "user{$i}", range(1, 120));
        $command = new BatchSendMessageCommand(
            $receiveIds,
            'text',
            'Hello',
            'open_id',
            ['uuid' => 'test'],
            'corr_123'
        );

        $batches = $command->split();

        // 默认批次大小为50，120个ID应该分成3批
        $this->assertIsArray($batches);
        $this->assertCount(3, $batches);

        // 验证前两批各50个
        $this->assertCount(50, $batches[0]->getReceiveIds());
        $this->assertCount(50, $batches[1]->getReceiveIds());
        $this->assertIsArray($batches);
        $this->assertCount(20, $batches[2]->getReceiveIds());

        // 验证每个批次的属性
        foreach ($batches as $index => $batch) {
            $this->assertInstanceOf(BatchSendMessageCommand::class, $batch);
            $this->assertSame($command->getMsgType(), $batch->getMsgType());
            $this->assertSame($command->getContent(), $batch->getContent());
            $this->assertSame($command->getReceiveIdType(), $batch->getReceiveIdType());
            $this->assertSame($command->getOptions(), $batch->getOptions());
            $this->assertSame("corr_123_batch_{$index}", $batch->getCorrelationId());
            $this->assertSame($command->getRetryCount(), $batch->getRetryCount());
        }
    }

    public function testSplitWithCustomBatchSize(): void
    {
        $receiveIds = array_map(fn ($i) => "user{$i}", range(1, 10));
        $command = new BatchSendMessageCommand(
            $receiveIds,
            'text',
            'Hello',
            'open_id'
        );

        $batches = $command->split(3);

        // 10个ID，每批3个，应该分成4批
        $this->assertIsArray($batches);
        $this->assertCount(4, $batches);
        $this->assertCount(3, $batches[0]->getReceiveIds());
        $this->assertCount(3, $batches[1]->getReceiveIds());
        $this->assertCount(3, $batches[2]->getReceiveIds());
        $this->assertCount(1, $batches[3]->getReceiveIds());

        // 验证ID正确分配
        $this->assertSame(['user1', 'user2', 'user3'], $batches[0]->getReceiveIds());
        $this->assertSame(['user4', 'user5', 'user6'], $batches[1]->getReceiveIds());
        $this->assertSame(['user7', 'user8', 'user9'], $batches[2]->getReceiveIds());
        $this->assertSame(['user10'], $batches[3]->getReceiveIds());
    }

    public function testSplitWithSingleReceiveId(): void
    {
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
            'open_id'
        );

        $batches = $command->split();

        $this->assertIsArray($batches);
        $this->assertCount(1, $batches);
        $this->assertSame(['user1'], $batches[0]->getReceiveIds());
    }

    public function testContentCanBeString(): void
    {
        $content = 'Simple text message';
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            $content,
            'open_id'
        );

        $this->assertSame($content, $command->getContent());
        $this->assertIsString($command->getContent());
    }

    public function testContentCanBeArray(): void
    {
        $content = ['text' => 'Hello', 'mentions' => ['user1']];
        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            $content,
            'open_id'
        );

        $this->assertSame($content, $command->getContent());
        $this->assertIsArray($command->getContent());
    }
}
