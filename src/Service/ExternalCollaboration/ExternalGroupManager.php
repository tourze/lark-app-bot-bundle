<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;

/**
 * 外部群组管理器.
 *
 * 管理包含外部用户的群组
 */
#[Autoconfigure(public: true)]
class ExternalGroupManager
{
    private LarkClient $client;

    private ExternalUserIdentifier $userIdentifier;

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    public function __construct(
        LarkClient $client,
        ExternalUserIdentifier $userIdentifier,
        CacheItemPoolInterface $cache,
        ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
        $this->userIdentifier = $userIdentifier;
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 获取外部群组信息.
     *
     * @param string $chatId 群组ID
     *
     * @return array<string, mixed>|null
     */
    public function getExternalGroupInfo(string $chatId): ?array
    {
        if (!$this->userIdentifier->isExternalGroup($chatId)) {
            return null;
        }

        $cacheKey = \sprintf('external_group:%s', $chatId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $response = $this->client->request('GET', '/open-apis/im/v1/chats/' . $chatId, [
                'query' => [
                    'user_id_type' => 'open_id',
                ],
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);
            $groupInfo = $data['data'] ?? null;

            if (null !== $groupInfo) {
                // 增强群组信息
                $groupInfo['is_external'] = true;
                $groupInfo['external_member_count'] = $this->countExternalMembers($chatId);
                $groupInfo['security_settings'] = $this->getSecuritySettings($chatId);

                $cacheItem->set($groupInfo);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }

            return $groupInfo;
        } catch (ApiException $e) {
            $this->logger->error('Failed to get external group info', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 获取群组中的外部成员列表.
     *
     * @param string $chatId 群组ID
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExternalMembers(string $chatId): array
    {
        $cacheKey = \sprintf('external_members:%s', $chatId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $response = $this->client->request('GET', '/open-apis/im/v1/chats/' . $chatId . '/members', [
                'query' => [
                    'user_id_type' => 'open_id',
                    'page_size' => 100,
                ],
            ]);

            $members = $this->extractMembersFromResponse($response->getContent());
            $externalMembers = $this->buildExternalMembers($members);

            $cacheItem->set($externalMembers);
            $cacheItem->expiresAfter(1800); // 30分钟
            $this->cache->save($cacheItem);

            return $externalMembers;
        } catch (ApiException $e) {
            $this->logger->error('Failed to get external members', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMembersFromResponse(string $content): array
    {
        $data = json_decode($content, true);
        $items = $data['data']['items'] ?? [];
        if (!is_iterable($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $members
     * @return array<int, array<string, mixed>>
     */
    private function buildExternalMembers(array $members): array
    {
        $externalMembers = [];
        foreach ($members as $member) {
            if ($this->isExternalMember($member)) {
                $externalMembers[] = $this->normalizeExternalMember($member);
            }
        }

        return $externalMembers;
    }

    /**
     * @param array<string, mixed> $member
     */
    private function isExternalMember(array $member): bool
    {
        return isset($member['member_id']) && $this->userIdentifier->isExternalUser($member['member_id']);
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function normalizeExternalMember(array $member): array
    {
        $memberId = $member['member_id'] ?? '';

        return [
            'user_id' => is_scalar($memberId) ? (string) $memberId : '',
            'name' => $member['name'] ?? 'External User',
            'join_time' => $member['join_time'] ?? null,
            'role' => $member['member_type'] ?? 'member',
        ];
    }

    /**
     * 检查群组是否包含外部成员.
     *
     * @param string $chatId 群组ID
     */
    public function hasExternalMembers(string $chatId): bool
    {
        $externalMembers = $this->getExternalMembers($chatId);

        return \count($externalMembers) > 0;
    }

    /**
     * 更新外部群组设置.
     *
     * @param string               $chatId   群组ID
     * @param array<string, mixed> $settings 设置项
     */
    public function updateExternalGroupSettings(string $chatId, array $settings): bool
    {
        if (!$this->userIdentifier->isExternalGroup($chatId)) {
            $this->logger->warning('Attempted to update settings for non-external group', [
                'chat_id' => $chatId,
            ]);

            return false;
        }

        try {
            // 这里可以调用实际的API更新群组设置
            // 目前仅更新缓存中的设置
            $groupInfo = $this->getExternalGroupInfo($chatId);
            if (null !== $groupInfo) {
                $groupInfo['security_settings'] = array_merge(
                    $groupInfo['security_settings'] ?? [],
                    $settings
                );

                $cacheKey = \sprintf('external_group:%s', $chatId);
                $cacheItem = $this->cache->getItem($cacheKey);
                $cacheItem->set($groupInfo);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }

            $this->logger->info('External group settings updated', [
                'chat_id' => $chatId,
                'settings' => $settings,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update external group settings', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 获取用户所在的外部群组列表.
     *
     * @param string $userId 用户ID
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserExternalGroups(string $userId): array
    {
        try {
            $response = $this->client->request('GET', '/open-apis/im/v1/users/' . $userId . '/chats', [
                'query' => [
                    'user_id_type' => 'open_id',
                    'page_size' => 100,
                ],
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);
            $chats = $data['data']['items'] ?? [];
            if (!is_iterable($chats)) {
                $chats = [];
            }
            $externalGroups = [];

            foreach ($chats as $chat) {
                if (is_array($chat) && isset($chat['chat_id']) && $this->hasExternalMembers($chat['chat_id'])) {
                    $externalGroups[] = [
                        'chat_id' => $chat['chat_id'],
                        'name' => $chat['name'] ?? 'External Group',
                        'type' => $chat['chat_type'] ?? 'group',
                        'member_count' => $chat['member_count'] ?? 0,
                        'external_member_count' => $this->countExternalMembers($chat['chat_id']),
                    ];
                }
            }

            return $externalGroups;
        } catch (ApiException $e) {
            $this->logger->error('Failed to get user external groups', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 清除群组缓存.
     *
     * @param string $chatId 群组ID
     */
    public function clearGroupCache(string $chatId): void
    {
        $this->cache->deleteItem(\sprintf('external_group:%s', $chatId));
        $this->cache->deleteItem(\sprintf('external_members:%s', $chatId));

        $this->logger->info('External group cache cleared', [
            'chat_id' => $chatId,
        ]);
    }

    /**
     * 统计群组中的外部成员数量.
     *
     * @param string $chatId 群组ID
     */
    private function countExternalMembers(string $chatId): int
    {
        try {
            $response = $this->client->request('GET', '/open-apis/im/v1/chats/' . $chatId . '/members', [
                'query' => [
                    'user_id_type' => 'open_id',
                    'page_size' => 100,
                ],
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);
            $members = $data['data']['items'] ?? [];
            if (!is_iterable($members)) {
                $members = [];
            }
            $externalCount = 0;

            foreach ($members as $member) {
                if (isset($member['member_id']) && $this->userIdentifier->isExternalUser($member['member_id'])) {
                    ++$externalCount;
                }
            }

            return $externalCount;
        } catch (ApiException $e) {
            $this->logger->error('Failed to count external members', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * 获取群组安全设置.
     *
     * @param string $chatId 群组ID
     *
     * @return array<string, mixed>
     */
    private function getSecuritySettings(string $chatId): array
    {
        // 外部群组的默认安全设置
        return [
            'allow_file_sharing' => false,
            'allow_screen_capture' => false,
            'message_retention_days' => 90,
            'require_approval_for_new_members' => true,
            'audit_enabled' => true,
        ];
    }
}
