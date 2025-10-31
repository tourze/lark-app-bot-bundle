<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\EventHandler;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Event\UserEvent;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManager;
use Tourze\LarkAppBotBundle\Service\User\UserTracker;

/**
 * 用户事件处理器.
 *
 * 处理用户相关的各种事件，包括：
 * - 用户创建、更新、删除
 * - 用户活动跟踪
 * - 用户数据变更
 * - 缓存同步
 */
#[Autoconfigure(public: true)]
class UserEventHandler
{
    public function __construct(
        private readonly UserCacheManager $cacheManager,
        private readonly UserTracker $userTracker,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理用户创建事件.
     */
    public function handleUserCreated(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();

        $this->logger->info('处理用户创建事件', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
        ]);

        // 预热用户缓存
        $openId = $user['open_id'] ?? '';
        if (\is_string($openId) && '' !== $openId) {
            $this->cacheManager->warmupUsers([$openId]);
        }

        // 记录用户活动
        $userId = $user['open_id'] ?? $user['user_id'] ?? '';
        \assert(\is_string($userId));
        $this->userTracker->trackActivity(
            $userId,
            'open_id',
            'user_created',
            [
                'source' => $context['source'] ?? 'unknown',
                'created_at' => time(),
            ]
        );
    }

    /**
     * 处理用户更新事件.
     */
    public function handleUserUpdated(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();
        $changes = $context['changes'] ?? [];
        \assert(\is_array($changes));

        $this->logger->info('处理用户更新事件', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
            'changes' => array_keys($changes),
        ]);

        // 清除旧缓存
        $this->cacheManager->invalidateUser(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id'
        );

        // 如果有关键字段变更，清除相关缓存
        if (isset($changes['department_ids']) || isset($changes['leader_user_id'])) {
            $this->clearDepartmentCaches($user);
        }

        // 记录用户活动
        $this->userTracker->trackActivity(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id',
            'user_updated',
            [
                'changes' => array_keys($changes),
                'updated_at' => time(),
            ]
        );
    }

    /**
     * 处理用户删除事件.
     */
    public function handleUserDeleted(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();

        $this->logUserDeletion($user);
        $this->clearUserCaches($user);
        $this->clearDepartmentCaches($user);
        $this->trackUserDeletion($user, $context);
    }

    /**
     * 处理用户活动事件.
     */
    public function handleUserActivity(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();

        $activityType = $context['activity_type'] ?? 'unknown';
        $activityData = $context['activity_data'] ?? [];

        // 记录活动
        $this->userTracker->trackActivity(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id',
            $activityType,
            $activityData
        );

        // 特殊活动处理
        switch ($activityType) {
            case 'login':
                $this->handleUserLogin($user, $activityData);
                break;
            case 'logout':
                $this->handleUserLogout($user, $activityData);
                break;
            case 'message_sent':
                $this->handleMessageSent($user, $activityData);
                break;
            case 'permission_changed':
                $this->handlePermissionChanged($user, $activityData);
                break;
        }
    }

    /**
     * 处理用户数据加载事件.
     */
    public function handleUserDataLoaded(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();
        $fullData = $context['full_data'] ?? [];

        $this->logger->debug('用户数据已加载', [
            'user_id' => $user['user_id'] ?? '',
            'data_keys' => array_keys($fullData),
        ]);

        // 可以在这里进行数据完整性检查或其他处理
    }

    /**
     * 处理用户数据更新事件.
     */
    public function handleUserDataUpdated(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();
        $customData = $context['custom_data'] ?? [];
        $oldCustomData = $context['old_custom_data'] ?? [];

        $this->logger->info('用户自定义数据已更新', [
            'user_id' => $user['user_id'] ?? '',
            'updated_keys' => array_keys($customData),
            'removed_keys' => array_diff(array_keys($oldCustomData), array_keys($customData)),
        ]);

        // 清除缓存以确保数据一致性
        $this->cacheManager->invalidateUser(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id'
        );
    }

    /**
     * 处理用户数据删除事件.
     */
    public function handleUserDataDeleted(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();

        $this->logger->info('用户数据已删除', [
            'user_id' => $user['user_id'] ?? '',
        ]);

        // 确保所有缓存都被清除
        $this->cacheManager->invalidateUser(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id'
        );
    }

    /**
     * 处理用户数据导入事件.
     */
    public function handleUserDataImported(UserEvent $event): void
    {
        $user = $event->getUser();
        $context = $event->getContext();

        // 安全访问 context 中的 import_data
        assert(array_key_exists('import_data', $context) || !array_key_exists('import_data', $context), 'import_data key must be properly accessed');
        /** @var array<string, mixed> $importData */
        $importData = array_key_exists('import_data', $context) ? $context['import_data'] : [];
        assert(is_array($importData), 'import_data must be an array');

        // 安全访问 user 数据
        $userId = array_key_exists('user_id', $user) ? $user['user_id'] : '';
        assert(is_string($userId), 'user_id must be a string');

        $openId = array_key_exists('open_id', $user) ? $user['open_id'] : '';
        assert(is_string($openId), 'open_id must be a string');

        // 安全访问 importData 中的 export_time
        $importTime = array_key_exists('export_time', $importData) ? $importData['export_time'] : 'unknown';
        assert(is_string($importTime) || is_int($importTime), 'export_time must be string or int');

        $this->logger->info('用户数据已导入', [
            'user_id' => $userId,
            'import_time' => $importTime,
            'data_keys' => array_keys($importData),
        ]);

        // 预热缓存
        if ('' !== $openId) {
            $this->cacheManager->warmupUsers([$openId]);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function logUserDeletion(array $user): void
    {
        $this->logger->info('处理用户删除事件', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function clearUserCaches(array $user): void
    {
        $userIdentifiers = $this->extractUserIdentifiers($user);

        foreach ($userIdentifiers as $identifier) {
            $this->cacheManager->invalidateUser($identifier['id'], $identifier['type']);
        }
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<int, array<string, string>>
     */
    private function extractUserIdentifiers(array $user): array
    {
        $identifierFields = [
            'open_id' => 'open_id',
            'union_id' => 'union_id',
            'user_id' => 'user_id',
            'email' => 'email',
            'mobile' => 'mobile',
        ];

        $identifiers = [];
        foreach ($identifierFields as $field => $type) {
            if (isset($user[$field]) && '' !== $user[$field]) {
                $identifiers[] = ['id' => $user[$field], 'type' => $type];
            }
        }

        return $identifiers;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function clearDepartmentCaches(array $user): void
    {
        $departmentIds = $user['department_ids'] ?? [];
        if (!is_array($departmentIds) || [] === $departmentIds) {
            return;
        }

        foreach ($departmentIds as $deptId) {
            if (is_scalar($deptId)) {
                $this->cacheManager->invalidateSearchCache([
                    'department_id' => $deptId,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $context
     */
    private function trackUserDeletion(array $user, array $context): void
    {
        $userId = $user['open_id'] ?? $user['user_id'] ?? '';

        $this->userTracker->trackActivity(
            $userId,
            'open_id',
            'user_deleted',
            [
                'deleted_at' => time(),
                'reason' => $context['reason'] ?? 'unknown',
            ]
        );
    }

    /**
     * 处理用户登录.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function handleUserLogin(array $user, array $data): void
    {
        $this->logger->info('用户登录', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
            'login_time' => $data['timestamp'] ?? time(),
            'ip' => $data['ip'] ?? 'unknown',
        ]);

        // 可以在这里添加登录相关的业务逻辑
        // 例如：更新最后登录时间、记录登录日志等
    }

    /**
     * 处理用户登出.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function handleUserLogout(array $user, array $data): void
    {
        $this->logger->info('用户登出', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
            'logout_time' => $data['timestamp'] ?? time(),
        ]);

        // 可以在这里添加登出相关的业务逻辑
    }

    /**
     * 处理消息发送.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function handleMessageSent(array $user, array $data): void
    {
        $this->logger->debug('用户发送消息', [
            'user_id' => $user['user_id'] ?? '',
            'message_type' => $data['message_type'] ?? 'unknown',
            'chat_id' => $data['chat_id'] ?? '',
        ]);

        // 可以在这里添加消息统计等逻辑
    }

    /**
     * 处理权限变更.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function handlePermissionChanged(array $user, array $data): void
    {
        $this->logger->info('用户权限变更', [
            'user_id' => $user['user_id'] ?? '',
            'user_name' => $user['name'] ?? '',
            'added_permissions' => $data['added'] ?? [],
            'removed_permissions' => $data['removed'] ?? [],
        ]);

        // 清除用户缓存以确保权限生效
        $this->cacheManager->invalidateUser(
            $user['open_id'] ?? $user['user_id'] ?? '',
            'open_id'
        );
    }
}
