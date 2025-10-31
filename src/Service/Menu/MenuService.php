<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Menu;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 菜单管理服务
 * 负责机器人菜单的配置、更新、事件处理等功能.
 */
#[Autoconfigure(public: true)]
class MenuService
{
    /**
     * 菜单权限类型.
     */
    public const PERMISSION_ALL = 'all';
    public const PERMISSION_ROLE = 'role';
    public const PERMISSION_USER = 'user';
    /**
     * 缓存键前缀
     */
    private const CACHE_KEY_PREFIX = 'lark_bot_menu_';

    private LarkClient $client;

    private LoggerInterface $logger;

    private AdapterInterface $cache;

    private MessageService $messageService;

    /**
     * @var array<string, callable>
     */
    private array $menuHandlers = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $menuPermissions = [];

    public function __construct(
        LarkClient $client,
        LoggerInterface $logger,
        AdapterInterface $cache,
        MessageService $messageService,
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->messageService = $messageService;
    }

    /**
     * 创建或更新机器人菜单.
     *
     * @param MenuConfig $config     菜单配置
     * @param bool       $clearCache 是否清除缓存
     *
     * @return bool 是否成功
     * @throws ApiException
     */
    public function updateMenu(MenuConfig $config, bool $clearCache = true): bool
    {
        try {
            $this->client->request(
                'PATCH',
                '/open-apis/application/v6/applications/app_menu',
                [
                    'json' => $config->toArray(),
                ]
            );

            if ($clearCache) {
                $this->clearMenuCache();
            }

            $this->logger->info('菜单更新成功', [
                'menu_count' => $config->getMenuCount(),
            ]);

            return true;
        } catch (ApiException $e) {
            $this->logger->error('菜单更新失败', [
                'error' => $e->getMessage(),
                'config' => $config->toArray(),
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('菜单更新失败：认证错误', [
                'error' => $e->getMessage(),
                'config' => $config->toArray(),
            ]);
            throw new GenericApiException('菜单更新失败：认证错误 - ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 获取当前菜单配置.
     *
     * @param bool $useCache 是否使用缓存
     *
     * @throws ApiException
     */
    public function getMenu(bool $useCache = true): ?MenuConfig
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'current';

        if ($useCache) {
            $cachedConfig = $this->getCachedMenu($cacheKey);
            if (null !== $cachedConfig) {
                return $cachedConfig;
            }
        }

        return $this->fetchMenuFromApi($cacheKey);
    }

    /**
     * 删除菜单.
     *
     * @throws ApiException
     */
    public function deleteMenu(): bool
    {
        try {
            $emptyConfig = new MenuConfig();

            return $this->updateMenu($emptyConfig);
        } catch (ApiException $e) {
            $this->logger->error('删除菜单失败', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 注册菜单处理器.
     *
     * @param string               $eventKey       菜单值
     * @param callable             $handler        处理器回调
     * @param string               $permission     权限类型
     * @param array<string, mixed> $permissionData 权限数据
     */
    public function registerHandler(
        string $eventKey,
        callable $handler,
        string $permission = self::PERMISSION_ALL,
        array $permissionData = [],
    ): self {
        $this->menuHandlers[$eventKey] = $handler;
        $this->menuPermissions[$eventKey] = [
            'type' => $permission,
            'data' => $permissionData,
        ];

        $this->logger->debug('注册菜单处理器', [
            'event_key' => $eventKey,
            'permission' => $permission,
        ]);

        return $this;
    }

    /**
     * 处理菜单事件.
     */
    public function handleMenuEvent(MenuEvent $event): void
    {
        $eventKey = $event->getEventKey();
        $userId = $event->getOperatorOpenId();

        $this->logger->info('处理菜单事件', [
            'event_key' => $eventKey,
            'user_id' => $userId,
        ]);

        // 检查权限
        if (!$this->checkPermission($eventKey, $userId)) {
            $this->logger->warning('用户无权访问菜单', [
                'event_key' => $eventKey,
                'user_id' => $userId,
            ]);

            $this->sendPermissionDeniedMessage($userId);

            return;
        }

        // 查找处理器
        if (!isset($this->menuHandlers[$eventKey])) {
            $this->logger->warning('未找到菜单处理器', [
                'event_key' => $eventKey,
            ]);

            $this->sendUnknownMenuMessage($userId, $eventKey);

            return;
        }

        // 执行处理器
        try {
            $handler = $this->menuHandlers[$eventKey];
            $handler($event);
        } catch (\Exception $e) {
            $this->logger->error('菜单处理器执行失败', [
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->sendErrorMessage($userId);
        }
    }

    /**
     * 获取所有注册的菜单处理器.
     *
     * @return array<string, callable>
     */
    public function getHandlers(): array
    {
        return $this->menuHandlers;
    }

    /**
     * 移除菜单处理器.
     */
    public function removeHandler(string $eventKey): bool
    {
        if (isset($this->menuHandlers[$eventKey])) {
            unset($this->menuHandlers[$eventKey], $this->menuPermissions[$eventKey]);

            return true;
        }

        return false;
    }

    /**
     * 批量注册菜单处理器.
     *
     * @param array<string, array{
     *     handler: callable,
     *     permission?: string,
     *     permission_data?: array<string, mixed>
     * }> $handlers
     */
    public function registerHandlers(array $handlers): self
    {
        foreach ($handlers as $eventKey => $config) {
            $this->registerHandler(
                $eventKey,
                $config['handler'],
                $config['permission'] ?? self::PERMISSION_ALL,
                $config['permission_data'] ?? []
            );
        }

        return $this;
    }

    private function getCachedMenu(string $cacheKey): ?MenuConfig
    {
        $cached = $this->cache->getItem($cacheKey);
        if (!$cached->isHit()) {
            return null;
        }

        $data = $cached->get();
        if (!\is_array($data)) {
            return null;
        }

        try {
            return MenuConfig::fromArray($data);
        } catch (ValidationException $e) {
            $this->logger->warning('缓存的菜单配置无效', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchMenuFromApi(string $cacheKey): ?MenuConfig
    {
        try {
            $response = $this->client->request(
                'GET',
                '/open-apis/application/v6/applications/app_menu'
            );

            $result = $this->parseApiResponse($response);
            if (null === $result) {
                return null;
            }

            $config = MenuConfig::fromArray($result);
            $this->cacheMenuConfig($cacheKey, $result);

            return $config;
        } catch (ApiException $e) {
            $this->logger->error('获取菜单配置失败', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 解析API响应.
     *
     * @param ResponseInterface $response 响应对象
     *
     * @return array<string, mixed>|null
     */
    private function parseApiResponse(ResponseInterface $response): ?array
    {
        $content = $response->getContent();
        $result = json_decode($content, true);

        if (!\is_array($result) || !isset($result['data']['menu'])) {
            return null;
        }

        return $result['data'];
    }

    /**
     * 缓存菜单配置.
     *
     * @param string               $cacheKey 缓存键
     * @param array<string, mixed> $data     菜单数据
     */
    private function cacheMenuConfig(string $cacheKey, array $data): void
    {
        $this->cache->save(
            $this->cache->getItem($cacheKey)
                ->set($data)
                ->expiresAfter(3600)
        );
    }

    /**
     * 检查用户权限.
     */
    private function checkPermission(string $eventKey, string $userId): bool
    {
        if (!isset($this->menuPermissions[$eventKey])) {
            return true; // 没有配置权限，默认允许
        }

        $permission = $this->menuPermissions[$eventKey];
        $type = $permission['type'];
        $data = $permission['data'];

        switch ($type) {
            case self::PERMISSION_ALL:
                return true;
            case self::PERMISSION_USER:
                $allowedUsers = $data['users'] ?? [];

                return \in_array($userId, $allowedUsers, true);
            case self::PERMISSION_ROLE:
                // 这里需要实现角色检查逻辑
                // 可以通过注入用户服务来获取用户角色
                $requiredRoles = $data['roles'] ?? [];

                return $this->checkUserRoles($userId, $requiredRoles);
            default:
                return false;
        }
    }

    /**
     * 检查用户角色（需要根据实际业务实现）.
     *
     * @param array<string> $requiredRoles
     */
    private function checkUserRoles(string $userId, array $requiredRoles): bool
    {
        // TODO: 实现用户角色检查逻辑
        // 这里可以通过用户服务获取用户角色信息
        return true;
    }

    /**
     * 发送权限拒绝消息.
     */
    private function sendPermissionDeniedMessage(string $userId): void
    {
        try {
            $this->messageService->sendText(
                $userId,
                '抱歉，您没有权限使用此功能。',
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            );
        } catch (\Exception $e) {
            $this->logger->error('发送权限拒绝消息失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送未知菜单消息.
     */
    private function sendUnknownMenuMessage(string $userId, string $eventKey): void
    {
        try {
            $this->messageService->sendText(
                $userId,
                \sprintf('抱歉，菜单功能 "%s" 暂未实现。', $eventKey),
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            );
        } catch (\Exception $e) {
            $this->logger->error('发送未知菜单消息失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送错误消息.
     */
    private function sendErrorMessage(string $userId): void
    {
        try {
            $this->messageService->sendText(
                $userId,
                '抱歉，处理您的请求时发生错误，请稍后重试。',
                MessageService::RECEIVE_ID_TYPE_OPEN_ID
            );
        } catch (\Exception $e) {
            $this->logger->error('发送错误消息失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清除菜单缓存.
     */
    private function clearMenuCache(): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'current';
        $this->cache->deleteItem($cacheKey);
    }
}
