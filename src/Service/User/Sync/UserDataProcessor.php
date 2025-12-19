<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManagerInterface;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;

/**
 * 用户数据处理器.
 *
 * 负责用户数据的缓存、相关数据提取和权限处理
 */
#[Autoconfigure(public: true)]
final class UserDataProcessor
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserCacheManagerInterface $cacheManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理单个用户数据.
     *
     * @param array<mixed> $userData
     *
     * @return array<mixed>
     */
    public function processUserData(string $userId, string $userIdType, array $userData): array
    {
        // 更新基础用户数据缓存
        $this->cacheManager->cacheUser($userId, $userIdType, $userData);

        // 同步相关数据
        $this->syncRelatedData($userId, $userIdType, $userData);

        return $userData;
    }

    /**
     * 获取缓存的用户数据.
     *
     * @return array<mixed>|null
     */
    public function getCachedUserData(string $userId, string $userIdType): ?array
    {
        return $this->cacheManager->getCachedUser($userId, $userIdType);
    }

    /**
     * 处理已删除的用户.
     */
    public function handleDeletedUser(string $userId, string $userIdType): void
    {
        $this->logger->warning('用户可能已被删除', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ]);

        // 清除缓存
        $this->cacheManager->invalidateUser($userId, $userIdType);
    }

    /**
     * 同步用户的相关数据.
     *
     * @param array<mixed> $userData
     */
    private function syncRelatedData(string $userId, string $userIdType, array $userData): void
    {
        try {
            $this->syncUserDepartments($userId, $userIdType, $userData);
            $this->syncUserPermissions($userId, $userIdType, $userData);
        } catch (\Exception $e) {
            $this->logger->error('同步用户相关数据失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 同步用户部门信息.
     *
     * @param array<mixed> $userData
     */
    private function syncUserDepartments(string $userId, string $userIdType, array $userData): void
    {
        if (!isset($userData['department_ids']) || !\is_array($userData['department_ids']) || [] === $userData['department_ids']) {
            return;
        }

        $departments = $this->userService->getUserDepartments($userId, $userIdType);
        $this->cacheManager->cacheUser(
            $userId,
            $userIdType,
            $departments,
            'user_departments'
        );
    }

    /**
     * 同步用户权限信息.
     *
     * @param array<mixed> $userData
     */
    private function syncUserPermissions(string $userId, string $userIdType, array $userData): void
    {
        if (!isset($userData['is_tenant_manager']) && !isset($userData['custom_attrs'])) {
            return;
        }

        $permissions = $this->extractUserPermissions($userData);
        $this->cacheManager->cacheUser(
            $userId,
            $userIdType,
            $permissions,
            'user_permissions'
        );
    }

    /**
     * 提取用户权限信息.
     *
     * @param array<mixed> $userData
     *
     * @return array{
     *     is_tenant_manager: bool,
     *     permissions: string[],
     *     roles: string[]
     * }
     */
    private function extractUserPermissions(array $userData): array
    {
        /** @var string[] $permissions */
        $permissions = [];
        /** @var string[] $roles */
        $roles = [];

        $result = $this->extractTenantManagerPermissions($userData, $permissions, $roles);
        $permissions = $result['permissions'];
        $roles = $result['roles'];

        $result = $this->extractCustomAttributePermissions($userData, $permissions, $roles);
        $permissions = $result['permissions'];
        $roles = $result['roles'];

        return [
            'is_tenant_manager' => $userData['is_tenant_manager'] ?? false,
            'permissions' => array_unique($permissions),
            'roles' => array_unique($roles),
        ];
    }

    /**
     * 提取租户管理员权限.
     *
     * @param array<mixed>  $userData
     * @param array<string> $permissions
     * @param array<string> $roles
     *
     * @return array{permissions: array<string>, roles: array<string>}
     */
    private function extractTenantManagerPermissions(array $userData, array $permissions, array $roles): array
    {
        if (true === ($userData['is_tenant_manager'] ?? false)) {
            $roles[] = 'tenant_manager';
            $permissions[] = '*'; // 所有权限
        }

        return ['permissions' => $permissions, 'roles' => $roles];
    }

    /**
     * 从自定义属性中提取权限.
     *
     * @param array<mixed>  $userData
     * @param array<string> $permissions
     * @param array<string> $roles
     *
     * @return array{permissions: array<string>, roles: array<string>}
     */
    private function extractCustomAttributePermissions(array $userData, array $permissions, array $roles): array
    {
        $customAttrs = $this->getCustomAttributes($userData);
        if ([] === $customAttrs) {
            return ['permissions' => $permissions, 'roles' => $roles];
        }

        foreach ($customAttrs as $attr) {
            $result = $this->processCustomAttribute($attr, $permissions, $roles);
            $permissions = $result['permissions'];
            $roles = $result['roles'];
        }

        return ['permissions' => $permissions, 'roles' => $roles];
    }

    /**
     * 获取自定义属性数组.
     *
     * @param array<mixed> $userData
     *
     * @return array<mixed>
     */
    private function getCustomAttributes(array $userData): array
    {
        $customAttrs = $userData['custom_attrs'] ?? [];

        return \is_array($customAttrs) ? $customAttrs : [];
    }

    /**
     * 处理单个自定义属性.
     *
     * @param array<string> $permissions
     * @param array<string> $roles
     *
     * @return array{permissions: array<string>, roles: array<string>}
     */
    private function processCustomAttribute(mixed $attr, array $permissions, array $roles): array
    {
        if (!\is_array($attr)) {
            return ['permissions' => $permissions, 'roles' => $roles];
        }

        $key = $attr['key'] ?? null;
        $value = $attr['value'] ?? null;

        if (!\is_array($value)) {
            return ['permissions' => $permissions, 'roles' => $roles];
        }

        if ('permissions' === $key && $this->isStringArray($value)) {
            foreach ($value as $permission) {
                \assert(\is_string($permission));
                $permissions[] = $permission;
            }
        } elseif ('roles' === $key && $this->isStringArray($value)) {
            foreach ($value as $role) {
                \assert(\is_string($role));
                $roles[] = $role;
            }
        }

        return ['permissions' => $permissions, 'roles' => $roles];
    }

    /**
     * 检查数组是否为字符串数组.
     *
     * @param array<mixed> $array
     */
    private function isStringArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!\is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
