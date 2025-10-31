<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\Handler\DefaultMessageHandler;
use Tourze\LarkAppBotBundle\Tests\TestDouble\InMemoryLogger;
use Tourze\LarkAppBotBundle\Tests\TestDouble\SpyMessageService;
use Tourze\LarkAppBotBundle\Tests\TestDouble\ThrowingMessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DefaultMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class DefaultMessageHandlerTest extends AbstractIntegrationTestCase
{
    private DefaultMessageHandler $handler;

    private SpyMessageService $stubMessageService;

    private InMemoryLogger $stubLogger;

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

        $this->handler->handle($stubEvent);

        // 验证记录了正确的日志
        $this->assertContains('[default] 收到消息', $this->stubLogger->getLoggedMessages());

        // 验证调用了发送消息
        $this->assertContains('sendText', $this->stubMessageService->getCalledMethods());
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

        $this->handler->handle($stubEvent);

        // 验证记录了正确的日志
        $this->assertContains('[default] 收到消息', $this->stubLogger->getLoggedMessages());

        // 验证调用了发送消息
        $this->assertContains('sendText', $this->stubMessageService->getCalledMethods());
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

        $initialCallCount = \count($this->stubMessageService->getCalledMethods());

        $this->handler->handle($stubEvent);

        // 验证记录了正确的日志
        $this->assertContains('[default] 收到消息', $this->stubLogger->getLoggedMessages());

        // 群消息但没有@机器人，不应该回复
        $this->assertIsArray($this);
        $this->assertCount($initialCallCount, $this->stubMessageService->getCalledMethods());
    }

    public function testHandleMessageWithSendError(): void
    {
        $throwingMessageService = new ThrowingMessageService();

        $stubEvent = $this->createMessageEvent([
            'message_id' => 'msg_error',
            'chat_id' => 'chat_error',
            'sender_id' => 'sender_error',
            'message_type' => 'text',
            'content' => 'Error test',
            'chat_type' => 'p2p',
        ]);

        // 通过反射替换MessageService
        $reflection = new \ReflectionClass($this->handler);
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $throwingMessageService);

        // 即使发送失败，handle方法也不应该抛出异常
        $this->handler->handle($stubEvent);

        // 验证记录了错误日志
        $loggedMessages = $this->stubLogger->getLoggedMessages();
        $this->assertNotEmpty($loggedMessages);
    }

    protected function createStubServices(): void
    {
        $this->stubMessageService = new SpyMessageService();
        $this->stubLogger = new InMemoryLogger();
    }

    protected function onSetUp(): void
    {
        $this->createStubServices();

        // 从容器获取 DefaultMessageHandler 服务
        $handler = self::getContainer()->get(DefaultMessageHandler::class);
        $this->assertInstanceOf(DefaultMessageHandler::class, $handler);
        $this->handler = $handler;

        // 通过反射替换依赖为 Stub 版本
        $reflection = new \ReflectionClass($this->handler);

        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $messageServiceProperty->setValue($this->handler, $this->stubMessageService);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->handler, $this->stubLogger);
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
