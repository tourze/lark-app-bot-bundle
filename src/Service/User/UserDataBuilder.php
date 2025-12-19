<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户数据构建器.
 */
#[Autoconfigure(public: true)]
final class UserDataBuilder
{
    private const DATA_VERSION = '1.0';

    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserPermissionExtractor $permissionExtractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     basic_info: array<string, mixed>,
     *     departments: array<array<string, mixed>>,
     *     permissions: array<array<string, mixed>>,
     *     leader: array<string, mixed>|null,
     *     subordinates: array<array<string, mixed>>,
     *     custom_data: array<string, mixed>,
     *     metadata: array{
     *         version: string,
     *         last_sync: int,
     *         sync_status: string,
     *         data_source: string
     *     }
     * }
     */
    public function buildUserData(string $userId, string $userIdType): array
    {
        // 获取基本信息
        $basicInfo = $this->userService->getUser($userId, $userIdType);

        // 获取部门信息
        $departments = $this->buildDepartmentData($userId, $userIdType);

        // 获取权限信息
        $permissions = $this->permissionExtractor->extractPermissions($basicInfo);

        // 获取上级信息
        $leader = $this->buildLeaderData($userId, $userIdType);

        // 获取下属信息
        $subordinates = $this->buildSubordinateData($userId, $userIdType, $basicInfo);

        return [
            'basic_info' => $basicInfo,
            'departments' => $departments,
            'permissions' => $permissions,
            'leader' => $leader,
            'subordinates' => $subordinates,
            'custom_data' => [],
            'metadata' => $this->buildMetadata(),
        ];
    }

    /**
     * @param string[] $userIds
     *
     * @return array<string, array<string, mixed>>
     */
    public function batchBuildUserData(array $userIds, string $userIdType): array
    {
        // 批量获取基本信息
        $basicInfoMap = $this->userService->batchGetUsers($userIds, $userIdType);

        $result = [];
        foreach ($basicInfoMap as $userId => $basicInfo) {
            try {
                $result[$userId] = $this->buildUserDataFromBasicInfo($userId, $userIdType, $basicInfo);
            } catch (\Exception $e) {
                $this->logger->error('构建用户数据失败', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildDepartmentData(string $userId, string $userIdType): array
    {
        $departmentsResult = $this->userService->getUserDepartments($userId, $userIdType);

        return $departmentsResult['items'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildLeaderData(string $userId, string $userIdType): ?array
    {
        return $this->userService->getUserLeader($userId, $userIdType);
    }

    /**
     * @param array<string, mixed> $basicInfo
     *
     * @return array<array<string, mixed>>
     */
    private function buildSubordinateData(string $userId, string $userIdType, array $basicInfo): array
    {
        if (true !== ($basicInfo['is_tenant_manager'] ?? false)) {
            return [];
        }

        try {
            return $this->userService->getUserSubordinates($userId, $userIdType);
        } catch (\Exception $e) {
            $this->logger->warning('获取用户下属信息失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $basicInfo
     *
     * @return array{
     *     basic_info: array<string, mixed>,
     *     departments: array<array<string, mixed>>,
     *     permissions: array<array<string, mixed>>,
     *     leader: array<string, mixed>|null,
     *     subordinates: array<array<string, mixed>>,
     *     custom_data: array<string, mixed>,
     *     metadata: array{
     *         version: string,
     *         last_sync: int,
     *         sync_status: string,
     *         data_source: string
     *     }
     * }
     */
    private function buildUserDataFromBasicInfo(string $userId, string $userIdType, array $basicInfo): array
    {
        // 获取部门信息
        $departmentsResult = $this->userService->getUserDepartments($userId, $userIdType);
        $departments = $departmentsResult['items'] ?? [];

        // 获取权限信息
        $permissions = $this->permissionExtractor->extractPermissions($basicInfo);

        // 获取上级信息
        $leader = $this->getLeaderFromBasicInfo($basicInfo);

        return [
            'basic_info' => $basicInfo,
            'departments' => $departments,
            'permissions' => $permissions,
            'leader' => $leader,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => $this->buildMetadata(),
        ];
    }

    /**
     * @param array<string, mixed> $basicInfo
     *
     * @return array<string, mixed>|null
     */
    private function getLeaderFromBasicInfo(array $basicInfo): ?array
    {
        if (!isset($basicInfo['leader_user_id']) || '' === $basicInfo['leader_user_id']) {
            return null;
        }

        try {
            return $this->userService->getUser($basicInfo['leader_user_id'], 'user_id');
        } catch (\Exception $e) {
            $this->logger->warning('获取用户上级信息失败', [
                'leader_id' => $basicInfo['leader_user_id'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{
     *     version: string,
     *     last_sync: int,
     *     sync_status: string,
     *     data_source: string
     * }
     */
    private function buildMetadata(): array
    {
        return [
            'version' => self::DATA_VERSION,
            'last_sync' => time(),
            'sync_status' => 'synced',
            'data_source' => 'api',
        ];
    }
}
