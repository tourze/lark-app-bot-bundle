<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 飞书用户服务接口.
 *
 * 定义用户信息获取、权限管理、批量查询等功能的接口
 */
interface UserServiceInterface
{
    /**
     * 获取单个用户信息.
     *
     * @param string             $userId     用户ID
     * @param string             $userIdType 用户ID类型（open_id, union_id, user_id, email, mobile）
     * @param array<int, string> $fields     需要返回的字段，空数组表示返回所有字段
     *
     * @return array<string, mixed> 用户信息数组
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function getUser(string $userId, string $userIdType = 'open_id', array $fields = []): array;

    /**
     * 批量获取用户信息.
     *
     * @param array<int, string> $userIds    用户ID列表
     * @param string             $userIdType 用户ID类型
     * @param array<int, string> $fields     需要返回的字段
     *
     * @return array<string, array<string, mixed>> 用户信息数组，键为用户ID，值为用户信息
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function batchGetUsers(array $userIds, string $userIdType = 'open_id', array $fields = []): array;

    /**
     * 搜索用户.
     *
     * @param array<string, mixed> $params 搜索参数
     *
     * @return array<string, mixed> 搜索结果
     * @throws ApiException 当API调用失败时
     */
    public function searchUsers(array $params = []): array;

    /**
     * 获取用户部门信息.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<string, mixed> 用户部门信息
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function getUserDepartments(string $userId, string $userIdType = 'open_id'): array;

    /**
     * 获取用户上级.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<string, mixed>|null 上级用户信息，无上级时返回null
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function getUserLeader(string $userId, string $userIdType = 'open_id'): ?array;

    /**
     * 获取用户下属.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<int, array<string, mixed>> 下属用户信息列表
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function getUserSubordinates(string $userId, string $userIdType = 'open_id'): array;

    /**
     * 检查用户是否有指定权限.
     *
     * @param string $userId     用户ID
     * @param string $permission 权限名称
     * @param string $userIdType 用户ID类型
     *
     * @return bool 是否有权限
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function hasPermission(string $userId, string $permission, string $userIdType = 'open_id'): bool;

    /**
     * 清除用户缓存.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     */
    public function clearUserCache(string $userId, string $userIdType = 'open_id'): void;

    /**
     * 清除所有用户缓存.
     */
    public function clearAllUserCache(): void;

    /**
     * 获取用户信息（兼容性方法）.
     *
     * @param string             $userId     用户ID
     * @param string             $userIdType 用户ID类型
     * @param array<int, string> $fields     需要返回的字段
     *
     * @return array<string, mixed> 用户信息数组
     * @throws ValidationException 当用户ID类型无效时
     * @throws ApiException        当API调用失败时
     */
    public function getUserInfo(string $userId, string $userIdType = 'open_id', array $fields = []): array;
}
