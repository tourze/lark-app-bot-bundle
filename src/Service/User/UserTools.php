<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户工具类 (Facade).
 *
 * 提供用户相关的工具方法，包括：
 * - 用户ID验证和转换
 * - 用户数据格式化
 * - 用户信息提取
 * - 用户权限计算
 *
 * 此类作为向后兼容的 Facade，内部委托给专用的类
 */
class UserTools
{
    private static ?UserValidator $validator = null;

    private static ?UserFormatter $formatter = null;

    private static ?UserDataMasker $dataMasker = null;

    private static ?UserPermissionCalculator $permissionCalculator = null;

    private static ?UserStatusChecker $statusChecker = null;

    private static ?UserComparator $comparator = null;

    /**
     * 验证用户ID类型.
     *
     * @throws ValidationException
     */
    public static function validateUserIdType(string $userIdType): void
    {
        self::getValidator()->validateUserIdType($userIdType);
    }

    /**
     * 从用户信息中提取指定类型的ID.
     *
     * @param array<string, mixed> $user       用户信息
     * @param string               $targetType 目标ID类型
     *
     * @return string|null 用户ID，如果不存在则返回null
     */
    public static function extractUserId(array $user, string $targetType): ?string
    {
        return self::getValidator()->extractUserId($user, $targetType);
    }

    /**
     * 获取用户的所有可用ID.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array<string, string> ID类型 => ID值的映射
     */
    public static function getAllUserIds(array $user): array
    {
        return self::getValidator()->getAllUserIds($user);
    }

    /**
     * 格式化用户显示名称.
     *
     * @param array<string, mixed> $user         用户信息
     * @param string               $locale       语言环境
     * @param bool                 $includeTitle 是否包含职位
     *
     * @return string 格式化后的显示名称
     */
    public static function formatDisplayName(array $user, string $locale = 'zh_CN', bool $includeTitle = false): string
    {
        return self::getFormatter()->formatDisplayName($user, $locale, $includeTitle);
    }

    /**
     * 获取用户头像URL.
     *
     * @param array<string, mixed> $user 用户信息
     * @param string               $size 头像尺寸（72, 240, 640）
     *
     * @return string|null 头像URL
     */
    public static function getAvatarUrl(array $user, string $size = '240'): ?string
    {
        return self::getFormatter()->getAvatarUrl($user, $size);
    }

    /**
     * 检查用户状态.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array{
     *     is_active: bool,
     *     is_frozen: bool,
     *     is_resigned: bool,
     *     status_text: string
     * }
     */
    public static function checkUserStatus(array $user): array
    {
        return self::getStatusChecker()->checkUserStatus($user);
    }

    /**
     * 解析用户权限.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return string[] 权限列表
     */
    public static function parsePermissions(array $user): array
    {
        return self::getPermissionCalculator()->parsePermissions($user);
    }

    /**
     * 计算用户的组织层级.
     *
     * @param array<string, mixed> $user        用户信息
     * @param array<string, mixed> $departments 部门信息列表
     *
     * @return int 组织层级（0表示最高级）
     */
    public static function calculateOrgLevel(array $user, array $departments = []): int
    {
        return self::getPermissionCalculator()->calculateOrgLevel($user, $departments);
    }

    /**
     * 格式化用户联系方式.
     *
     * @param array<string, mixed> $user          用户信息
     * @param bool                 $maskSensitive 是否脱敏敏感信息
     *
     * @return array{
     *     email?: string,
     *     mobile?: string,
     *     enterprise_email?: string
     * }
     */
    public static function formatContactInfo(array $user, bool $maskSensitive = false): array
    {
        return self::getDataMasker()->formatContactInfo($user, $maskSensitive);
    }

    /**
     * 获取用户的主要部门ID.
     *
     * @param array<int, array<string, mixed>> $departments 用户的部门列表
     *
     * @return string|null 主要部门ID
     */
    public static function getPrimaryDepartmentId(array $departments): ?string
    {
        return self::getValidator()->getPrimaryDepartmentId($departments);
    }

    /**
     * 比较两个用户信息的差异.
     *
     * @param array<string, mixed> $oldUser 旧用户信息
     * @param array<string, mixed> $newUser 新用户信息
     *
     * @return array<string, mixed>
     */
    public static function compareUserInfo(array $oldUser, array $newUser): array
    {
        return self::getComparator()->compareUserInfo($oldUser, $newUser);
    }

    /**
     * 生成用户摘要信息.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array<string, mixed> 摘要信息
     */
    public static function generateUserSummary(array $user): array
    {
        return self::getComparator()->generateUserSummary($user);
    }

    /**
     * 根据邮箱获取用户信息（需要传入UserService实例）.
     *
     * @param UserServiceInterface $userService 用户服务实例
     * @param string               $email       用户邮箱
     * @param array<string>        $fields      需要返回的字段
     *
     * @return array<string, mixed>|null 用户信息
     */
    public static function getUserByEmail(UserServiceInterface $userService, string $email, array $fields = []): ?array
    {
        try {
            return $userService->getUser($email, 'email', $fields);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 根据手机号获取用户信息（需要传入UserService实例）.
     *
     * @param UserServiceInterface $userService 用户服务实例
     * @param string               $mobile      用户手机号
     * @param array<string>        $fields      需要返回的字段
     *
     * @return array<string, mixed>|null 用户信息
     */
    public static function getUserByMobile(UserServiceInterface $userService, string $mobile, array $fields = []): ?array
    {
        try {
            return $userService->getUser($mobile, 'mobile', $fields);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取用户所属群组列表（需要传入必要的服务实例）.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<string, mixed> 群组列表
     *
     * @deprecated 此方法尚未实现，飞书API可能没有直接获取用户所有群组的接口
     */
    public static function getUserGroups(string $userId, string $userIdType = 'open_id'): array
    {
        return [];
    }

    private static function getValidator(): UserValidator
    {
        return self::$validator ??= new UserValidator();
    }

    private static function getFormatter(): UserFormatter
    {
        return self::$formatter ??= new UserFormatter();
    }

    private static function getDataMasker(): UserDataMasker
    {
        return self::$dataMasker ??= new UserDataMasker();
    }

    private static function getPermissionCalculator(): UserPermissionCalculator
    {
        return self::$permissionCalculator ??= new UserPermissionCalculator();
    }

    private static function getStatusChecker(): UserStatusChecker
    {
        return self::$statusChecker ??= new UserStatusChecker();
    }

    private static function getComparator(): UserComparator
    {
        return self::$comparator ??= new UserComparator(self::getStatusChecker(), self::getFormatter());
    }
}
