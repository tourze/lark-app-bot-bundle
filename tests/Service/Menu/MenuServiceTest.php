<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\Menu\MenuConfig;
use Tourze\LarkAppBotBundle\Service\Menu\MenuService;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 单元测试：MenuService.
 *
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（LarkClient、MessageService）
 * 这些具体类被 Mock 是为了隔离测试环境，避免对HTTP客户端和消息服务的依赖
 * 测试重点在于验证 MenuService 的菜单处理逻辑，而不是依赖服务的具体实现
 */
#[CoversClass(MenuService::class)]
#[RunTestsInSeparateProcesses]
final class MenuServiceTest extends AbstractIntegrationTestCase
{
    private MenuService $menuService;

    private MockObject&LarkClient $client;

    private MockObject&LoggerInterface $logger;

    private MockObject&AdapterInterface $cache;

    private MockObject&MessageService $messageService;

    public function testUpdateMenu(): void
    {
        $config = new MenuConfig();
        $config->addTopLevelMenu('测试菜单', 'test');

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'PATCH',
                '/open-apis/application/v6/applications/app_menu',
                ['json' => $config->toArray()]
            )
            ->willReturn($response)
        ;

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('lark_bot_menu_current')
        ;

        $result = $this->menuService->updateMenu($config);
        $this->assertTrue($result);
    }

    public function testUpdateMenuApiException(): void
    {
        $config = new MenuConfig();
        $config->addTopLevelMenu('测试菜单', 'test');

        $exception = new GenericApiException('API error', 500);
        $this->client->expects($this->once())
            ->method('request')
            ->willThrowException($exception)
        ;

        $this->expectException(ApiException::class);
        $this->menuService->updateMenu($config);
    }

    public function testDeleteMenu(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'PATCH',
                '/open-apis/application/v6/applications/app_menu',
                self::callback(function ($options) {
                    $data = $options['json'];

                    return isset($data['menu']['list']) && [] === $data['menu']['list'];
                })
            )
            ->willReturn($response)
        ;

        $result = $this->menuService->deleteMenu();
        $this->assertTrue($result);
    }

    public function testRegisterHandler(): void
    {
        $handler = function (MenuEvent $event): void {
            // 处理逻辑
        };

        $this->menuService->registerHandler('test_menu', $handler);
        $handlers = $this->menuService->getHandlers();

        $this->assertIsArray($handlers);
        $this->assertArrayHasKey('test_menu', $handlers);
        $this->assertSame($handler, $handlers['test_menu']);
    }

    public function testRegisterHandlers(): void
    {
        $handlers = [
            'menu1' => [
                'handler' => function (): void {},
                'permission' => MenuService::PERMISSION_USER,
                'permission_data' => ['users' => ['user1', 'user2']],
            ],
            'menu2' => [
                'handler' => function (): void {},
            ],
        ];

        $this->menuService->registerHandlers($handlers);
        $registeredHandlers = $this->menuService->getHandlers();

        $this->assertIsArray($registeredHandlers);
        $this->assertCount(2, $registeredHandlers);
        $this->assertArrayHasKey('menu1', $registeredHandlers);
        $this->assertArrayHasKey('menu2', $registeredHandlers);
    }

    public function testRemoveHandler(): void
    {
        $this->menuService->registerHandler('test_menu', function (): void {});
        $this->assertTrue($this->menuService->removeHandler('test_menu'));
        $this->assertFalse($this->menuService->removeHandler('non_existent'));

        $handlers = $this->menuService->getHandlers();
        $this->assertArrayNotHasKey('test_menu', $handlers);
    }

    public function testHandleMenuEvent(): void
    {
        $called = false;
        $handler = function (MenuEvent $event) use (&$called): void {
            $called = true;
        };

        $this->menuService->registerHandler('test_menu', $handler);

        /*
         * 使用具体类 MenuEvent 而不是接口的原因：
         * 1) MenuEvent 是本包中的事件类，封装了菜单点击事件的具体数据结构
         * 2) 在单元测试中Mock MenuEvent 是合理的，因为我们需要控制事件的返回值来测试处理逻辑
         * 3) 替代方案：可以创建EventInterface，但MenuEvent已经足够简单且职责明确，直接Mock更实用
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

        $this->menuService->handleMenuEvent($event);
        $this->assertTrue($called);
    }

    public function testHandleMenuEventWithoutHandler(): void
    {
        /*
         * 使用具体类 MenuEvent 而不是接口的原因：
         * 1) MenuEvent 是菜单事件的具体实现，包含了菜单操作的所有必要信息
         * 2) Mock 该类用于测试无处理器情况下的默认行为，这是合理的测试场景
         * 3) 替代方案：虽然可以定义接口，但MenuEvent类已经很稳定，Mock具体类不会带来维护问题
         */
        $event = $this->createMock(MenuEvent::class);
        $event->expects($this->once())
            ->method('getEventKey')
            ->willReturn('unknown_menu')
        ;
        $event->expects($this->once())
            ->method('getOperatorOpenId')
            ->willReturn('user123')
        ;

        $this->messageService->expects($this->once())
            ->method('sendText')
            ->with(
                'user123',
                '抱歉，菜单功能 "unknown_menu" 暂未实现。',
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            )
        ;

        $this->menuService->handleMenuEvent($event);
    }

    public function testHandleMenuEventWithPermissionDenied(): void
    {
        $this->menuService->registerHandler(
            'restricted_menu',
            function (): void {},
            MenuService::PERMISSION_USER,
            ['users' => ['allowed_user']]
        );

        /*
         * 使用具体类 MenuEvent 而不是接口的原因：
         * 1) MenuEvent 承载菜单权限验证所需的用户信息，Mock该类可以控制权限测试数据
         * 2) 在权限验证测试中Mock MenuEvent是必要的，以便模拟不同用户的操作场景
         * 3) 替代方案：定义权限接口是可能的，但MenuEvent已经封装了所有权限相关数据，直接Mock更直接
         */
        $event = $this->createMock(MenuEvent::class);
        $event->expects($this->once())
            ->method('getEventKey')
            ->willReturn('restricted_menu')
        ;
        $event->expects($this->once())
            ->method('getOperatorOpenId')
            ->willReturn('denied_user')
        ;

        $this->messageService->expects($this->once())
            ->method('sendText')
            ->with(
                'denied_user',
                '抱歉，您没有权限使用此功能。',
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            )
        ;

        $this->menuService->handleMenuEvent($event);
    }

    public function testHandleMenuEventWithException(): void
    {
        $handler = function (): void {
            throw new ConfigurationException('Handler error');
        };

        $this->menuService->registerHandler('error_menu', $handler);

        /*
         * 使用具体类 MenuEvent 而不是接口的原因：
         * 1) MenuEvent 是异常处理测试的载体，需要Mock来模拟触发异常的事件数据
         * 2) 在异常处理测试中Mock MenuEvent可以确保测试的可控性和可重复性
         * 3) 替代方案：可以使用真实的MenuEvent对象，但Mock提供了更好的测试隔离性
         */
        $event = $this->createMock(MenuEvent::class);
        $event->expects($this->once())
            ->method('getEventKey')
            ->willReturn('error_menu')
        ;
        $event->expects($this->once())
            ->method('getOperatorOpenId')
            ->willReturn('user123')
        ;

        $this->messageService->expects($this->once())
            ->method('sendText')
            ->with(
                'user123',
                '抱歉，处理您的请求时发生错误，请稍后重试。',
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            )
        ;

        $this->menuService->handleMenuEvent($event);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象用于测试隔离
        $this->client = $this->createMock(LarkClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(AdapterInterface::class);
        $this->messageService = $this->createMock(MessageService::class);

        // 直接构造被测服务，确保依赖均为 Mock
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->menuService = new MenuService(
            $this->client,
            $this->logger,
            $this->cache,
            $this->messageService,
        );
    }
}
