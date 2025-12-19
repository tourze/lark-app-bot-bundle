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

    private MockObject $client;

    private MockObject $logger;

    private MockObject $cache;

    private MockObject $messageService;

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
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象并提供测试数据来验证处理逻辑
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
            ['event_id' => 'test_event_id']
        );

        $this->menuService->handleMenuEvent($event);
        $this->assertTrue($called);
    }

    public function testHandleMenuEventWithoutHandler(): void
    {
        /*
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象来测试无处理器情况下的默认行为
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new MenuEvent(
            MenuEvent::EVENT_TYPE,
            [
                'event_key' => 'unknown_menu',
                'operator' => [
                    'operator_id' => ['open_id' => 'user123'],
                    'operator_type' => 'user',
                ],
                'timestamp' => time(),
            ],
            ['event_id' => 'test_event_id']
        );

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
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象来模拟权限被拒绝的用户操作场景
         * 3) 这种方式更接近真实场景，测试可靠性更高
         */
        $event = new MenuEvent(
            MenuEvent::EVENT_TYPE,
            [
                'event_key' => 'restricted_menu',
                'operator' => [
                    'operator_id' => ['open_id' => 'denied_user'],
                    'operator_type' => 'user',
                ],
                'timestamp' => time(),
            ],
            ['event_id' => 'test_event_id']
        );

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
         * 使用真实 MenuEvent 对象而不是 Mock：
         * 1) MenuEvent 是 final 类，无法被 Mock
         * 2) 通过构造真实的 MenuEvent 对象来模拟触发异常的事件数据
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
            ['event_id' => 'test_event_id']
        );

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

        // 将 Mock 服务注入到容器中
        self::getContainer()->set(LarkClient::class, $this->client);
        self::getContainer()->set('logger', $this->logger);
        self::getContainer()->set('lark_app_bot.cache', $this->cache);
        self::getContainer()->set(MessageService::class, $this->messageService);

        // 从容器获取被测试的服务
        $this->menuService = self::getService(MenuService::class);
    }
}
