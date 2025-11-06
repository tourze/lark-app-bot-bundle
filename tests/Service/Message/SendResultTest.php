<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\SendResult;

/**
 * @internal
 */
#[CoversClass(SendResult::class)]
final class SendResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $result = new SendResult(
            true,
            'msg_12345',
            null,
            0
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('msg_12345', $result->getMessageId());
        $this->assertNull($result->getError());
        $this->assertSame(0, $result->getErrorCode());
    }

    public function testFailedResult(): void
    {
        $result = new SendResult(
            false,
            null,
            'Invalid receiver ID',
            400
        );

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getMessageId());
        $this->assertSame('Invalid receiver ID', $result->getError());
        $this->assertSame(400, $result->getErrorCode());
    }

    public function testFailedResultWithMessageId(): void
    {
        // 某些情况下即使失败也可能有消息ID
        $result = new SendResult(
            false,
            'msg_failed_123',
            'Partial delivery failure',
            500
        );

        $this->assertFalse($result->isSuccess());
        $this->assertSame('msg_failed_123', $result->getMessageId());
        $this->assertSame('Partial delivery failure', $result->getError());
        $this->assertSame(500, $result->getErrorCode());
    }

    public function testDefaultErrorCode(): void
    {
        // 测试不提供错误码时的默认值
        $result = new SendResult(
            false,
            null,
            'Some error'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getMessageId());
        $this->assertSame('Some error', $result->getError());
        $this->assertSame(0, $result->getErrorCode());
    }

    public function testReadonlyProperties(): void
    {
        $result = new SendResult(
            true,
            'msg_readonly',
            null,
            0
        );

        // 验证所有属性都是只读的
        $reflection = new \ReflectionClass($result);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }

    public function testDifferentErrorScenarios(): void
    {
        // 测试各种错误场景
        $scenarios = [
            ['success' => false, 'messageId' => null, 'error' => 'Rate limit exceeded', 'errorCode' => 429],
            ['success' => false, 'messageId' => null, 'error' => 'Unauthorized', 'errorCode' => 401],
            ['success' => false, 'messageId' => null, 'error' => 'Server error', 'errorCode' => 503],
            ['success' => false, 'messageId' => null, 'error' => 'Invalid message format', 'errorCode' => 422],
            ['success' => false, 'messageId' => null, 'error' => 'Network timeout', 'errorCode' => 0]];

        foreach ($scenarios as $scenario) {
            $result = new SendResult(
                $scenario['success'],
                $scenario['messageId'],
                $scenario['error'],
                $scenario['errorCode']
            );

            $this->assertFalse($result->isSuccess());
            $this->assertNull($result->getMessageId());
            $this->assertSame($scenario['error'], $result->getError());
            $this->assertSame($scenario['errorCode'], $result->getErrorCode());
        }
    }

    public function testSuccessWithoutMessageId(): void
    {
        // 某些成功场景可能没有消息ID（比如批量发送的汇总结果）
        $result = new SendResult(
            true,
            null,
            null,
            0
        );

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getMessageId());
        $this->assertNull($result->getError());
        $this->assertSame(0, $result->getErrorCode());
    }

    public function testSuccessWithErrorInfo(): void
    {
        // 测试成功但带有警告信息的场景
        $result = new SendResult(
            true,
            'msg_with_warning',
            'Message sent but some features may not work',
            0
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('msg_with_warning', $result->getMessageId());
        $this->assertSame('Message sent but some features may not work', $result->getError());
        $this->assertSame(0, $result->getErrorCode());
    }
}
