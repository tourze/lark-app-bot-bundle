<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Service\User\UserEvent;

/**
 * 用户事件分发器.
 *
 * 负责用户相关事件的创建和分发
 */
#[Autoconfigure(public: true)]
final class UserEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserChangeDetector $changeDetector,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 触发用户更新事件.
     *
     * @param array<mixed> $oldData
     * @param array<mixed> $newData
     */
    public function dispatchUserUpdatedEvent(
        string $userId,
        string $userIdType,
        array $oldData,
        array $newData,
    ): void {
        $changes = $this->changeDetector->detectChanges($oldData, $newData);

        $event = new UserEvent(UserEvent::USER_UPDATED, [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ], [
            'action' => 'updated',
            'old_data' => $oldData,
            'new_data' => $newData,
            'changes' => $changes,
            'timestamp' => time(),
        ]);

        $this->eventDispatcher->dispatch($event);

        $this->logger->info('触发用户更新事件', [
            'user_id' => $userId,
            'changes' => array_keys($changes),
        ]);
    }

    /**
     * 触发用户删除事件.
     */
    public function dispatchUserDeletedEvent(string $userId, string $userIdType): void
    {
        $event = new UserEvent(UserEvent::USER_DELETED, [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ], [
            'action' => 'deleted',
            'timestamp' => time(),
        ]);

        $this->eventDispatcher->dispatch($event);

        $this->logger->info('触发用户删除事件', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ]);
    }

    /**
     * 判断是否应该触发更新事件.
     *
     * @param array<mixed>|null $oldData
     * @param array<mixed>      $newData
     */
    public function shouldDispatchUpdateEvent(?array $oldData, array $newData): bool
    {
        return $this->changeDetector->shouldDispatchUpdateEvent($oldData, $newData);
    }

    /**
     * 触发批量同步完成事件.
     *
     * @param string[]                                                                         $userIds
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     */
    public function dispatchBatchSyncCompletedEvent(array $userIds, string $userIdType, array $result): void
    {
        $event = new UserEvent(UserEvent::BATCH_SYNC_COMPLETED, [
            'user_ids' => $userIds,
            'user_id_type' => $userIdType,
        ], [
            'action' => 'batch_sync_completed',
            'result' => $result,
            'timestamp' => time(),
        ]);

        $this->eventDispatcher->dispatch($event);

        $this->logger->info('触发批量同步完成事件', [
            'user_count' => \count($userIds),
            'success_count' => \count($result['success']),
            'failed_count' => \count($result['failed']),
            'skipped_count' => \count($result['skipped']),
        ]);
    }

    /**
     * 分发事件的通用方法.
     */
    public function dispatch(UserEvent $event, string $eventName): void
    {
        // Symfony 6+只需要传递事件对象，事件名称会自动从事件类中获取
        $this->eventDispatcher->dispatch($event);
    }
}
