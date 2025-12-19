<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Group;

use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 群组工具类.
 *
 * 提供批量操作、模板管理、数据导出等功能
 */
final class GroupTools
{
    /**
     * 默认群组模板.
     */
    private const DEFAULT_TEMPLATES = [
        'team' => [
            'name' => '团队协作群',
            'description' => '团队内部沟通协作',
            'chat_mode' => 'group',
            'group_message_type' => 'all',
            'add_member_permission' => 'only_owner',
            'share_card_permission' => 'allowed',
            'at_all_permission' => 'only_owner',
            'edit_permission' => 'only_owner',
            'membership_approval' => 'no_approval',
        ],
        'project' => [
            'name' => '项目群',
            'description' => '项目相关讨论和协作',
            'chat_mode' => 'group',
            'group_message_type' => 'all',
            'add_member_permission' => 'all_members',
            'share_card_permission' => 'allowed',
            'at_all_permission' => 'all_members',
            'edit_permission' => 'all_members',
            'membership_approval' => 'no_approval',
        ],
        'announcement' => [
            'name' => '公告群',
            'description' => '重要通知和公告发布',
            'chat_mode' => 'group',
            'group_message_type' => 'only_owner',
            'add_member_permission' => 'only_owner',
            'share_card_permission' => 'not_allowed',
            'at_all_permission' => 'only_owner',
            'edit_permission' => 'only_owner',
            'membership_approval' => 'approval_by_owner',
        ],
        'discussion' => [
            'name' => '讨论群',
            'description' => '自由讨论和交流',
            'chat_mode' => 'group',
            'group_message_type' => 'all',
            'add_member_permission' => 'all_members',
            'share_card_permission' => 'allowed',
            'at_all_permission' => 'all_members',
            'edit_permission' => 'all_members',
            'membership_approval' => 'no_approval',
        ],
    ];

    public function __construct(
        private readonly GroupService $groupService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 批量添加成员到群组.
     *
     * @param string   $chatId     群组ID
     * @param string[] $memberIds  成员ID列表
     * @param int      $batchSize  每批次大小
     * @param string   $memberType 成员类型
     *
     * @return array{
     *     total: int,
     *     success: int,
     *     failed: int,
     *     invalid_ids: string[],
     *     not_existed_ids: string[]
     * }
     * @throws ValidationException
     */
    public function batchAddMembers(
        string $chatId,
        array $memberIds,
        int $batchSize = 100,
        string $memberType = 'user_id',
    ): array {
        if ([] === $memberIds) {
            throw new ValidationException('成员ID列表不能为空');
        }

        if ($batchSize < 1 || $batchSize > 200) {
            throw new ValidationException('批次大小必须在1-200之间');
        }

        $this->logger->info('批量添加群成员', [
            'chat_id' => $chatId,
            'total_count' => \count($memberIds),
            'batch_size' => $batchSize,
        ]);

        $result = [
            'total' => \count($memberIds),
            'success' => 0,
            'failed' => 0,
            'invalid_ids' => [],
            'not_existed_ids' => [],
        ];

        // 分批处理
        $batches = array_chunk($memberIds, $batchSize);
        foreach ($batches as $index => $batch) {
            try {
                $batchResult = $this->groupService->addMembers($chatId, $batch, $memberType, false);

                $batchSuccess = \count($batch) -
                    \count($batchResult['invalid_id_list'] ?? []) -
                    \count($batchResult['not_existed_id_list'] ?? []);

                $result['success'] += $batchSuccess;
                $result['failed'] += \count($batch) - $batchSuccess;
                $result['invalid_ids'] = array_merge($result['invalid_ids'], $batchResult['invalid_id_list'] ?? []);
                $result['not_existed_ids'] = array_merge($result['not_existed_ids'], $batchResult['not_existed_id_list'] ?? []);

                $this->logger->debug('批次处理完成', [
                    'batch' => $index + 1,
                    'success' => $batchSuccess,
                    'failed' => \count($batch) - $batchSuccess,
                ]);

                // 避免请求过快
                if ($index < \count($batches) - 1) {
                    usleep(100000); // 100ms
                }
            } catch (\Exception $e) {
                $this->logger->error('批次处理失败', [
                    'batch' => $index + 1,
                    'error' => $e->getMessage(),
                ]);
                $result['failed'] += \count($batch);
            }
        }

        return $result;
    }

    /**
     * 批量移除群成员.
     *
     * @param string   $chatId     群组ID
     * @param string[] $memberIds  成员ID列表
     * @param int      $batchSize  每批次大小
     * @param string   $memberType 成员类型
     *
     * @return array{
     *     total: int,
     *     success: int,
     *     failed: int,
     *     invalid_ids: string[]
     * }
     */
    public function batchRemoveMembers(
        string $chatId,
        array $memberIds,
        int $batchSize = 100,
        string $memberType = 'user_id',
    ): array {
        if ([] === $memberIds) {
            throw new ValidationException('成员ID列表不能为空');
        }

        if ($batchSize < 1 || $batchSize > 200) {
            throw new ValidationException('批次大小必须在1-200之间');
        }

        $this->logger->info('批量移除群成员', [
            'chat_id' => $chatId,
            'total_count' => \count($memberIds),
            'batch_size' => $batchSize,
        ]);

        $result = [
            'total' => \count($memberIds),
            'success' => 0,
            'failed' => 0,
            'invalid_ids' => [],
        ];

        // 分批处理
        $batches = array_chunk($memberIds, $batchSize);
        foreach ($batches as $index => $batch) {
            try {
                $batchResult = $this->groupService->removeMembers($chatId, $batch, $memberType);

                $batchSuccess = \count($batch) - \count($batchResult['invalid_id_list'] ?? []);
                $result['success'] += $batchSuccess;
                $result['failed'] += \count($batch) - $batchSuccess;
                $result['invalid_ids'] = array_merge($result['invalid_ids'], $batchResult['invalid_id_list'] ?? []);

                $this->logger->debug('批次处理完成', [
                    'batch' => $index + 1,
                    'success' => $batchSuccess,
                    'failed' => \count($batch) - $batchSuccess,
                ]);

                // 避免请求过快
                if ($index < \count($batches) - 1) {
                    usleep(100000); // 100ms
                }
            } catch (\Exception $e) {
                $this->logger->error('批次处理失败', [
                    'batch' => $index + 1,
                    'error' => $e->getMessage(),
                ]);
                $result['failed'] += \count($batch);
            }
        }

        return $result;
    }

    /**
     * 根据模板创建群组.
     *
     * @param string               $templateName 模板名称
     * @param array<string, mixed> $overrides    覆盖参数
     *
     * @return array{chat_id: string, avatar?: string, name?: string, description?: string, owner_id?: string}
     */
    public function createGroupFromTemplate(string $templateName, array $overrides = []): array
    {
        if (!isset(self::DEFAULT_TEMPLATES[$templateName])) {
            throw new ValidationException(\sprintf('未知的群组模板：%s', $templateName));
        }

        $template = self::DEFAULT_TEMPLATES[$templateName];
        $params = array_merge($template, $overrides);

        $this->logger->info('使用模板创建群组', [
            'template' => $templateName,
            'params' => $params,
        ]);

        return $this->groupService->createGroup($params);
    }

    /**
     * 获取可用的群组模板列表.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableTemplates(): array
    {
        return self::DEFAULT_TEMPLATES;
    }

    /**
     * 导出群组数据.
     *
     * @param string $chatId         群组ID
     * @param bool   $includeMembers 是否包含成员信息
     *
     * @return array{
     *     group_info: array<string, mixed>,
     *     members?: array<array<string, mixed>>,
     *     export_time: string,
     *     member_count?: int
     * }
     */
    public function exportGroupData(string $chatId, bool $includeMembers = true): array
    {
        $this->logger->info('导出群组数据', [
            'chat_id' => $chatId,
            'include_members' => $includeMembers,
        ]);

        $exportData = [
            'group_info' => $this->groupService->getGroup($chatId),
            'export_time' => date('Y-m-d H:i:s'),
        ];

        if ($includeMembers) {
            $members = $this->groupService->getAllMembers($chatId);
            $exportData['members'] = $members;
            $exportData['member_count'] = \count($members);
        }

        return $exportData;
    }

    /**
     * 导出群组数据为CSV格式.
     *
     * @param string $chatId 群组ID
     *
     * @return string CSV内容
     */
    public function exportGroupDataAsCsv(string $chatId): string
    {
        $data = $this->exportGroupData($chatId, true);
        $groupInfo = $data['group_info'] ?? [];
        $members = $data['members'] ?? [];

        // CSV头部
        $csv = "群组信息\n";
        $csv .= \sprintf("群组ID,%s\n", $groupInfo['chat_id'] ?? '');
        $csv .= \sprintf("群组名称,%s\n", $groupInfo['name'] ?? '');
        $csv .= \sprintf("群组描述,%s\n", $groupInfo['description'] ?? '');
        $csv .= \sprintf("群主ID,%s\n", $groupInfo['owner_id'] ?? '');
        $csv .= \sprintf("成员总数,%d\n", $data['member_count'] ?? 0);
        $csv .= \sprintf("导出时间,%s\n", $data['export_time'] ?? '');
        $csv .= "\n";

        // 成员列表
        if ([] !== $members) {
            $csv .= "成员列表\n";
            $csv .= "序号,成员ID,成员名称,租户\n";
            foreach ($members as $index => $member) {
                $csv .= \sprintf(
                    "%d,%s,%s,%s\n",
                    $index + 1,
                    $member['member_id'],
                    $member['name'] ?? '',
                    $member['tenant_key'] ?? ''
                );
            }
        }

        return $csv;
    }

    /**
     * 群组统计分析.
     *
     * @param string $chatId 群组ID
     *
     * @return array{
     *     basic: array{
     *         chat_id: string,
     *         name: string,
     *         description: string,
     *         owner_id: string,
     *         chat_type: string,
     *         external: bool
     *     },
     *     members: array{
     *         total: int,
     *         users: int,
     *         bots: int,
     *         with_name: int,
     *         without_name: int,
     *         by_tenant: array<string, int>
     *     },
     *     permissions: array{
     *         add_member: string,
     *         share_card: string,
     *         at_all: string,
     *         edit: string,
     *         message_type: string
     *     }
     * }
     */
    public function analyzeGroup(string $chatId): array
    {
        $groupInfo = $this->groupService->getGroup($chatId);
        $members = $this->groupService->getAllMembers($chatId);

        // 基本信息
        $basic = [
            'chat_id' => $groupInfo['chat_id'] ?? '',
            'name' => $groupInfo['name'] ?? '',
            'description' => $groupInfo['description'] ?? '',
            'owner_id' => $groupInfo['owner_id'] ?? '',
            'chat_type' => $groupInfo['chat_type'] ?? '',
            'external' => $groupInfo['external'] ?? false,
        ];

        // 成员统计
        $memberStats = [
            'total' => \count($members),
            'users' => $groupInfo['user_count'] ?? 0,
            'bots' => $groupInfo['bot_count'] ?? 0,
            'with_name' => 0,
            'without_name' => 0,
            'by_tenant' => [],
        ];

        foreach ($members as $member) {
            // 使用类型守卫检查成员信息
            if (isset($member['name']) && '' !== $member['name']) {
                ++$memberStats['with_name'];
            } else {
                ++$memberStats['without_name'];
            }

            // 安全地访问可选的 tenant_key 字段
            if (isset($member['tenant_key']) && '' !== $member['tenant_key']) {
                $tenant = $member['tenant_key'];
                $memberStats['by_tenant'][$tenant] = ($memberStats['by_tenant'][$tenant] ?? 0) + 1;
            }
        }

        // 权限设置
        $permissions = [
            'add_member' => $groupInfo['add_member_permission'] ?? '',
            'share_card' => $groupInfo['share_card_permission'] ?? '',
            'at_all' => $groupInfo['at_all_permission'] ?? '',
            'edit' => $groupInfo['edit_permission'] ?? '',
            'message_type' => $groupInfo['group_message_type'] ?? '',
        ];

        return [
            'basic' => $basic,
            'members' => $memberStats,
            'permissions' => $permissions,
        ];
    }

    /**
     * 复制群组（创建具有相同设置的新群组）.
     *
     * @param string               $sourceChatId 源群组ID
     * @param string               $newName      新群组名称
     * @param array<string, mixed> $overrides    覆盖设置
     * @param bool                 $copyMembers  是否复制成员
     *
     * @return array{chat_id: string, avatar?: string, name?: string, description?: string, owner_id?: string}
     */
    public function cloneGroup(string $sourceChatId, string $newName, array $overrides = [], bool $copyMembers = false): array
    {
        $this->logger->info('复制群组', [
            'source_chat_id' => $sourceChatId,
            'new_name' => $newName,
            'copy_members' => $copyMembers,
        ]);

        $sourceGroup = $this->groupService->getGroup($sourceChatId);
        $params = $this->buildGroupParams($sourceGroup, $newName, $overrides);
        $newGroup = $this->groupService->createGroup($params);

        if ($copyMembers) {
            $this->copyGroupMembers($sourceChatId, $newGroup['chat_id'], $sourceGroup['owner_id'] ?? '');
        }

        return $newGroup;
    }

    /**
     * 获取群组成员（getGroupMembers别名方法）.
     *
     * @param string $chatId     群组ID
     * @param string $memberType 成员类型：user_id, union_id, open_id
     *
     * @return array<array{
     *     member_id: string,
     *     member_id_type: string,
     *     name?: string,
     *     tenant_key?: string
     * }>
     * @throws ApiException
     */
    public function getGroupMembers(string $chatId, string $memberType = 'user_id'): array
    {
        return $this->groupService->getAllMembers($chatId, $memberType);
    }

    /**
     * 构建群组参数.
     *
     * @param array<string, mixed> $sourceGroup 源群组信息
     * @param string               $newName     新群组名称
     * @param array<string, mixed> $overrides   覆盖设置
     *
     * @return array<string, mixed>
     */
    private function buildGroupParams(array $sourceGroup, string $newName, array $overrides): array
    {
        $params = [
            'name' => $newName,
            'description' => $sourceGroup['description'] ?? '',
        ];

        $params = $this->copyOptionalGroupFields($params, $sourceGroup);

        return array_merge($params, $overrides);
    }

    /**
     * 复制可选的群组字段.
     *
     * @param array<string, mixed> $params      目标参数数组
     * @param array<string, mixed> $sourceGroup 源群组信息
     *
     * @return array<string, mixed>
     */
    private function copyOptionalGroupFields(array $params, array $sourceGroup): array
    {
        $optionalFields = [
            'i18n_names',
            'chat_mode',
            'chat_type',
            'group_message_type',
            'add_member_permission',
            'share_card_permission',
            'at_all_permission',
            'edit_permission',
            'membership_approval',
        ];

        foreach ($optionalFields as $field) {
            if (isset($sourceGroup[$field])) {
                $params[$field] = $sourceGroup[$field];
            }
        }

        return $params;
    }

    /**
     * 复制群组成员.
     *
     * @param string $sourceChatId 源群组ID
     * @param string $newChatId    新群组ID
     * @param string $ownerId      群主ID
     */
    private function copyGroupMembers(string $sourceChatId, string $newChatId, string $ownerId): void
    {
        if ('' === $newChatId) {
            return;
        }

        try {
            $members = $this->groupService->getAllMembers($sourceChatId);
            $memberIds = array_column($members, 'member_id');
            $memberIds = $this->excludeOwnerFromMembers($memberIds, $ownerId);

            if ([] !== $memberIds) {
                $this->batchAddMembers($newChatId, array_values($memberIds));
            }
        } catch (\Exception $e) {
            $this->logger->error('复制群成员失败', [
                'new_chat_id' => $newChatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 从成员列表中排除群主.
     *
     * @param string[] $memberIds 成员ID列表
     * @param string   $ownerId   群主ID
     *
     * @return string[]
     */
    private function excludeOwnerFromMembers(array $memberIds, string $ownerId): array
    {
        $ownerIndex = array_search($ownerId, $memberIds, true);
        if (false !== $ownerIndex) {
            unset($memberIds[$ownerIndex]);
        }

        return $memberIds;
    }
}
