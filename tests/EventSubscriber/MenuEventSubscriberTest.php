<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\LarkAppBotBundle\EventSubscriber\MenuEventSubscriber;
use Tourze\LarkAppBotBundle\Service\Menu\MenuService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * 菜单事件订阅器测试.
 *
 * @internal
 */
#[CoversClass(MenuEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class MenuEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private MenuEventSubscriber $listener;

    private MenuService&MockObject $menuService;

    public function testGetSubscribedEvents(): void
    {
        $events = MenuEventSubscriber::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey(MenuEvent::class, $events);
        $this->assertSame(['onMenuEvent', 10], $events[MenuEvent::class]);
    }

    public function testOnMenuEvent(): void
    {
        /*
         * 使用具体类 MenuEvent 创建 Mock 对象的原因：
         * 1. MenuEvent 是飞书菜单事件的数据载体，包含菜单键、操作者、上下文等信息
         * 2. 该类封装了用户点击菜单项后的所有相关数据
         * 3. 测试需要验证监听器是否正确处理菜单事件并调用相应的服务
         * 4. Mock 该类可以灵活设置不同的菜单键和操作者，测试多种场景
         */
        $event = $this->createMock(MenuEvent::class);
        $event->expects($this->once())
            ->method('getEventKey')
            ->willReturn('test_menu')
        ;
        $event->expects($this->once())
            ->method('getOperatorOpenId')
            ->willReturn('user123')
        ;
        $event->expects($this->once())
            ->method('getEventId')
            ->willReturn('event123')
        ;

        // Logger会执行真实的日志记录，我们主要测试业务逻辑

        $this->menuService->expects($this->once())
            ->method('handleMenuEvent')
            ->with($event)
        ;

        $this->listener->onMenuEvent($event);
    }

    public function testOnMenuEventWithException(): void
    {
        /*
         * 使用具体类 MenuEvent 创建 Mock 对象的原因：
         * 1. 测试异常处理场景需要模拟完整的菜单事件数据
         * 2. MenuEvent 提供了 getEventKey()、getOperatorOpenId()、getEventId() 等多个方法
         * 3. 异常处理时需要记录详细的事件信息用于调试
         * 4. Mock 该类可以精确控制每个方法的调用次数和返回值
         */
        $event = $this->createMock(MenuEvent::class);
        $event->expects($this->exactly(2))
            ->method('getEventKey')
            ->willReturn('error_menu')
        ;
        $event->expects($this->once())
            ->method('getOperatorOpenId')
            ->willReturn('user123')
        ;
        $event->expects($this->once())
            ->method('getEventId')
            ->willReturn('event123')
        ;

        $exception = new \Exception('Test error');

        $this->menuService->expects($this->once())
            ->method('handleMenuEvent')
            ->willThrowException($exception)
        ;

        // Logger会执行真实的错误日志记录，我们主要测试异常处理逻辑

        $this->listener->onMenuEvent($event);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->menuService = $this->createMock(MenuService::class);

        // 设置依赖到容器中
        self::getContainer()->set(MenuService::class, $this->menuService);

        // 从容器中获取服务实例（使用真实的Logger）
        $this->listener = self::getService(MenuEventSubscriber::class);
    }
}
