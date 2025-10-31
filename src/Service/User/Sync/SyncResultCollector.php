<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 同步结果收集器.
 *
 * 负责收集和统计同步结果
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'lark_app_bot')]
class SyncResultCollector
{
    private const SYNC_BATCH_SIZE = 100;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 创建空的同步结果.
     *
     * @return array{success: array<mixed>, failed: array<mixed>, skipped: array<mixed>}
     */
    public function createEmptyResult(): array
    {
        return [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];
    }

    /**
     * 初始化同步结果.
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function initializeResult(): array
    {
        return [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];
    }

    /**
     * 添加成功的同步结果.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     * @param array<mixed>                                                                     $userData
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function addSuccess(array $result, string $userId, array $userData): array
    {
        $result['success'][$userId] = $userData;

        return $result;
    }

    /**
     * 添加失败的同步结果.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function addFailure(array $result, string $userId): array
    {
        $result['failed'][] = $userId;

        return $result;
    }

    /**
     * 批量添加失败结果.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     * @param string[]                                                                         $userIds
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function addBatchFailures(array $result, array $userIds): array
    {
        $result['failed'] = array_merge($result['failed'], $userIds);

        return $result;
    }

    /**
     * 添加跳过的同步结果.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     * @param string[]                                                                         $userIds
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function addSkipped(array $result, array $userIds): array
    {
        $result['skipped'] = array_merge($result['skipped'], $userIds);

        return $result;
    }

    /**
     * 计算需要处理的批次.
     *
     * @param string[] $userIds
     *
     * @return array<string[]>
     */
    public function createBatches(array $userIds): array
    {
        return array_chunk($userIds, self::SYNC_BATCH_SIZE);
    }

    /**
     * 记录批量同步开始日志.
     *
     * @param string[] $userIds
     */
    public function logBatchSyncStart(array $userIds, string $userIdType, bool $force): void
    {
        $this->logger->info('开始批量同步用户数据', [
            'user_count' => \count($userIds),
            'user_id_type' => $userIdType,
            'force' => $force,
        ]);
    }

    /**
     * 记录批量同步完成日志.
     *
     * @param string[]                                                                         $userIds
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     */
    public function logBatchSyncComplete(array $userIds, array $result): void
    {
        $this->logger->info('批量同步用户数据完成', [
            'total' => \count($userIds),
            'success' => \count($result['success']),
            'failed' => \count($result['failed']),
            'skipped' => \count($result['skipped']),
        ]);
    }

    /**
     * 获取同步结果统计.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array<string, mixed>
     */
    public function getResultStats(array $result): array
    {
        return [
            'total' => \count($result['success']) + \count($result['failed']) + \count($result['skipped']),
            'success_count' => \count($result['success']),
            'failed_count' => \count($result['failed']),
            'skipped_count' => \count($result['skipped']),
            'success_rate' => $this->calculateSuccessRate($result),
        ];
    }

    /**
     * 计算成功率.
     *
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     */
    private function calculateSuccessRate(array $result): float
    {
        $total = \count($result['success']) + \count($result['failed']);
        if (0 === $total) {
            return 0.0;
        }

        return round(\count($result['success']) / $total * 100, 2);
    }
}
