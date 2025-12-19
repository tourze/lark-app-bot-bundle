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

    private MockObject $menuService;

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
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象来验证监听器处理逻辑
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new MenuEvent(
            MenuEvent::EVENT_TYPE,
            [
                'event_key' => 'test_menu',
                'operator' => [
                    'operator_id' => ['open_id' => 'user123'],
                    'operator_type' => 'user',
                ],
                'timestamp' => time(),
            ],
            ['event_id' => 'event123']
        );

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
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象来测试异常处理场景
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new MenuEvent(
            MenuEvent::EVENT_TYPE,
            [
                'event_key' => 'error_menu',
                'operator' => [
                    'operator_id' => ['open_id' => 'user123'],
                    'operator_type' => 'user',
                ],
                'timestamp' => time(),
            ],
            ['event_id' => 'event123']
        );

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
