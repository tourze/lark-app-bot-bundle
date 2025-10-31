<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Group;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\Handler\AbstractMessageHandler;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 群组命令处理器.
 *
 * 处理群组相关的命令，如 /add、/remove、/list 等
 */
#[Autoconfigure(public: true)]
class GroupCommandHandler extends AbstractMessageHandler
{
    private const COMMAND_PREFIX = '/';

    public function __construct(
        private readonly GroupService $groupService,
        MessageService $messageService,
        LoggerInterface $logger,
    ) {
        parent::__construct($messageService, $logger);
    }

    /**
     * 是否支持处理该消息.
     */
    public function supports(MessageEvent $event): bool
    {
        // 只处理群组消息
        if ('group' !== $event->getChatType()) {
            return false;
        }

        // 只处理文本消息
        if ('text' !== $event->getMessageType()) {
            return false;
        }

        $contentStr = $event->getContent();
        if ('' === $contentStr) {
            return false;
        }

        // 尝试解码JSON内容
        $content = json_decode($contentStr, true);
        if (!\is_array($content) || !isset($content['text'])) {
            return false;
        }

        // 检查是否是命令
        $text = $content['text'] ?? '';

        return \is_string($text) && str_starts_with($text, self::COMMAND_PREFIX);
    }

    /**
     * 处理消息.
     */
    public function handle(MessageEvent $event): void
    {
        $contentStr = $event->getContent();
        $content = json_decode($contentStr, true);
        $text = '';
        if (\is_array($content)) {
            $textContent = $content['text'] ?? null;
            $text = is_scalar($textContent) ? (string) $textContent : '';
        }
        $chatId = $event->getChatId();
        $senderId = $event->getSenderId();

        // 解析命令
        $parts = explode(' ', trim($text), 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        $this->logger->info('处理群组命令', [
            'chat_id' => $chatId,
            'command' => $command,
            'args' => $args,
            'sender_id' => $senderId,
        ]);

        try {
            switch ($command) {
                case '/help':
                    $this->handleHelpCommand($chatId);
                    break;
                case '/info':
                    $this->handleInfoCommand($chatId);
                    break;
                case '/members':
                case '/list':
                    $this->handleMembersCommand($chatId);
                    break;
                case '/add':
                    $this->handleAddCommand($chatId, $args, $senderId);
                    break;
                case '/remove':
                    $this->handleRemoveCommand($chatId, $args, $senderId);
                    break;
                case '/stats':
                    $this->handleStatsCommand($chatId);
                    break;
                default:
                    $this->handleUnknownCommand($chatId, $command);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error('处理群组命令失败', [
                'chat_id' => $chatId,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            $this->sendErrorMessage($chatId, '命令执行失败，请稍后重试。');
        }
    }

    /**
     * 获取优先级.
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * 处理帮助命令.
     */
    private function handleHelpCommand(string $chatId): void
    {
        $helpText = "群组命令列表：\n\n" .
            "/help - 显示此帮助信息\n" .
            "/info - 查看群组信息\n" .
            "/members - 查看群成员列表\n" .
            "/add @用户 - 添加用户到群组（需要管理员权限）\n" .
            "/remove @用户 - 从群组移除用户（需要管理员权限）\n" .
            "/stats - 查看群组统计信息\n";

        $this->messageService->sendText($chatId, $helpText, null, 'chat_id');
    }

    /**
     * 处理群组信息命令.
     */
    private function handleInfoCommand(string $chatId): void
    {
        $groupInfo = $this->groupService->getGroup($chatId);

        $infoText = \sprintf(
            "群组信息：\n\n" .
            "名称：%s\n" .
            "描述：%s\n" .
            "群主：%s\n" .
            "成员数：%d\n" .
            "机器人数：%d\n" .
            "群类型：%s\n" .
            "是否外部群：%s\n",
            $groupInfo['name'] ?? '未设置',
            $groupInfo['description'] ?? '未设置',
            $groupInfo['owner_id'] ?? '未知',
            $groupInfo['user_count'] ?? 0,
            $groupInfo['bot_count'] ?? 0,
            $groupInfo['chat_type'] ?? 'unknown',
            ($groupInfo['external'] ?? false) ? '是' : '否'
        );

        $this->messageService->sendText($chatId, $infoText, null, 'chat_id');
    }

    /**
     * 处理查看成员命令.
     */
    private function handleMembersCommand(string $chatId): void
    {
        $members = $this->groupService->getMembers($chatId, 'user_id', null, 20);

        if (!isset($members['items']) || [] === $members['items']) {
            $this->messageService->sendText($chatId, '群组中暂无成员。', null, 'chat_id');

            return;
        }

        $memberList = "群成员列表（前20位）：\n\n";
        foreach ($members['items'] as $index => $member) {
            $memberList .= \sprintf(
                "%d. %s\n",
                $index + 1,
                $member['name'] ?? $member['member_id']
            );
        }

        if ($members['has_more']) {
            $memberList .= \sprintf("\n共 %d 位成员，使用 /stats 查看完整统计。", $members['member_total']);
        }

        $this->messageService->sendText($chatId, $memberList, null, 'chat_id');
    }

    /**
     * 处理添加成员命令.
     */
    private function handleAddCommand(string $chatId, string $args, string $senderId): void
    {
        if ('' === $args) {
            $this->messageService->sendText(
                $chatId,
                '请使用正确的格式：/add @用户名 或 /add user_id',
                'chat_id'
            );

            return;
        }

        // 检查权限（这里简化处理，实际应该检查是否是管理员）
        $groupInfo = $this->groupService->getGroup($chatId);
        if (!isset($groupInfo['owner_id']) || $groupInfo['owner_id'] !== $senderId) {
            $this->messageService->sendText(
                $chatId,
                '只有群主才能添加成员。',
                'chat_id'
            );

            return;
        }

        // 解析用户ID（简化处理）
        $userIds = $this->parseUserIds($args);
        if ([] === $userIds) {
            $this->messageService->sendText(
                $chatId,
                '未找到有效的用户ID。',
                'chat_id'
            );

            return;
        }

        // 添加成员
        $result = $this->groupService->addMembers($chatId, $userIds);

        $successCount = \count($userIds) - \count($result['invalid_id_list'] ?? []) - \count($result['not_existed_id_list'] ?? []);
        $message = \sprintf(
            "添加成员结果：\n成功：%d 人\n失败：%d 人",
            $successCount,
            \count($userIds) - $successCount
        );

        if (isset($result['invalid_id_list']) && [] !== $result['invalid_id_list']) {
            $message .= \sprintf("\n无效ID：%s", implode(', ', $result['invalid_id_list']));
        }

        $this->messageService->sendText($chatId, $message, null, 'chat_id');
    }

    /**
     * 处理移除成员命令.
     */
    private function handleRemoveCommand(string $chatId, string $args, string $senderId): void
    {
        if ('' === $args) {
            $this->messageService->sendText(
                $chatId,
                '请使用正确的格式：/remove @用户名 或 /remove user_id',
                'chat_id'
            );

            return;
        }

        // 检查权限
        $groupInfo = $this->groupService->getGroup($chatId);
        if (!isset($groupInfo['owner_id']) || $groupInfo['owner_id'] !== $senderId) {
            $this->messageService->sendText(
                $chatId,
                '只有群主才能移除成员。',
                'chat_id'
            );

            return;
        }

        // 解析用户ID
        $userIds = $this->parseUserIds($args);
        if ([] === $userIds) {
            $this->messageService->sendText(
                $chatId,
                '未找到有效的用户ID。',
                'chat_id'
            );

            return;
        }

        // 移除成员
        $result = $this->groupService->removeMembers($chatId, $userIds);

        $successCount = \count($userIds) - \count($result['invalid_id_list'] ?? []);
        $message = \sprintf(
            "移除成员结果：\n成功：%d 人\n失败：%d 人",
            $successCount,
            \count($userIds) - $successCount
        );

        if (isset($result['invalid_id_list']) && [] !== $result['invalid_id_list']) {
            $message .= \sprintf("\n无效ID：%s", implode(', ', $result['invalid_id_list']));
        }

        $this->messageService->sendText($chatId, $message, null, 'chat_id');
    }

    /**
     * 处理统计命令.
     */
    private function handleStatsCommand(string $chatId): void
    {
        $groupInfo = $this->groupService->getGroup($chatId);
        $allMembers = $this->groupService->getAllMembers($chatId);

        $memberStats = $this->calculateMemberStats($allMembers);
        $statsText = $this->buildStatsText($groupInfo, $memberStats);

        $this->messageService->sendText($chatId, $statsText, null, 'chat_id');
    }

    /**
     * 计算成员统计数据.
     *
     * @param array<int, array<string, mixed>> $allMembers
     *
     * @return array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>}
     */
    private function calculateMemberStats(array $allMembers): array
    {
        $memberStats = [
            'total' => \count($allMembers),
            'with_name' => 0,
            'without_name' => 0,
            'by_tenant' => [],
        ];

        foreach ($allMembers as $member) {
            $memberStats = $this->updateMemberNameStats($memberStats, $member);
            $memberStats = $this->updateTenantStats($memberStats, $member);
        }

        return $memberStats;
    }

    /**
     * 更新成员姓名统计.
     *
     * @param array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>} $stats
     * @param array<string, mixed>                                                                $member
     *
     * @return array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>}
     */
    private function updateMemberNameStats(array $stats, array $member): array
    {
        if (isset($member['name']) && '' !== $member['name']) {
            ++$stats['with_name'];
        } else {
            ++$stats['without_name'];
        }

        return $stats;
    }

    /**
     * 更新租户统计.
     *
     * @param array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>} $stats
     * @param array<string, mixed>                                                                $member
     *
     * @return array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>}
     */
    private function updateTenantStats(array $stats, array $member): array
    {
        if (!isset($member['tenant_key']) || '' === $member['tenant_key']) {
            return $stats;
        }

        $tenant = $member['tenant_key'];
        $stats['by_tenant'][$tenant] = ($stats['by_tenant'][$tenant] ?? 0) + 1;

        return $stats;
    }

    /**
     * 构建统计文本.
     *
     * @param array<string, mixed>                                                                $groupInfo
     * @param array{total: int, with_name: int, without_name: int, by_tenant: array<string, int>} $memberStats
     */
    private function buildStatsText(array $groupInfo, array $memberStats): string
    {
        $statsText = \sprintf(
            "群组统计信息：\n\n" .
            "群组名称：%s\n" .
            "总成员数：%d\n" .
            "机器人数：%d\n" .
            "已设置昵称：%d\n" .
            "未设置昵称：%d\n",
            $groupInfo['name'] ?? '未设置',
            $memberStats['total'],
            $groupInfo['bot_count'] ?? 0,
            $memberStats['with_name'],
            $memberStats['without_name']
        );

        if ([] !== $memberStats['by_tenant']) {
            $statsText .= $this->buildTenantDistribution($memberStats['by_tenant']);
        }

        return $statsText;
    }

    /**
     * 构建租户分布文本.
     *
     * @param array<string, int> $byTenant
     */
    private function buildTenantDistribution(array $byTenant): string
    {
        $text = "\n按租户分布：\n";
        foreach ($byTenant as $tenant => $count) {
            $text .= \sprintf("  %s: %d 人\n", $tenant, $count);
        }

        return $text;
    }

    /**
     * 处理未知命令.
     */
    private function handleUnknownCommand(string $chatId, string $command): void
    {
        $message = \sprintf("未知命令：%s\n输入 /help 查看可用命令。", $command);
        $this->messageService->sendText($chatId, $message, null, 'chat_id');
    }

    /**
     * 发送错误消息.
     */
    private function sendErrorMessage(string $chatId, string $message): void
    {
        try {
            $this->messageService->sendText($chatId, $message, null, 'chat_id');
        } catch (\Exception $e) {
            $this->logger->error('发送错误消息失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 解析用户ID.
     *
     * @return string[]
     */
    private function parseUserIds(string $input): array
    {
        // 简化处理，实际应该支持 @mention 格式解析
        $parts = preg_split('/[\s,]+/', trim($input));
        if (false === $parts) {
            return [];
        }

        $userIds = [];

        foreach ($parts as $part) {
            $part = trim($part, '@');
            if ('' !== $part) {
                $userIds[] = $part;
            }
        }

        return array_unique($userIds);
    }
}
