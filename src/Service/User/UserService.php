<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 飞书用户服务.
 *
 * 提供用户信息获取、权限管理、批量查询等功能的主服务
 */
#[Autoconfigure(public: true)]
class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly UserDataService $dataService,
        private readonly UserCacheService $cacheService,
        private readonly UserSearchService $searchService,
        private readonly UserPermissionService $permissionService,
        private readonly UserRelationService $relationService,
    ) {
    }

    /**
     * 获取单个用户信息.
     *
     * @param string             $userId     用户ID
     * @param string             $userIdType 用户ID类型（open_id, union_id, user_id, email, mobile）
     * @param array<int, string> $fields     需要返回的字段，空数组表示返回所有字段
     *
     * @return array{
     *     user_id?: string,
     *     open_id?: string,
     *     union_id?: string,
     *     name: string,
     *     en_name?: string,
     *     nickname?: string,
     *     email?: string,
     *     mobile?: string,
     *     mobile_visible?: bool,
     *     gender?: int,
     *     avatar?: array,
     *     status?: array,
     *     department_ids?: string[],
     *     leader_user_id?: string,
     *     city?: string,
     *     country?: string,
     *     work_station?: string,
     *     join_time?: int,
     *     is_tenant_manager?: bool,
     *     employee_no?: string,
     *     employee_type?: int,
     *     positions?: array,
     *     orders?: array,
     *     custom_attrs?: array,
     *     enterprise_email?: string,
     *     time_zone?: string,
     *     description?: string,
     *     job_title?: string,
     *     need_send_notification?: bool,
     *     notification_locale?: string,
     *     is_frozen?: bool,
     *     geo?: string,
     *     job_level_name?: string,
     *     display_name_i18n?: array
     * }
     * @throws ApiException
     * @throws ValidationException
     */
    /**
     * @return array<string, mixed>
     */
    public function getUser(string $userId, string $userIdType = 'open_id', array $fields = []): array
    {
        $this->dataService->validateUserIdType($userIdType);

        $cachedUser = $this->cacheService->getUser($userId, $userIdType);
        if (null !== $cachedUser) {
            return $this->dataService->filterFields($cachedUser, $fields);
        }

        $user = $this->dataService->fetchUser($userId, $userIdType);
        $this->cacheService->cacheUser($userId, $userIdType, $user);

        return $this->dataService->filterFields($user, $fields);
    }

    /**
     * 批量获取用户信息.
     *
     * @param array<int, string> $userIds    用户ID列表
     * @param string             $userIdType 用户ID类型
     * @param array<int, string> $fields     需要返回的字段
     *
     * @return array<string, array<string, mixed>> 用户ID => 用户信息的映射
     * @throws ApiException
     * @throws ValidationException
     */
    public function batchGetUsers(array $userIds, string $userIdType = 'open_id', array $fields = []): array
    {
        if ([] === $userIds) {
            return [];
        }

        $this->dataService->validateUserIdType($userIdType);
        $userIds = array_unique($userIds);

        $cacheResult = $this->cacheService->batchGetUsers($userIds, $userIdType);
        $result = $this->mergeCachedResults($cacheResult['cached'] ?? [], $fields);

        $uncached = $cacheResult['uncached'] ?? [];
        if ([] !== $uncached) {
            $result = $this->fetchAndMergeUncached($result, $uncached, $userIdType, $fields);
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $cached
     * @param array<int,string> $fields
     * @return array<string,array<string,mixed>>
     */
    private function mergeCachedResults(iterable $cached, array $fields): array
    {
        $result = [];
        foreach ($cached as $userId => $user) {
            if (is_scalar($userId) && is_array($user)) {
                $result[(string) $userId] = $this->dataService->filterFields($user, $fields);
            }
        }

        return $result;
    }

    /**
     * @param array<string,array<string,mixed>> $result
     * @param array<int,string> $uncached
     * @param array<int,string> $fields
     */
    /**
     * @param array<string,array<string,mixed>> $result
     * @param array<int,string> $uncached
     * @param array<int,string> $fields
     * @return array<string,array<string,mixed>>
     */
    private function fetchAndMergeUncached(array $result, array $uncached, string $userIdType, array $fields): array
    {
        $fetchedUsers = $this->dataService->batchFetchUsers($uncached, $userIdType);
        $this->cacheService->batchCacheUsers($fetchedUsers, $userIdType);

        foreach ($fetchedUsers as $userId => $user) {
            $result[$userId] = $this->dataService->filterFields($user, $fields);
        }
        return $result;
    }

    /**
     * 搜索用户.
     *
     * @param array<string, mixed> $params 搜索参数
     *
     * @return array<string, mixed> 搜索结果
     * @throws ApiException
     */
    public function searchUsers(array $params = []): array
    {
        return $this->searchService->searchUsers($params);
    }

    /**
     * 获取用户所属部门列表.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array{
     *     items: array<array{
     *         department_id: string,
     *         user_order?: int,
     *         department_order?: int,
     *         is_primary_dept?: bool
     *     }>,
     *     has_more: bool,
     *     page_token?: string
     * }
     * @throws ApiException
     * @throws ValidationException
     */
    /**
     * @return array<string, mixed>
     */
    public function getUserDepartments(string $userId, string $userIdType = 'open_id'): array
    {
        return $this->relationService->getUserDepartments($userId, $userIdType);
    }

    /**
     * 获取用户的直属上级.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<string, mixed>|null 上级用户信息，如果没有上级则返回null
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserLeader(string $userId, string $userIdType = 'open_id'): ?array
    {
        return $this->relationService->getUserLeader($userId, $userIdType);
    }

    /**
     * 获取用户的直接下属列表.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<int, array<string, mixed>> 下属用户列表
     * @throws ApiException
     */
    public function getUserSubordinates(string $userId, string $userIdType = 'open_id'): array
    {
        return $this->relationService->getUserSubordinates($userId, $userIdType);
    }

    /**
     * 检查用户是否有特定权限.
     *
     * @param string $userId     用户ID
     * @param string $permission 权限标识
     * @param string $userIdType 用户ID类型
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function hasPermission(string $userId, string $permission, string $userIdType = 'open_id'): bool
    {
        return $this->permissionService->hasPermission($userId, $permission, $userIdType);
    }

    /**
     * 清除用户缓存.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     */
    public function clearUserCache(string $userId, string $userIdType = 'open_id'): void
    {
        $this->cacheService->clearUserCache($userId, $userIdType);
    }

    /**
     * 清除所有用户缓存.
     */
    public function clearAllUserCache(): void
    {
        $this->cacheService->clearAllCache();
    }

    /**
     * 获取用户信息（getUserInfo别名方法）.
     *
     * @param string             $userId     用户ID
     * @param string             $userIdType 用户ID类型
     * @param array<int, string> $fields     需要返回的字段
     *
     * @return array<string, mixed> 用户信息
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserInfo(string $userId, string $userIdType = 'open_id', array $fields = []): array
    {
        return $this->getUser($userId, $userIdType, $fields);
    }
}
