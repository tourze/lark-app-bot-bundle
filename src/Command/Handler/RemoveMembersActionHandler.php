<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 移除群成员的操作处理器.
 */
class RemoveMembersActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    public function getActionName(): string
    {
        return 'remove-member';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $this->validateChatId($chatId);
        $members = $this->validateAndGetMembers($input);
        $memberType = $this->getMemberType($input);

        $this->groupService->removeMembers((string) $chatId, $members, $memberType);
        $io->success(\sprintf('成功从群组移除 %d 个成员', \count($members)));

        return Command::SUCCESS;
    }
}
