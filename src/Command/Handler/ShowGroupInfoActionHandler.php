<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 显示群组信息的操作处理器.
 */
final class ShowGroupInfoActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    public function getActionName(): string
    {
        return 'info';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $this->validateChatId($chatId);

        $info = $this->groupService->getGroupInfo((string) $chatId);

        $io->title(\sprintf('群组信息: %s', $info['name'] ?? $chatId));

        $io->definitionList(
            ['群组ID' => $info['chat_id'] ?? 'N/A'],
            ['名称' => $info['name'] ?? 'N/A'],
            ['描述' => $info['description'] ?? '无'],
            ['成员数' => $info['member_count'] ?? 0],
            ['群主ID' => $info['owner_id'] ?? 'N/A'],
            ['群主名称' => $info['owner_name'] ?? 'N/A'],
            ['创建时间' => isset($info['create_time']) && \is_int($info['create_time']) ? date('Y-m-d H:i:s', $info['create_time']) : 'N/A'],
            ['公开群' => 'public' === ($info['chat_mode'] ?? '') ? '是' : '否'],
            ['允许外部用户' => ($info['external'] ?? false) ? '是' : '否'],
            ['群类型' => $info['chat_type'] ?? 'N/A'],
        );

        // TODO: labels 和 chat_settings 功能尚未实现
        // 显示群标签
        // if (!info['labels'] === []) {
        //     $io->section('群标签');
        //     $io->listing($info['labels']);
        // }

        // 显示群设置
        // if (!info['chat_settings'] === []) {
        //     $io->section('群设置');
        //     $settings = [];
        //     foreach ($info['chat_settings'] as $key => $value) {
        //         $settings[] = sprintf('%s: %s', $key, is_bool($value) ? ($value ? '是' : '否') : $value);
        //     }
        //     $io->listing($settings);
        // }

        return Command::SUCCESS;
    }
}
