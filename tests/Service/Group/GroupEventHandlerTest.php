<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    private EventDispatcherInterface $eventDispatcher;

    private MockObject $messageService;

    private MockObject $groupService;

    public function testHandleGroupCreated(): void
    {
        /*
         * 使用真实 GroupEvent 对象而不是 Mock：
         * 1) GroupEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupEvent 对象来测试群组创建处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupEvent(
            'im.chat.created_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
            ],
            ['event_id' => 'test_event_id']
        );

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

        // 使用真实的EventDispatcher，不需要Mock期望

        $this->handler->handleGroupCreated($event);
    }

    public function testHandleGroupDisbanded(): void
    {
        /*
         * 使用真实 GroupEvent 对象而不是 Mock：
         * 1) GroupEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupEvent 对象来测试群解散处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupEvent(
            'im.chat.disbanded_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
            ],
            ['event_id' => 'test_event_id']
        );

        // 使用真实的EventDispatcher，不需要Mock期望

        // 验证方法能够正常执行，无异常抛出
        $this->expectNotToPerformAssertions();
        $this->handler->handleGroupDisbanded($event);
    }

    public function testHandleGroupUpdated(): void
    {
        /*
         * 使用真实 GroupEvent 对象而不是 Mock：
         * 1) GroupEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupEvent 对象来测试群更新处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupEvent(
            'im.chat.updated_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
                'i18n_names' => ['zh_cn' => '中文名'],
            ],
            ['event_id' => 'test_event_id']
        );

        // 使用真实的EventDispatcher，不需要Mock期望

        // 验证方法能够正常执行，无异常抛出
        $this->expectNotToPerformAssertions();
        $this->handler->handleGroupUpdated($event);
    }

    public function testHandleMemberJoined(): void
    {
        $users = [
            ['user_id' => 'user1', 'name' => '用户1'],
            ['user_id' => 'user2', 'name' => '用户2']];

        /*
         * 使用真实 GroupMemberEvent 对象而不是 Mock：
         * 1) GroupMemberEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupMemberEvent 对象来测试成员加入处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $formattedUsers = [
            ['tenant_key' => 'tenant1', 'user_id' => 'user1'],
            ['tenant_key' => 'tenant1', 'user_id' => 'user2'],
        ];
        $event = new GroupMemberEvent(
            'im.chat.member.user.added_v1',
            [
                'chat_id' => 'oc_123456',
                'users' => $formattedUsers,
            ],
            ['event_id' => 'test_event_id']
        );

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

        // 使用真实的EventDispatcher，不需要Mock期望

        // 验证方法能够正常执行，无异常抛出
        $this->handler->handleMemberJoined($event);
    }

    public function testHandleMemberLeft(): void
    {
        $users = [
            ['user_id' => 'user1', 'name' => '用户1']];

        /*
         * 使用真实 GroupMemberEvent 对象而不是 Mock：
         * 1) GroupMemberEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupMemberEvent 对象来测试成员离开处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $formattedUsers = [
            ['tenant_key' => 'tenant1', 'user_id' => 'user1'],
        ];
        $event = new GroupMemberEvent(
            'im.chat.member.user.withdrawn_v1',
            [
                'chat_id' => 'oc_123456',
                'users' => $formattedUsers,
            ],
            ['event_id' => 'test_event_id']
        );

        // 使用真实的EventDispatcher，不需要Mock期望

        // 验证方法能够正常执行，无异常抛出
        $this->expectNotToPerformAssertions();
        $this->handler->handleMemberLeft($event);
    }

    public function testHandleBotAdded(): void
    {
        /*
         * 使用真实 GroupMemberEvent 对象而不是 Mock：
         * 1) GroupMemberEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupMemberEvent 对象来测试机器人加入处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupMemberEvent(
            'im.chat.member.bot.added_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
            ],
            ['event_id' => 'test_event_id']
        );

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

        // 使用真实的EventDispatcher，不需要Mock期望

        $this->handler->handleBotAdded($event);
    }

    public function testHandleBotRemoved(): void
    {
        /*
         * 使用真实 GroupMemberEvent 对象而不是 Mock：
         * 1) GroupMemberEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupMemberEvent 对象来测试机器人移除处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupMemberEvent(
            'im.chat.member.bot.deleted_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
            ],
            ['event_id' => 'test_event_id']
        );

        // 使用真实的EventDispatcher，不需要Mock期望

        // 验证方法能够正常执行，无异常抛出
        $this->expectNotToPerformAssertions();
        $this->handler->handleBotRemoved($event);
    }

    public function testHandleGroupCreatedWithError(): void
    {
        /*
         * 使用真实 GroupEvent 对象而不是 Mock：
         * 1) GroupEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 GroupEvent 对象来测试异常处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new GroupEvent(
            'im.chat.created_v1',
            [
                'chat_id' => 'oc_123456',
                'operator_id' => 'user123',
            ],
            ['event_id' => 'test_event_id']
        );

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->willThrowException(new \Exception('API Error'))
        ;

        // 测试异常处理，不关注具体的日志调用
        $this->logger->expects($this->any())
            ->method('info')
        ;

        $this->logger->expects($this->any())
            ->method('error')
        ;

        $this->handler->handleGroupCreated($event);
    }

    protected function onSetUp(): void
    {
        // 初始化Mock对象
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageService = $this->createMock(MessageService::class);
        $this->groupService = $this->createMock(GroupService::class);

        // 使用真实的EventDispatcher，避免Mock的复杂性
        $this->eventDispatcher = new EventDispatcher();

        // 将 Mock 服务注入到容器中
        self::getContainer()->set(LoggerInterface::class, $this->logger);
        self::getContainer()->set(EventDispatcherInterface::class, $this->eventDispatcher);
        self::getContainer()->set(MessageService::class, $this->messageService);
        self::getContainer()->set(GroupService::class, $this->groupService);

        // 从容器获取被测试的服务
        $this->handler = self::getService(GroupEventHandler::class);
    }
}
