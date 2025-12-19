<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户关系管理服务.
 *
 * 负责处理用户组织关系（部门、上级、下属）
 */
#[Autoconfigure(public: true)]
final class UserRelationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserDataService $userDataService,
        private readonly UserCacheService $cacheService,
        private readonly UserSearchService $searchService,
    ) {
    }

    /**
     * 获取用户的直接下属列表.
     *
     * @return array<array<string, mixed>>
     * @throws ApiException
     */
    public function getUserSubordinates(string $userId, string $userIdType = 'open_id'): array
    {
        $userId = $this->ensureUserId($userId, $userIdType);
        if (null === $userId) {
            return [];
        }

        $subordinates = $this->searchService->findSubordinates($userId);

        $this->logSubordinatesFound($userId, $subordinates);

        return $subordinates;
    }

    /**
     * 检查用户A是否是用户B的上级.
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function isLeaderOf(string $leaderId, string $subordinateId, string $userIdType = 'open_id'): bool
    {
        $leader = $this->getUserLeader($subordinateId, $userIdType);
        if (null === $leader) {
            return false;
        }

        $leaderIdField = $this->userDataService->getKeyFieldByType($userIdType);

        return ($leader[$leaderIdField] ?? '') === $leaderId;
    }

    /**
     * 获取用户的直属上级.
     *
     * @return array<string, mixed>|null
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserLeader(string $userId, string $userIdType = 'open_id'): ?array
    {
        $user = $this->getUserFromCacheOrApi($userId, $userIdType, ['leader_user_id']);
        $leaderUserId = $user['leader_user_id'] ?? null;

        if (null === $leaderUserId || '' === $leaderUserId) {
            return null;
        }

        // 获取上级的详细信息
        return $this->getUserFromCacheOrApi($leaderUserId, 'user_id');
    }

    /**
     * 获取用户在组织中的层级深度.
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserHierarchyDepth(string $userId, string $userIdType = 'open_id'): int
    {
        $leaderChain = $this->getUserLeaderChain($userId, $userIdType);

        return \count($leaderChain);
    }

    /**
     * 获取用户的所有上级链.
     *
     * @return array<array<string, mixed>> 从直属上级到顶级上级的用户信息数组
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserLeaderChain(string $userId, string $userIdType = 'open_id'): array
    {
        $leaders = [];
        $currentUserId = $userId;
        $currentUserIdType = $userIdType;

        while (true) {
            $leader = $this->getUserLeader($currentUserId, $currentUserIdType);
            if (null === $leader) {
                break;
            }

            $leaders[] = $leader;

            // 防止循环引用
            if (\count($leaders) > 10) {
                $this->logger->warning('检测到可能的上级循环引用', [
                    'original_user_id' => $userId,
                    'current_leader_count' => \count($leaders),
                ]);
                break;
            }

            // 继续向上查找
            $currentUserId = $leader['user_id'] ?? '';
            $currentUserIdType = 'user_id';

            if ('' === $currentUserId) {
                break;
            }
        }

        return $leaders;
    }

    /**
     * 检查两个用户是否在同一部门.
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function areInSameDepartment(string $userId1, string $userId2, string $userIdType = 'open_id'): bool
    {
        $user1Departments = $this->getUserDepartments($userId1, $userIdType);
        $user2Departments = $this->getUserDepartments($userId2, $userIdType);

        $user1DeptIds = array_column($user1Departments['items'], 'department_id');
        $user2DeptIds = array_column($user2Departments['items'], 'department_id');

        return [] !== array_intersect($user1DeptIds, $user2DeptIds);
    }

    /**
     * 获取用户部门信息.
     *
     * @return array<string, mixed>
     * @throws ApiException
     * @throws ValidationException
     */
    public function getUserDepartments(string $userId, string $userIdType = 'open_id'): array
    {
        $this->userDataService->validateUserIdType($userIdType);

        return $this->userDataService->fetchUserDepartments($userId, $userIdType);
    }

    /**
     * 确保获取到user_id.
     *
     * @throws ApiException
     */
    private function ensureUserId(string $userId, string $userIdType): ?string
    {
        if ('user_id' === $userIdType) {
            return $userId;
        }

        $user = $this->getUserFromCacheOrApi($userId, $userIdType, ['user_id']);
        $userId = $user['user_id'] ?? null;

        return (null !== $userId && '' !== $userId) ? $userId : null;
    }

    /**
     * 从缓存或API获取用户信息.
     *
     * @param array<string> $fields
     *
     * @return array<string, mixed>
     */
    private function getUserFromCacheOrApi(string $userId, string $userIdType, array $fields = []): array
    {
        // 首先尝试从缓存获取
        $user = $this->cacheService->getUser($userId, $userIdType);

        if (null === $user) {
            // 从API获取
            $user = $this->userDataService->fetchUser($userId, $userIdType);
            // 缓存完整用户信息
            $this->cacheService->cacheUser($userId, $userIdType, $user);
        }

        // 过滤字段
        return $this->userDataService->filterFields($user, $fields);
    }

    /**
     * 记录下属查找结果.
     *
     * @param array<array<string, mixed>> $subordinates
     */
    private function logSubordinatesFound(string $userId, array $subordinates): void
    {
        $this->logger->info('获取用户下属列表成功', [
            'user_id' => $userId,
            'subordinate_count' => \count($subordinates),
        ]);
    }
}
