<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 添加群成员的操作处理器.
 */
class AddMembersActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    public function getActionName(): string
    {
        return 'add-member';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $this->validateChatId($chatId);
        $members = $this->validateAndGetMembers($input);
        $memberType = $this->getMemberType($input);

        $result = $this->groupService->addMembers((string) $chatId, $members, $memberType);

        $this->showAddMembersResult($io, $members, $result);

        return Command::SUCCESS;
    }

    /**
     * 显示添加成员结果.
     *
     * @param array<string>        $members
     * @param array<string, mixed> $result
     */
    private function showAddMembersResult(SymfonyStyle $io, array $members, array $result): void
    {
        $io->success(\sprintf('成功添加 %d 个成员到群组', \count($members)));

        $invalidList = $result['invalid_id_list'] ?? [];
        if (\is_array($invalidList) && [] !== $invalidList) {
            $io->warning('以下成员ID无效：');
            $io->listing($invalidList);
        }
    }
}
