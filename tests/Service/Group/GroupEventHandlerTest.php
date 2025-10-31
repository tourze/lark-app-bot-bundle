<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GroupEvent;
use Tourze\LarkAppBotBundle\Event\GroupMemberEvent;
use Tourze\LarkAppBotBundle\Service\Group\GroupEventHandler;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 单元测试：GroupEventHandler.
 *
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（GroupEvent、GroupMemberEvent）
 * 这些事件类被 Mock 是为了控制测试数据，避免构造复杂的事件对象
 * 测试重点在于验证 GroupEventHandler 的业务逻辑处理，而不是事件类本身的实现
 */
#[CoversClass(GroupEventHandler::class)]
#[RunTestsInSeparateProcesses]
final class GroupEventHandlerTest extends AbstractIntegrationTestCase
{
    private GroupEventHandler $handler;

    private LoggerInterface&MockObject $logger;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private MessageService&MockObject $messageService;

    private GroupService&MockObject $groupService;

    public function testHandleGroupCreated(): void
    {
        /*
         * 使用具体类 GroupEvent 创建 Mock 对象的原因：
         * 1. GroupEvent 是事件数据的载体，包含群组相关的所有事件信息
         * 2. 构造真实的 GroupEvent 需要复杂的数据结构，mock 可以简化测试数据准备
         * 3. 测试关注点是事件处理器的行为，而非事件对象本身的构造
         * 4. 这种方式使得测试更加灵活，可以轻松模拟各种事件场景
         */
        $event = $this->createMock(GroupEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $groupInfo = [
            'chat_id' => 'oc_123456',
            'name' => '测试群组',
            'description' => '测试描述'];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->messageService->expects($this->once())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::stringContains('欢迎来到群组【测试群组】'),
                null,
                'chat_id'
            )
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupEvent::class)
            )
        ;

        $this->handler->handleGroupCreated($event);
    }

    public function testHandleGroupDisbanded(): void
    {
        /*
         * 使用具体类 GroupEvent 创建 Mock 对象的原因：
         * 1. 测试群解散事件处理需要模拟特定的事件数据
         * 2. GroupEvent 封装了飞书群组事件的所有信息，直接 mock 可以灵活设置测试数据
         * 3. 群解散是重要事件，通过 mock 可以验证处理器正确响应此事件
         * 4. 这种方式避免了构建复杂的事件对象，使测试更加简洁高效
         */
        $event = $this->createMock(GroupEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupEvent::class)
            )
        ;

        $this->handler->handleGroupDisbanded($event);
    }

    public function testHandleGroupUpdated(): void
    {
        /*
         * 使用具体类 GroupEvent 创建 Mock 对象的原因：
         * 1. 群更新事件可能包含多种信息变更，如名称、描述、国际化名称等
         * 2. 使用 mock 可以精确控制每个属性的返回值，测试不同的更新场景
         * 3. GroupEvent 作为数据载体，其内部结构可能复杂，mock 简化了测试准备
         * 4. 这种方式确保测试可以覆盖各种边界情况，提高代码健壮性
         */
        $event = $this->createMock(GroupEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getI18nNames')->willReturn(['zh_cn' => '中文名']);
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupEvent::class)
            )
        ;

        $this->handler->handleGroupUpdated($event);
    }

    public function testHandleMemberJoined(): void
    {
        $users = [
            ['user_id' => 'user1', 'name' => '用户1'],
            ['user_id' => 'user2', 'name' => '用户2']];

        /*
         * 使用具体类 GroupMemberEvent 创建 Mock 对象的原因：
         * 1. GroupMemberEvent 专门处理群成员变更事件，包含成员列表等特定信息
         * 2. 测试成员加入事件需要模拟多个用户数据，mock 可以简化数据准备
         * 3. 该事件类继承自 GroupEvent 并扩展了成员特定功能，直接 mock 更加灵活
         * 4. 这种方式便于测试批量成员加入的复杂场景
         */
        $event = $this->createMock(GroupMemberEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getUsers')->willReturn($users);
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456', 'users' => $users]);

        $matcher = $this->exactly(2);
        $this->messageService->expects($matcher)
            ->method('sendText')
            ->willReturnCallback(function ($chatId, $message, $rootId, $idType) use ($matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(['oc_123456', '欢迎 用户1 加入群组！', null, 'chat_id'], [$chatId, $message, $rootId, $idType]),
                    2 => $this->assertSame(['oc_123456', '欢迎 用户2 加入群组！', null, 'chat_id'], [$chatId, $message, $rootId, $idType]),
                    default => static::fail('Unexpected number of invocations: ' . $matcher->numberOfInvocations()),
                };

                return ['message_id' => 'test'];
            })
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupMemberEvent::class)
            )
        ;

        $this->handler->handleMemberJoined($event);
    }

    public function testHandleMemberLeft(): void
    {
        $users = [
            ['user_id' => 'user1', 'name' => '用户1']];

        /*
         * 使用具体类 GroupMemberEvent 创建 Mock 对象的原因：
         * 1. 成员移除事件需要传递被移除成员的详细信息
         * 2. GroupMemberEvent 提供了 getUsers() 等特定方法获取成员列表
         * 3. Mock 该类可以模拟单个或多个成员被移除的场景
         * 4. 这种设计支持灵活的事件处理测试，无需构建完整的事件对象
         */
        $event = $this->createMock(GroupMemberEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getUsers')->willReturn($users);
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456', 'users' => $users]);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupMemberEvent::class)
            )
        ;

        $this->handler->handleMemberLeft($event);
    }

    public function testHandleBotAdded(): void
    {
        /*
         * 使用具体类 GroupMemberEvent 创建 Mock 对象的原因：
         * 1. 机器人加入事件是特殊的成员事件，需要特殊处理
         * 2. 该事件触发机器人的欢迎消息和初始化流程
         * 3. Mock 该类可以验证机器人加入后的正确响应行为
         * 4. 这种方式确保了机器人初始化流程的可测试性
         */
        $event = $this->createMock(GroupMemberEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $groupInfo = [
            'chat_id' => 'oc_123456',
            'name' => '测试群组'];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with('oc_123456')
            ->willReturn($groupInfo)
        ;

        $this->messageService->expects($this->once())
            ->method('sendText')
            ->with(
                'oc_123456',
                self::stringContains('大家好！我是飞书机器人助手'),
                null,
                'chat_id'
            )
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupMemberEvent::class)
            )
        ;

        $this->handler->handleBotAdded($event);
    }

    public function testHandleBotRemoved(): void
    {
        /*
         * 使用具体类 GroupMemberEvent 创建 Mock 对象的原因：
         * 1. 机器人移除事件需要记录操作者和被移除群组的信息
         * 2. 该事件可能触发清理工作，如删除群组相关数据
         * 3. Mock 该类可以验证机器人被移除后的正确处理流程
         * 4. 这种方式支持测试各种移除场景，如主动移除、群解散等
         */
        $event = $this->createMock(GroupMemberEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(GroupMemberEvent::class)
            )
        ;

        $this->handler->handleBotRemoved($event);
    }

    public function testHandleGroupCreatedWithError(): void
    {
        /*
         * 使用具体类 GroupEvent 创建 Mock 对象的原因：
         * 1. 测试异常处理需要模拟群组创建过程中的错误情况
         * 2. 通过 mock 可以设置特定的事件数据，触发异常分支
         * 3. 这种测试确保系统在遇到错误时能正确处理和记录
         * 4. 异常处理是系统健壮性的重要部分，mock 使得这类测试更加可控
         */
        $event = $this->createMock(GroupEvent::class);
        $event->method('getChatId')->willReturn('oc_123456');
        $event->method('getOperatorId')->willReturn('user123');
        $event->method('getData')->willReturn(['chat_id' => 'oc_123456']);

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->willThrowException(new \Exception('API Error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '处理群组创建事件失败',
                self::arrayHasKey('error')
            )
        ;

        $this->handler->handleGroupCreated($event);
    }

    protected function onSetUp(): void
    {
        // 初始化Mock对象
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->messageService = $this->createMock(MessageService::class);
        $this->groupService = $this->createMock(GroupService::class);


        // 直接构造被测服务，避免容器内 EventDispatcher 被 Traceable 装饰导致的 addListener 类型约束问题
        $this->handler = new GroupEventHandler(
            $this->logger,
            $this->eventDispatcher,
            $this->messageService,
            $this->groupService,
        );
    }
}
