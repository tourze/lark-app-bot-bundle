<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Group\GroupTools;

/**
 * 列出群成员的操作处理器.
 */
final class ListMembersActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupTools $groupTools,
    ) {
    }

    public function getActionName(): string
    {
        return 'members';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $this->validateChatId($chatId);
        $pageOption = $input->getOption('page');
        $sizeOption = $input->getOption('size');
        $page = is_numeric($pageOption) ? (int) $pageOption : 1;
        $size = is_numeric($sizeOption) ? (int) $sizeOption : 20;

        $members = $this->groupTools->getGroupMembers((string) $chatId, 'user_id');

        if ([] === $members) {
            $io->warning('该群组没有成员');

            return Command::SUCCESS;
        }

        $this->displayMembersList($io, $members, $page, $size);

        return Command::SUCCESS;
    }

    /**
     * 显示成员列表.
     *
     * @param array<mixed> $members
     */
    private function displayMembersList(SymfonyStyle $io, array $members, int $page, int $size): void
    {
        $io->title(\sprintf('群组成员列表 (第 %d 页)', $page));

        $rows = [];
        foreach ($members as $member) {
            if (!\is_array($member)) {
                continue;
            }
            $joinTime = 'N/A';
            if (isset($member['join_time']) && \is_int($member['join_time'])) {
                $joinTime = date('Y-m-d H:i:s', $member['join_time']);
            }
            $rows[] = [
                $member['member_id'] ?? 'N/A',
                $member['name'] ?? 'N/A',
                $member['member_type'] ?? 'user',
                $joinTime,
            ];
        }

        $io->table(
            ['成员ID', '名称', '类型', '加入时间'],
            $rows
        );

        if (\count($members) === $size) {
            $io->note(\sprintf('可能还有更多成员，使用 --page=%d 查看下一页', $page + 1));
        }
    }
}
