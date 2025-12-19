<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Group\GroupCommandHandler;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（GroupService、MessageService、MessageEvent）
 * 这些具体类被 Mock 是为了隔离测试环境，避免对外部依赖的调用
 * 测试重点在于验证 GroupCommandHandler 的命令处理逻辑，而不是依赖类的具体实现
 */
#[CoversClass(GroupCommandHandler::class)]
#[RunTestsInSeparateProcesses]
final class GroupCommandHandlerTest extends AbstractIntegrationTestCase
{
    private GroupCommandHandler $handler;

    private MockObject $groupService;

    private MockObject $messageService;

    public function testSupportsGroupTextCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_type' => 'group',
            'message_type' => 'text',
            'content' => json_encode(['text' => '/help'])]);

        $this->assertTrue($this->handler->supports($event));
    }

    public function testDoesNotSupportP2PMessage(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_type' => 'p2p',
            'message_type' => 'text',
            'content' => json_encode(['text' => '/help'])]);

        $this->assertFalse($this->handler->supports($event));
    }

    public function testDoesNotSupportNonTextMessage(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_type' => 'group',
            'message_type' => 'image']);

        $this->assertFalse($this->handler->supports($event));
    }

    public function testDoesNotSupportNonCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_type' => 'group',
            'message_type' => 'text',
            'content' => json_encode(['text' => 'hello world'])]);

        $this->assertFalse($this->handler->supports($event));
    }

    public function testHandleHelpCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/help']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleInfoCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/info']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $groupInfo = [
            'name' => '测试群组',
            'description' => '测试描述',
            'owner_id' => 'user123',
            'user_count' => 10,
            'bot_count' => 1,
            'chat_type' => 'group',
            'external' => false];

        $this->groupService->expects($this->atLeastOnce())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleMembersCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/members']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $members = [
            'items' => [
                ['member_id' => 'user1', 'name' => '用户1'],
                ['member_id' => 'user2', 'name' => '用户2']],
            'has_more' => true,
            'member_total' => 50];

        $this->groupService->expects($this->atLeastOnce())
            ->method('getMembers')
            ->with('oc_123456', 'user_id', null, 20)
            ->willReturn($members)
        ;

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleAddCommandAsOwner(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/add user1 user2']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'owner123',
                    'user_id' => 'owner123',
                    'union_id' => 'owner123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $groupInfo = ['owner_id' => 'owner123'];
        $this->groupService->expects($this->atLeastOnce())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->groupService->expects($this->atLeastOnce())
            ->method('addMembers')
            ->with('oc_123456', ['user1', 'user2'])
            ->willReturn([
                'invalid_id_list' => [],
                'not_existed_id_list' => []])
        ;

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleAddCommandAsNonOwner(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/add user1']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $groupInfo = ['owner_id' => 'owner123'];
        $this->groupService->expects($this->atLeastOnce())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleStatsCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/stats']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $groupInfo = [
            'name' => '测试群组',
            'bot_count' => 1];

        $members = [
            ['member_id' => 'user1', 'name' => '用户1', 'tenant_key' => 'tenant1'],
            ['member_id' => 'user2', 'name' => '用户2', 'tenant_key' => 'tenant1'],
            ['member_id' => 'user3', 'tenant_key' => 'tenant2']];

        $this->groupService->expects($this->atLeastOnce())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->groupService->expects($this->atLeastOnce())
            ->method('getAllMembers')
            ->with('oc_123456')
            ->willReturn($members)
        ;

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testHandleUnknownCommand(): void
    {
        $event = $this->createTestMessageEvent([
            'chat_id' => 'oc_123456',
            'chat_type' => 'group',
            'content' => json_encode(['text' => '/unknown']),
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user123',
                    'user_id' => 'user123',
                    'union_id' => 'user123'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']]);

        $this->messageService->expects($this->atLeastOnce())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::anything(),
                null,
                'chat_id'
            )
        ;

        $this->handler->handle($event);
    }

    public function testGetPriority(): void
    {
        $this->assertSame(100, $this->handler->getPriority());
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象用于测试隔离
        $this->groupService = $this->createMock(GroupService::class);
        $this->messageService = $this->createMock(MessageService::class);
        $logger = $this->createMock(LoggerInterface::class);

        // 注册 mock 对象到容器
        self::getContainer()->set(GroupService::class, $this->groupService);
        self::getContainer()->set(MessageService::class, $this->messageService);
        self::getContainer()->set(LoggerInterface::class, $logger);

        // 从容器获取 handler 实例
        $this->handler = self::getService(GroupCommandHandler::class);
    }

    /**
     * 创建测试用的 MessageEvent 实例.
     *
     * @param array<string, mixed> $data
     */
    private function createTestMessageEvent(array $data = []): MessageEvent
    {
        $defaultData = [
            'message_id' => 'test-message-id',
            'message_type' => 'text',
            'chat_id' => 'test-chat-id',
            'chat_type' => 'p2p',
            'content' => '',
            'sender' => [
                'sender_id' => [
                    'open_id' => 'test-open-id',
                    'user_id' => 'test-user-id',
                    'union_id' => 'test-union-id'],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant']];

        return new MessageEvent(
            'im.message.receive_v1',
            array_merge($defaultData, $data),
            [
                'event_id' => 'test-event-id',
                'tenant_key' => 'test-tenant',
                'app_id' => 'test-app-id']
        );
    }
}
