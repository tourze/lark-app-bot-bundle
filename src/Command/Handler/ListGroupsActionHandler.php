<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 列出所有群组的操作处理器.
 */
class ListGroupsActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    public function getActionName(): string
    {
        return 'list';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $pageOption = $input->getOption('page');
        $sizeOption = $input->getOption('size');
        $page = is_numeric($pageOption) ? (int) $pageOption : 1;
        $size = is_numeric($sizeOption) ? (int) $sizeOption : 20;

        // TODO: 修改为正确的参数
        $pageToken = $page > 1 ? 'page_' . $page : null;
        $groups = $this->groupService->listGroups('open_id', $pageToken, $size);

        if ([] === $groups['items']) {
            $io->warning('没有找到任何群组');

            return Command::SUCCESS;
        }

        $io->title('群组列表');

        $rows = [];
        foreach ($groups['items'] as $group) {
            $rows[] = [
                $group['chat_id'] ?? 'N/A',
                $group['name'] ?? 'N/A',
                $group['member_count'] ?? 0,
                ($group['external'] ?? false) ? '是' : '否',
                $group['owner_id'] ?? 'N/A',
            ];
        }

        $io->table(
            ['群组ID', '名称', '成员数', '允许外部', '群主'],
            $rows
        );

        if ($groups['has_more']) {
            $io->note(\sprintf('还有更多群组，使用 --page=%d 查看下一页', $page + 1));
        }

        return Command::SUCCESS;
    }
}
