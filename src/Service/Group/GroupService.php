<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Group;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;

/**
 * 群组服务.
 *
 * 提供群组创建、管理、成员操作等功能
 */
#[Autoconfigure(public: true)]
final class GroupService
{
    public function __construct(
        private readonly LarkClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 创建群组.
     *
     * @param array{
     *     name?: string,
     *     description?: string,
     *     avatar?: string,
     *     i18n_names?: array<string, string>,
     *     owner_id?: string,
     *     user_id_list?: string[],
     *     bot_id_list?: string[],
     *     group_message_type?: string,
     *     chat_mode?: string,
     *     chat_type?: string,
     *     external?: bool,
     *     join_message_visibility?: string,
     *     leave_message_visibility?: string,
     *     membership_approval?: string
     * } $params 创建群组的参数
     *
     * @return array{chat_id: string, avatar?: string, name?: string, description?: string, owner_id?: string}
     * @throws ApiException
     * @throws ValidationException
     */
    public function createGroup(array $params): array
    {
        // 验证必要参数
        if ((!isset($params['name']) || '' === $params['name']) && (!isset($params['i18n_names']) || [] === $params['i18n_names'])) {
            throw new ValidationException('群组名称或国际化名称至少需要提供一个');
        }

        $this->logger->info('创建群组', ['params' => $params]);

        try {
            $response = $this->client->request('POST', '/open-apis/im/v1/chats', [
                'query' => [
                    'uuid' => $this->generateUuid(),
                ],
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['chat_id'])) {
                throw new GenericApiException('创建群组失败：响应中缺少chat_id');
            }

            $this->logger->info('群组创建成功', [
                'chat_id' => $data['data']['chat_id'],
                'name' => $data['data']['name'] ?? '',
            ]);

            return $data['data'];
        } catch (\Exception $e) {
            $this->logger->error('创建群组失败', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }

    /**
     * 更新群组信息.
     *
     * @param string $chatId 群组ID
     * @param array{
     *     avatar?: string,
     *     name?: string,
     *     description?: string,
     *     i18n_names?: array<string, string>,
     *     add_member_permission?: string,
     *     share_card_permission?: string,
     *     at_all_permission?: string,
     *     edit_permission?: string,
     *     group_message_type?: string,
     *     join_message_visibility?: string,
     *     leave_message_visibility?: string,
     *     membership_approval?: string,
     *     owner_id?: string
     * } $params 更新参数
     *
     * @throws ApiException
     */
    public function updateGroup(string $chatId, array $params): void
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        if ([] === $params) {
            throw new ValidationException('更新参数不能为空');
        }

        $this->logger->info('更新群组信息', [
            'chat_id' => $chatId,
            'params' => $params,
        ]);

        try {
            $this->client->request('PUT', \sprintf('/open-apis/im/v1/chats/%s', $chatId), [
                'json' => $params,
            ]);

            $this->logger->info('群组信息更新成功', ['chat_id' => $chatId]);
        } catch (\Exception $e) {
            $this->logger->error('更新群组信息失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取群组信息.
     *
     * @param string $chatId 群组ID
     *
     * @return array{
     *     chat_id: string,
     *     avatar?: string,
     *     name?: string,
     *     description?: string,
     *     i18n_names?: array<string, string>,
     *     owner_id?: string,
     *     owner_id_type?: string,
     *     add_member_permission?: string,
     *     share_card_permission?: string,
     *     at_all_permission?: string,
     *     edit_permission?: string,
     *     chat_mode?: string,
     *     chat_type?: string,
     *     chat_tag?: string,
     *     external?: bool,
     *     tenant_key?: string,
     *     user_count?: int,
     *     bot_count?: int
     * }
     * @throws ApiException
     */
    public function getGroup(string $chatId): array
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        $this->logger->debug('获取群组信息', ['chat_id' => $chatId]);

        try {
            $response = $this->client->request('GET', \sprintf('/open-apis/im/v1/chats/%s', $chatId));
            $data = $response->toArray();

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('获取群组信息失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 解散群组.
     *
     * @param string $chatId 群组ID
     *
     * @throws ApiException
     */
    public function disbandGroup(string $chatId): void
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        $this->logger->info('解散群组', ['chat_id' => $chatId]);

        try {
            $this->client->request('DELETE', \sprintf('/open-apis/im/v1/chats/%s', $chatId));
            $this->logger->info('群组解散成功', ['chat_id' => $chatId]);
        } catch (\Exception $e) {
            $this->logger->error('解散群组失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 添加群成员.
     *
     * @param string   $chatId      群组ID
     * @param string[] $memberIds   成员ID列表
     * @param string   $memberType  成员类型：user_id, union_id, open_id, app_id
     * @param bool     $notifyUsers 是否通知用户
     *
     * @return array{
     *     invalid_id_list?: string[],
     *     not_existed_id_list?: string[],
     *     pending_approval_id_list?: string[]
     * }
     * @throws ApiException
     */
    public function addMembers(string $chatId, array $memberIds, string $memberType = 'user_id', bool $notifyUsers = true): array
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        if ([] === $memberIds) {
            throw new ValidationException('成员ID列表不能为空');
        }

        $validTypes = ['user_id', 'union_id', 'open_id', 'app_id'];
        if (!\in_array($memberType, $validTypes, true)) {
            throw new ValidationException(\sprintf('无效的成员类型：%s', $memberType));
        }

        $this->logger->info('添加群成员', [
            'chat_id' => $chatId,
            'member_count' => \count($memberIds),
            'member_type' => $memberType,
        ]);

        try {
            $response = $this->client->request('POST', \sprintf('/open-apis/im/v1/chats/%s/members', $chatId), [
                'query' => [
                    'member_id_type' => $memberType,
                ],
                'json' => [
                    'id_list' => $memberIds,
                    'add_type' => $notifyUsers ? 'normal' : 'silent',
                ],
            ]);

            $data = $response->toArray();
            $result = $data['data'] ?? [];

            $this->logger->info('群成员添加完成', [
                'chat_id' => $chatId,
                'success_count' => \count($memberIds) - \count($result['invalid_id_list'] ?? []) - \count($result['not_existed_id_list'] ?? []),
                'invalid_count' => \count($result['invalid_id_list'] ?? []),
                'not_existed_count' => \count($result['not_existed_id_list'] ?? []),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('添加群成员失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 移除群成员.
     *
     * @param string   $chatId     群组ID
     * @param string[] $memberIds  成员ID列表
     * @param string   $memberType 成员类型：user_id, union_id, open_id, app_id
     *
     * @return array{invalid_id_list?: string[]}
     * @throws ApiException
     */
    public function removeMembers(string $chatId, array $memberIds, string $memberType = 'user_id'): array
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        if ([] === $memberIds) {
            throw new ValidationException('成员ID列表不能为空');
        }

        $validTypes = ['user_id', 'union_id', 'open_id', 'app_id'];
        if (!\in_array($memberType, $validTypes, true)) {
            throw new ValidationException(\sprintf('无效的成员类型：%s', $memberType));
        }

        $this->logger->info('移除群成员', [
            'chat_id' => $chatId,
            'member_count' => \count($memberIds),
            'member_type' => $memberType,
        ]);

        try {
            $response = $this->client->request('DELETE', \sprintf('/open-apis/im/v1/chats/%s/members', $chatId), [
                'query' => [
                    'member_id_type' => $memberType,
                ],
                'json' => [
                    'id_list' => $memberIds,
                ],
            ]);

            $data = $response->toArray();
            $result = $data['data'] ?? [];

            $this->logger->info('群成员移除完成', [
                'chat_id' => $chatId,
                'success_count' => \count($memberIds) - \count($result['invalid_id_list'] ?? []),
                'invalid_count' => \count($result['invalid_id_list'] ?? []),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('移除群成员失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取群成员列表.
     *
     * @param string      $chatId     群组ID
     * @param string      $memberType 成员类型：user_id, union_id, open_id
     * @param string|null $pageToken  分页标记
     * @param int         $pageSize   每页大小（最大100）
     *
     * @return array{
     *     items: array<array{
     *         member_id: string,
     *         member_id_type: string,
     *         name?: string,
     *         tenant_key?: string
     *     }>,
     *     page_token?: string,
     *     has_more: bool,
     *     member_total: int
     * }
     * @throws ApiException
     */
    public function getMembers(string $chatId, string $memberType = 'user_id', ?string $pageToken = null, int $pageSize = 50): array
    {
        if ('' === $chatId) {
            throw new ValidationException('群组ID不能为空');
        }

        $validTypes = ['user_id', 'union_id', 'open_id'];
        if (!\in_array($memberType, $validTypes, true)) {
            throw new ValidationException(\sprintf('无效的成员类型：%s', $memberType));
        }

        if ($pageSize < 1 || $pageSize > 100) {
            throw new ValidationException('每页大小必须在1-100之间');
        }

        $this->logger->debug('获取群成员列表', [
            'chat_id' => $chatId,
            'member_type' => $memberType,
            'page_size' => $pageSize,
        ]);

        try {
            $query = [
                'member_id_type' => $memberType,
                'page_size' => $pageSize,
            ];

            if (null !== $pageToken) {
                $query['page_token'] = $pageToken;
            }

            $response = $this->client->request('GET', \sprintf('/open-apis/im/v1/chats/%s/members', $chatId), [
                'query' => $query,
            ]);

            $data = $response->toArray();
            $result = $data['data'] ?? [];

            // 确保返回结构完整
            return [
                'items' => $result['items'] ?? [],
                'page_token' => $result['page_token'] ?? null,
                'has_more' => $result['has_more'] ?? false,
                'member_total' => $result['member_total'] ?? 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('获取群成员列表失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取所有群成员（自动分页）.
     *
     * @param string $chatId     群组ID
     * @param string $memberType 成员类型
     *
     * @return array<array{
     *     member_id: string,
     *     member_id_type: string,
     *     name?: string,
     *     tenant_key?: string
     * }>
     * @throws ApiException
     */
    public function getAllMembers(string $chatId, string $memberType = 'user_id'): array
    {
        $allMembers = [];
        $pageToken = null;

        do {
            $result = $this->getMembers($chatId, $memberType, $pageToken, 100);
            $allMembers = array_merge($allMembers, $result['items']);
            $pageToken = $result['page_token'] ?? null;
        } while ($result['has_more'] ?? false);

        $this->logger->info('获取所有群成员完成', [
            'chat_id' => $chatId,
            'total_count' => \count($allMembers),
        ]);

        return $allMembers;
    }

    /**
     * 获取群组列表.
     *
     * @param string      $userIdType 用户ID类型：user_id, union_id, open_id
     * @param string|null $pageToken  分页标记
     * @param int         $pageSize   每页大小（最大100）
     *
     * @return array{
     *     items: array<array{
     *         chat_id: string,
     *         avatar?: string,
     *         name?: string,
     *         description?: string,
     *         owner_id?: string,
     *         owner_id_type?: string,
     *         chat_mode?: string,
     *         chat_type?: string,
     *         chat_tag?: string,
     *         external?: bool,
     *         tenant_key?: string
     *     }>,
     *     page_token?: string,
     *     has_more: bool
     * }
     * @throws ApiException
     */
    public function listGroups(string $userIdType = 'open_id', ?string $pageToken = null, int $pageSize = 50): array
    {
        $validTypes = ['user_id', 'union_id', 'open_id'];
        if (!\in_array($userIdType, $validTypes, true)) {
            throw new ValidationException(\sprintf('无效的用户ID类型：%s', $userIdType));
        }

        if ($pageSize < 1 || $pageSize > 100) {
            throw new ValidationException('每页大小必须在1-100之间');
        }

        $this->logger->debug('获取群组列表', [
            'user_id_type' => $userIdType,
            'page_size' => $pageSize,
        ]);

        try {
            $query = [
                'user_id_type' => $userIdType,
                'page_size' => $pageSize,
            ];

            if (null !== $pageToken) {
                $query['page_token'] = $pageToken;
            }

            $response = $this->client->request('GET', '/open-apis/im/v1/chats', [
                'query' => $query,
            ]);

            $data = $response->toArray();
            $result = $data['data'] ?? [];

            return [
                'items' => $result['items'] ?? [],
                'page_token' => $result['page_token'] ?? null,
                'has_more' => $result['has_more'] ?? false,
            ];
        } catch (\Exception $e) {
            $this->logger->error('获取群组列表失败', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取群组信息（getGroupInfo别名方法）.
     *
     * @param string $chatId 群组ID
     *
     * @return array{
     *     chat_id: string,
     *     avatar?: string,
     *     name?: string,
     *     description?: string,
     *     i18n_names?: array<string, string>,
     *     owner_id?: string,
     *     owner_id_type?: string,
     *     add_member_permission?: string,
     *     share_card_permission?: string,
     *     at_all_permission?: string,
     *     edit_permission?: string,
     *     chat_mode?: string,
     *     chat_type?: string,
     *     chat_tag?: string,
     *     external?: bool,
     *     tenant_key?: string,
     *     user_count?: int,
     *     bot_count?: int
     * }
     * @throws ApiException
     */
    public function getGroupInfo(string $chatId): array
    {
        return $this->getGroup($chatId);
    }

    /**
     * 生成UUID.
     */
    private function generateUuid(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }
}
