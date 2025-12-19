<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;

/**
 * 批量同步处理器.
 *
 * 负责处理用户的批量同步逻辑
 */
#[Autoconfigure(public: true)]
final class BatchSyncProcessor
{
    private const SYNC_BATCH_SIZE = 50;

    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserDataProcessor $dataProcessor,
        private readonly SyncStrategyManager $strategyManager,
        private readonly SyncErrorHandler $errorHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 执行批量同步.
     *
     * @param string[] $userIds
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function processBatchSync(array $userIds, string $userIdType, bool $force): array
    {
        if ([] === $userIds) {
            return $this->getEmptyResult();
        }

        $this->logBatchSyncStart($userIds, $userIdType, $force);

        $result = $this->initializeResult();
        $chunks = array_chunk($userIds, self::SYNC_BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $result = $this->processSyncChunk($chunk, $userIdType, $force, $result);
        }

        $this->logBatchSyncComplete($userIds, $result);

        return $result;
    }

    /**
     * 处理单个同步块.
     *
     * @param string[]                                                                         $chunk
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function processSyncChunk(array $chunk, string $userIdType, bool $force, array $result): array
    {
        $filterResult = $this->strategyManager->filterUsersToSync($chunk, $userIdType, $force);
        $result['skipped'] = array_merge($result['skipped'], $filterResult['skipped']);

        if ([] === $filterResult['toSync']) {
            return $result;
        }

        return $this->syncChunkUsers($filterResult['toSync'], $userIdType, $result);
    }

    /**
     * 同步块中的用户.
     *
     * @param string[]                                                                         $toSync
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function syncChunkUsers(array $toSync, string $userIdType, array $result): array
    {
        try {
            $users = $this->userService->batchGetUsers($toSync, $userIdType);
            $result = $this->processRetrievedUsers($users, $userIdType, $result);
            $result = $this->handleMissingUsers($toSync, $users, $userIdType, $result);
        } catch (\Exception $e) {
            $result['failed'] = $this->errorHandler->handleBatchSyncErrorWithFailedRef($toSync, $e, $result['failed']);
        }

        return $result;
    }

    /**
     * 处理获取到的用户数据.
     *
     * @param array<string, array<mixed>>                                                      $users
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function processRetrievedUsers(array $users, string $userIdType, array $result): array
    {
        foreach ($users as $userId => $userData) {
            try {
                $processedData = $this->dataProcessor->processUserData($userId, $userIdType, $userData);
                $this->strategyManager->recordSyncTime($userId, $userIdType);
                $result['success'][$userId] = $processedData;
            } catch (\Exception $e) {
                $result['failed'] = $this->errorHandler->handleSingleUserError($userId, $e, $result['failed']);
            }
        }

        return $result;
    }

    /**
     * 处理未返回的用户.
     *
     * @param string[]                                                                         $toSync
     * @param array<string, array<mixed>>                                                      $users
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function handleMissingUsers(array $toSync, array $users, string $userIdType, array $result): array
    {
        foreach ($toSync as $userId) {
            if (!isset($users[$userId])) {
                $this->dataProcessor->handleDeletedUser($userId, $userIdType);
                $result['failed'][] = $userId;
            }
        }

        return $result;
    }

    /**
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function getEmptyResult(): array
    {
        return [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];
    }

    /**
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function initializeResult(): array
    {
        return [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];
    }

    /**
     * @param string[] $userIds
     */
    private function logBatchSyncStart(array $userIds, string $userIdType, bool $force): void
    {
        $this->logger->info('开始批量同步用户数据', [
            'user_count' => \count($userIds),
            'user_id_type' => $userIdType,
            'force' => $force,
        ]);
    }

    /**
     * @param string[]                                                                         $userIds
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     */
    private function logBatchSyncComplete(array $userIds, array $result): void
    {
        $this->logger->info('批量同步用户数据完成', [
            'total' => \count($userIds),
            'success' => \count($result['success']),
            'failed' => \count($result['failed']),
            'skipped' => \count($result['skipped']),
        ]);
    }
}
