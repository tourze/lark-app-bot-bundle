<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户权限管理服务.
 *
 * 负责处理用户权限检查和管理
 */
#[Autoconfigure(public: true)]
final class UserPermissionService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserDataService $userDataService,
        private readonly UserCacheService $cacheService,
    ) {
    }

    /**
     * 获取用户的权限列表.
     *
     * @return string[]
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserPermissions(string $userId, string $userIdType = 'open_id'): array
    {
        $user = $this->getUserForPermissionCheck($userId, $userIdType);

        // 如果是租户管理员，返回特殊标识
        if ($this->isTenantManager($user)) {
            return ['*']; // 表示拥有所有权限
        }

        return $this->extractPermissionsFromUser($user);
    }

    /**
     * 检查用户是否是租户管理员.
     *
     * @param array<string, mixed> $user
     */
    public function isTenantManager(array $user): bool
    {
        return $user['is_tenant_manager'] ?? false;
    }

    /**
     * 检查用户是否有任意一个权限.
     *
     * @param string[] $permissions
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function hasAnyPermission(string $userId, array $permissions, string $userIdType = 'open_id'): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($userId, $permission, $userIdType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查用户是否有特定权限.
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function hasPermission(string $userId, string $permission, string $userIdType = 'open_id'): bool
    {
        // 验证用户ID类型
        $this->userDataService->validateUserIdType($userIdType);

        // 获取用户信息
        $user = $this->getUserForPermissionCheck($userId, $userIdType);

        // 检查是否是租户管理员
        if ($this->isTenantManager($user)) {
            $this->logPermissionResult($userId, $permission, true, '租户管理员');

            return true;
        }

        // 检查自定义权限
        if ($this->hasCustomPermission($user, $permission)) {
            $this->logPermissionResult($userId, $permission, true, '自定义权限');

            return true;
        }

        $this->logPermissionResult($userId, $permission, false, '无权限');

        return false;
    }

    /**
     * 检查用户是否拥有所有权限.
     *
     * @param string[] $permissions
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function hasAllPermissions(string $userId, array $permissions, string $userIdType = 'open_id'): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($userId, $permission, $userIdType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取用于权限检查的用户信息.
     *
     * @return array<string, mixed>
     * @throws ApiException
     * @throws ValidationException
     */
    private function getUserForPermissionCheck(string $userId, string $userIdType): array
    {
        // 首先尝试从缓存获取
        $user = $this->cacheService->getUser($userId, $userIdType);

        if (null === $user) {
            // 从API获取用户信息，只获取权限相关字段
            $user = $this->userDataService->fetchUser($userId, $userIdType);
            $user = $this->userDataService->filterFields($user, ['is_tenant_manager', 'custom_attrs']);

            // 缓存用户信息
            $this->cacheService->cacheUser($userId, $userIdType, $user);
        }

        return $user;
    }

    /**
     * 从用户信息中提取权限列表.
     *
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function extractPermissionsFromUser(array $user): array
    {
        $permissions = [];
        $customAttrs = $user['custom_attrs'] ?? [];
        if (!is_iterable($customAttrs)) {
            $customAttrs = [];
        }

        foreach ($customAttrs as $attr) {
            if (is_array($attr) && 'permissions' === ($attr['key'] ?? '') && \is_array($attr['value'] ?? null)) {
                /** @var array<mixed> $attrValue */
                $attrValue = $attr['value'];
                $permissions = array_merge($permissions, $attrValue);
            }
        }

        return array_unique($permissions);
    }

    /**
     * 记录权限检查结果.
     */
    private function logPermissionResult(string $userId, string $permission, bool $hasPermission, string $reason): void
    {
        $this->logger->debug('权限检查结果', [
            'user_id' => $userId,
            'permission' => $permission,
            'has_permission' => $hasPermission,
            'reason' => $reason,
        ]);
    }

    /**
     * 检查用户是否拥有自定义权限.
     *
     * @param array<string, mixed> $user
     */
    private function hasCustomPermission(array $user, string $permission): bool
    {
        $customAttrs = $user['custom_attrs'] ?? [];
        if (!is_iterable($customAttrs)) {
            $customAttrs = [];
        }
        foreach ($customAttrs as $attr) {
            if (is_array($attr) && 'permissions' === ($attr['key'] ?? '') && \is_array($attr['value'] ?? null)) {
                $permissions = $attr['value'];
                if (\in_array($permission, $permissions, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
