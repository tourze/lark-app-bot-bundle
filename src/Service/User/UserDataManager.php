<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\UserEvent;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户数据管理器.
 *
 * 提供用户数据的统一管理，包括：
 * - 用户数据的增删改查
 * - 用户数据的验证和标准化
 * - 用户数据的版本控制
 * - 用户数据的导入导出
 * - 用户数据的批量操作
 */
#[Autoconfigure(public: true)]
class UserDataManager
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserCacheManagerInterface $cacheManager,
        private readonly UserSyncServiceInterface $syncService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly UserDataCacheManager $dataCacheManager,
        private readonly UserDataBuilder $dataBuilder,
        private readonly UserDataExporter $dataExporter,
        private readonly UserDataImporter $dataImporter,
    ) {
    }

    /**
     * 获取用户完整数据.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param bool   $forceSync  是否强制同步最新数据
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
     * @throws ValidationException
     */
    public function getUserData(string $userId, string $userIdType = 'open_id', bool $forceSync = false): array
    {
        if (!$forceSync) {
            $cachedData = $this->dataCacheManager->get($userId, $userIdType);
            if (null !== $cachedData) {
                // 验证缓存数据的结构
                if (!$this->isValidUserDataStructure($cachedData)) {
                    $this->logger->warning('缓存数据结构无效，将重新加载', [
                        'user_id' => $userId,
                        'user_id_type' => $userIdType,
                    ]);

                    // 缓存数据无效，重新加载
                    return $this->loadAndCacheUserData($userId, $userIdType);
                }

                \assert(isset($cachedData['basic_info'], $cachedData['departments'], $cachedData['permissions'], $cachedData['subordinates'], $cachedData['custom_data'], $cachedData['metadata']));
                \assert(\is_array($cachedData['metadata']) && isset($cachedData['metadata']['version'], $cachedData['metadata']['last_sync'], $cachedData['metadata']['sync_status'], $cachedData['metadata']['data_source']));

                // 确保 leader 字段存在，如果没有则设为 null
                if (!isset($cachedData['leader'])) {
                    $cachedData['leader'] = null;
                }

                /* @var array{basic_info: array<string, mixed>, departments: array<array<string, mixed>>, permissions: array<array<string, mixed>>, leader: array<string, mixed>|null, subordinates: array<array<string, mixed>>, custom_data: array<string, mixed>, metadata: array{version: string, last_sync: int, sync_status: string, data_source: string}} $cachedData */
                return $cachedData;
            }
        }

        return $this->loadAndCacheUserData($userId, $userIdType);
    }

    /**
     * 批量获取用户数据.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     * @param bool     $forceSync  是否强制同步
     *
     * @return array<string, array<mixed>> 用户ID => 用户数据的映射
     */
    public function batchGetUserData(array $userIds, string $userIdType = 'open_id', bool $forceSync = false): array
    {
        $result = [];
        if ($forceSync) {
            $uncachedIds = $userIds;
        } else {
            $batchResult = $this->loadFromCacheBatch($userIds, $userIdType, $result);
            $result = $batchResult['result'];
            $uncachedIds = $batchResult['uncachedIds'];
        }

        $result = $this->loadUncachedUserData($uncachedIds, $userIdType, $result);
        $this->logBatchGetComplete($userIds, $uncachedIds);

        return $result;
    }

    /**
     * 更新用户自定义数据.
     *
     * @param string               $userId     用户ID
     * @param array<string, mixed> $customData 自定义数据
     * @param string               $userIdType 用户ID类型
     *
     * @throws ValidationException
     */
    public function updateUserCustomData(string $userId, array $customData, string $userIdType = 'open_id'): void
    {
        $userData = $this->getUserData($userId, $userIdType);
        $oldCustomData = $userData['custom_data'] ?? [];

        // 合并自定义数据
        $userData['custom_data'] = array_merge($oldCustomData, $customData);
        $userData['metadata']['last_update'] = time();

        // 更新缓存
        $this->dataCacheManager->set($userId, $userIdType, $userData);

        // 触发更新事件
        $this->eventDispatcher->dispatch(new UserEvent(
            UserEvent::USER_DATA_UPDATED,
            $userData['basic_info'],
            [
                'custom_data' => $customData,
                'old_custom_data' => $oldCustomData,
            ]
        ));

        $this->logger->info('更新用户自定义数据成功', [
            'user_id' => $userId,
            'custom_data_keys' => array_keys($customData),
        ]);
    }

    /**
     * 删除用户数据.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     */
    public function deleteUserData(string $userId, string $userIdType = 'open_id'): void
    {
        // 获取用户数据用于事件
        $userData = $this->dataCacheManager->get($userId, $userIdType);

        // 删除缓存
        $this->dataCacheManager->delete($userId, $userIdType);

        // 清除相关缓存
        $this->userService->clearUserCache($userId, $userIdType);
        $this->cacheManager->invalidateUser($userId, $userIdType);

        // 触发删除事件
        if (null !== $userData) {
            $this->eventDispatcher->dispatch(new UserEvent(
                UserEvent::USER_DATA_DELETED,
                $userData['basic_info'] ?? ['user_id' => $userId],
                ['full_data' => $userData]
            ));
        }

        $this->logger->info('删除用户数据成功', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ]);
    }

    /**
     * 导出用户数据.
     *
     * @param string               $userId     用户ID
     * @param string               $userIdType 用户ID类型
     * @param array<string, mixed> $options    导出选项
     *
     * @return array<string, mixed> 导出的数据
     */
    public function exportUserData(string $userId, string $userIdType = 'open_id', array $options = []): array
    {
        $userData = $this->getUserData($userId, $userIdType);

        return $this->dataExporter->export($userId, $userIdType, $userData, $options);
    }

    /**
     * 导入用户数据.
     *
     * @param array<string, mixed> $data 要导入的数据
     *
     * @return string 导入的用户ID
     * @throws ValidationException
     */
    public function importUserData(array $data): string
    {
        $userData = $this->dataImporter->import($data);

        if (!isset($data['user_id']) || !\is_string($data['user_id'])) {
            throw new ValidationException('Missing or invalid user_id in import data');
        }

        $userId = $data['user_id'];
        $userIdType = \is_string($data['user_id_type'] ?? null) ? $data['user_id_type'] : 'open_id';

        // 保存数据
        $this->dataCacheManager->set($userId, $userIdType, $userData);

        // 触发导入事件
        $this->eventDispatcher->dispatch(new UserEvent(
            UserEvent::USER_DATA_IMPORTED,
            $userData['basic_info'],
            ['import_data' => $data]
        ));

        return $userId;
    }

    /**
     * 刷新用户数据.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array<string, mixed> 刷新后的用户数据
     */
    public function refreshUserData(string $userId, string $userIdType = 'open_id'): array
    {
        // 强制同步最新数据
        $this->syncService->syncUser($userId, $userIdType, true);

        // 重新获取数据
        return $this->getUserData($userId, $userIdType, true);
    }

    /**
     * 获取修改过的用户数据.
     *
     * @return array<string, array<string, mixed>> 用户ID => 用户数据的映射
     */
    public function getDirtyUserData(): array
    {
        return $this->dataCacheManager->getDirtyData();
    }

    /**
     * 持久化修改过的数据.
     */
    public function persistDirtyData(): void
    {
        $this->dataCacheManager->persistDirtyData();
    }

    /**
     * 清理内存缓存.
     *
     * @param int $maxAge 最大缓存时间（秒）
     */
    public function cleanMemoryCache(int $maxAge = 3600): void
    {
        $this->dataCacheManager->cleanMemoryCache($maxAge);
    }

    /**
     * 加载并缓存用户数据.
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
    private function loadAndCacheUserData(string $userId, string $userIdType): array
    {
        // 构建用户完整数据
        $userData = $this->dataBuilder->buildUserData($userId, $userIdType);

        // 缓存数据
        $this->dataCacheManager->set($userId, $userIdType, $userData);

        // 触发数据加载事件
        $this->dispatchDataLoadedEvent($userData);

        return $userData;
    }

    /**
     * 触发数据加载事件.
     *
     * @param array<string, mixed> $userData
     */
    private function dispatchDataLoadedEvent(array $userData): void
    {
        $this->eventDispatcher->dispatch(new UserEvent(
            UserEvent::USER_DATA_LOADED,
            $userData['basic_info'],
            ['full_data' => $userData]
        ));
    }

    /**
     * 批量从缓存加载用户数据.
     *
     * @param string[]                            $userIds
     * @param array<string, array<string, mixed>> $result
     *
     * @return array{result: array<string, array<string, mixed>>, uncachedIds: string[]}
     */
    private function loadFromCacheBatch(array $userIds, string $userIdType, array $result): array
    {
        $uncachedIds = [];

        foreach ($userIds as $userId) {
            $cachedData = $this->dataCacheManager->get($userId, $userIdType);
            if (null !== $cachedData) {
                $result[$userId] = $cachedData;
            } else {
                $uncachedIds[] = $userId;
            }
        }

        return ['result' => $result, 'uncachedIds' => $uncachedIds];
    }

    /**
     * 加载未缓存的用户数据.
     *
     * @param string[]                            $uncachedIds
     * @param array<string, array<string, mixed>> $result
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadUncachedUserData(array $uncachedIds, string $userIdType, array $result): array
    {
        if ([] === $uncachedIds) {
            return $result;
        }

        $batchData = $this->dataBuilder->batchBuildUserData($uncachedIds, $userIdType);
        foreach ($batchData as $userId => $userData) {
            $result[$userId] = $userData;
            $this->dataCacheManager->set($userId, $userIdType, $userData);
        }

        return $result;
    }

    /**
     * 记录批量获取完成日志.
     *
     * @param string[] $userIds
     * @param string[] $uncachedIds
     */
    private function logBatchGetComplete(array $userIds, array $uncachedIds): void
    {
        $this->logger->info('批量获取用户数据完成', [
            'total_count' => \count($userIds),
            'cached_count' => \count($userIds) - \count($uncachedIds),
            'loaded_count' => \count($uncachedIds),
        ]);
    }

    /**
     * 验证用户数据结构的完整性.
     */
    private function isValidUserDataStructure(mixed $data): bool
    {
        if (!\is_array($data)) {
            return false;
        }

        // 检查必需的顶级键
        $requiredKeys = ['basic_info', 'departments', 'permissions', 'subordinates', 'custom_data', 'metadata'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key]) || !\is_array($data[$key])) {
                return false;
            }
        }

        // 检查 metadata 结构
        $metadata = $data['metadata'];
        $requiredMetadataKeys = ['version', 'last_sync', 'sync_status', 'data_source'];
        foreach ($requiredMetadataKeys as $key) {
            if (!isset($metadata[$key])) {
                return false;
            }
        }

        // 验证 metadata 字段类型
        return \is_string($metadata['version'])
            && \is_int($metadata['last_sync'])
            && \is_string($metadata['sync_status'])
            && \is_string($metadata['data_source']);
    }
}
