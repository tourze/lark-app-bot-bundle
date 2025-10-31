<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Tourze\LarkAppBotBundle\Exception\ApiException;

/**
 * 用户数据同步服务接口.
 *
 * 定义用户数据的同步、更新和一致性维护功能
 */
interface UserSyncServiceInterface
{
    /**
     * 同步单个用户数据.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param bool   $force      是否强制同步（忽略同步间隔）
     *
     * @return array<mixed> 同步后的用户数据
     * @throws ApiException
     */
    public function syncUser(string $userId, string $userIdType = 'open_id', bool $force = false): array;

    /**
     * 批量同步用户数据.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     * @param bool     $force      是否强制同步
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} 同步结果统计
     * @throws ApiException
     */
    public function batchSyncUsers(array $userIds, string $userIdType = 'open_id', bool $force = false): array;

    /**
     * 同步部门用户数据.
     *
     * @param string $departmentId 部门ID
     * @param bool   $recursive    是否递归同步子部门
     * @param bool   $force        是否强制同步
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} 同步结果统计
     * @throws ApiException
     */
    public function syncDepartmentUsers(
        string $departmentId,
        bool $recursive = false,
        bool $force = false,
    ): array;

    /**
     * 清除同步历史记录.
     */
    public function clearSyncHistory(): void;
}
