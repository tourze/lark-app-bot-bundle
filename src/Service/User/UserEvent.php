<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户事件类.
 *
 * 用于表示用户相关的各种事件
 */
final class UserEvent
{
    public const USER_UPDATED = 'user.updated';
    public const USER_DELETED = 'user.deleted';
    public const USER_CREATED = 'user.created';
    public const BATCH_SYNC_COMPLETED = 'user.batch_sync_completed';

    /**
     * @param array<string, mixed> $userData
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $eventType,
        private readonly array $userData,
        private readonly array $metadata = [],
    ) {
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserData(): array
    {
        return $this->userData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getUserId(): ?string
    {
        return $this->userData['user_id'] ?? null;
    }

    public function getUserIdType(): ?string
    {
        return $this->userData['user_id_type'] ?? null;
    }
}
