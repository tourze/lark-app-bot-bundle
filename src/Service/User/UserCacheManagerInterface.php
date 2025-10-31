<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户缓存管理器接口.
 *
 * 定义统一的用户数据缓存策略和管理功能
 */
interface UserCacheManagerInterface
{
    /**
     * 缓存用户信息.
     *
     * @param string               $userId     用户ID
     * @param string               $userIdType 用户ID类型
     * @param array<string, mixed> $userData   用户数据
     * @param string               $dataType   数据类型
     */
    public function cacheUser(
        string $userId,
        string $userIdType,
        array $userData,
        string $dataType = 'user_info',
    ): void;

    /**
     * 获取缓存的用户信息.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param string $dataType   数据类型
     *
     * @return array<string, mixed>|null 用户数据，未找到时返回null
     */
    public function getCachedUser(
        string $userId,
        string $userIdType,
        string $dataType = 'user_info',
    ): ?array;

    /**
     * 批量缓存用户信息.
     *
     * @param array<string, mixed> $usersData  用户数据数组，键为用户ID，值为用户数据
     * @param string               $userIdType 用户ID类型
     * @param string               $dataType   数据类型
     */
    public function batchCacheUsers(
        array $usersData,
        string $userIdType,
        string $dataType = 'user_info',
    ): void;

    /**
     * 批量获取缓存的用户信息.
     *
     * @param array<string> $userIds    用户ID列表
     * @param string        $userIdType 用户ID类型
     * @param string        $dataType   数据类型
     *
     * @return array<string, mixed> 用户数据数组，键为用户ID，值为用户数据
     */
    public function batchGetCachedUsers(
        array $userIds,
        string $userIdType,
        string $dataType = 'user_info',
    ): array;

    /**
     * 缓存搜索结果.
     *
     * @param string               $searchKey 搜索关键字
     * @param array<string, mixed> $results   搜索结果
     * @param array<string, mixed> $params    搜索参数
     */
    public function cacheSearchResults(string $searchKey, array $results, array $params = []): void;

    /**
     * 获取缓存的搜索结果.
     *
     * @param string               $searchKey 搜索关键字
     * @param array<string, mixed> $params    搜索参数
     *
     * @return array<string, mixed>|null 搜索结果，未找到时返回null
     */
    public function getCachedSearchResults(string $searchKey, array $params = []): ?array;

    /**
     * 使用户缓存失效.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     */
    public function invalidateUser(string $userId, string $userIdType): void;

    /**
     * 批量使用户缓存失效.
     *
     * @param array<string> $userIds    用户ID列表
     * @param string        $userIdType 用户ID类型
     */
    public function batchInvalidateUsers(array $userIds, string $userIdType): void;

    /**
     * 清除所有缓存.
     */
    public function clearAllCache(): void;

    /**
     * 设置TTL配置.
     *
     * @param string $dataType 数据类型
     * @param int    $ttl      TTL时间（秒）
     */
    public function setTtlConfig(string $dataType, int $ttl): void;

    /**
     * 获取缓存统计信息.
     *
     * @return array<string, mixed> 缓存统计信息
     */
    public function getCacheStats(): array;

    /**
     * 预热缓存.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     * @param callable $dataLoader 数据加载器
     */
    public function warmupCache(array $userIds, string $userIdType, callable $dataLoader): void;

    /**
     * 预热用户缓存.
     *
     * @param array<string> $userIds    用户ID列表
     * @param string        $userIdType 用户ID类型
     */
    public function warmupUsers(array $userIds, string $userIdType = 'open_id'): void;

    /**
     * 使搜索缓存失效.
     *
     * @param array<string, mixed> $params 搜索参数
     */
    public function invalidateSearchCache(array $params = []): void;
}
