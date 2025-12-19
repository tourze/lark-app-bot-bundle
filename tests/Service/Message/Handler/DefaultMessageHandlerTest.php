<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\Handler\DefaultMessageHandler;
use Tourze\LarkAppBotBundle\Service\Message\MessageServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DefaultMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class DefaultMessageHandlerTest extends AbstractIntegrationTestCase
{
    private DefaultMessageHandler $handler;

    public function testSupportsAllMessages(): void
    {
        $stubEvent = $this->createMessageEvent([]);

        // 默认处理器应该支持所有消息
        $this->assertTrue($this->handler->supports($stubEvent));
    }

    public function testGetName(): void
    {
        $this->assertSame('default', $this->handler->getName());
    }

    public function testGetPriority(): void
    {
        // 默认处理器应该有最低优先级
        $this->assertSame(-1000, $this->handler->getPriority());
    }

    public function testHandlePrivateMessage(): void
    {
        $stubEvent = $this->createMessageEvent([
            'message_id' => 'msg_123',
            'chat_id' => 'chat_456',
            'sender_id' => 'sender_789',
            'message_type' => 'text',
            'content' => 'Hello',
            'chat_type' => 'p2p',
        ]);

        // 创建MessageService mock
        $stubMessageService = $this->createMock(MessageServiceInterface::class);
        $stubMessageService->expects($this->once())
            ->method('sendText')
            ->withAnyParameters()
            ->willReturn([])
        ;

        // 创建Logger mock
        $stubLogger = $this->createMock(LoggerInterface::class);
        $stubLogger->expects($this->atLeastOnce())
            ->method('info')
        ;

        // 通过反射设置依赖
        $reflection = new \ReflectionClass($this->handler);
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $stubMessageService);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->handler, $stubLogger);

        $this->handler->handle($stubEvent);
    }

    public function testHandleGroupMessageWithMention(): void
    {
        $stubEvent = $this->createMessageEvent([
            'message_id' => 'msg_group_123',
            'chat_id' => 'chat_group_456',
            'sender_id' => 'sender_group_789',
            'message_type' => 'text',
            'content' => '@bot Hello',
            'chat_type' => 'group',
            'mentions' => [['user_id' => 'bot_id']],
        ]);

        // 创建MessageService mock
        $stubMessageService = $this->createMock(MessageServiceInterface::class);
        $stubMessageService->expects($this->once())
            ->method('sendText')
            ->withAnyParameters()
            ->willReturn([])
        ;

        // 创建Logger mock
        $stubLogger = $this->createMock(LoggerInterface::class);
        $stubLogger->expects($this->atLeastOnce())
            ->method('info')
        ;

        // 通过反射设置依赖
        $reflection = new \ReflectionClass($this->handler);
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $stubMessageService);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->handler, $stubLogger);

        $this->handler->handle($stubEvent);
    }

    public function testHandleGroupMessageWithoutMention(): void
    {
        $stubEvent = $this->createMessageEvent([
            'message_id' => 'msg_no_mention',
            'chat_id' => 'chat_no_mention',
            'sender_id' => 'sender_no_mention',
            'message_type' => 'text',
            'content' => 'Regular message',
            'chat_type' => 'group',
        ]);

        // 创建MessageService mock，不应该调用sendText
        $stubMessageService = $this->createMock(MessageServiceInterface::class);
        $stubMessageService->expects($this->never())
            ->method('sendText')
        ;

        // 创建Logger mock
        $stubLogger = $this->createMock(LoggerInterface::class);
        $stubLogger->expects($this->atLeastOnce())
            ->method('info')
        ;

        // 通过反射设置依赖
        $reflection = new \ReflectionClass($this->handler);
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $stubMessageService);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->handler, $stubLogger);

        $this->handler->handle($stubEvent);
    }

    public function testHandleMessageWithSendError(): void
    {
        $stubEvent = $this->createMessageEvent([
            'message_id' => 'msg_error',
            'chat_id' => 'chat_error',
            'sender_id' => 'sender_error',
            'message_type' => 'text',
            'content' => 'Error test',
            'chat_type' => 'p2p',
        ]);

        // 创建会抛出异常的MessageService mock
        $throwingMessageService = $this->createMock(MessageServiceInterface::class);
        $throwingMessageService->method('sendText')->willThrowException(new \Exception('Send failed'));

        // 创建Logger mock，期望记录错误日志
        $stubLogger = $this->createMock(LoggerInterface::class);
        $stubLogger->expects($this->atLeastOnce())
            ->method('error')
            ->withAnyParameters()
        ;

        // 通过反射设置依赖
        $reflection = new \ReflectionClass($this->handler);
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $throwingMessageService);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->handler, $stubLogger);

        // 即使发送失败，handle方法也不应该抛出异常
        $this->handler->handle($stubEvent);
    }

    protected function onSetUp(): void
    {
        // 从容器获取 DefaultMessageHandler 服务
        $handler = self::getContainer()->get(DefaultMessageHandler::class);
        $this->assertInstanceOf(DefaultMessageHandler::class, $handler);
        $this->handler = $handler;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMessageEvent(array $data): MessageEvent
    {
        return new MessageEvent(
            eventType: 'im.message.receive_v1',
            data: $data,
        );
    }
}
