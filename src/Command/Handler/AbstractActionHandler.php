<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Input\InputInterface;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;

/**
 * 操作处理器抽象基类.
 */
abstract class AbstractActionHandler implements ActionHandlerInterface
{
    /**
     * 验证群组ID.
     */
    protected function validateChatId(?string $chatId): void
    {
        if (null === $chatId || '' === $chatId) {
            throw InvalidCommandUsageException::missingRequired('chat_id');
        }
    }

    /**
     * 验证并获取成员列表.
     *
     * @return array<int, string>
     */
    protected function validateAndGetMembers(InputInterface $input): array
    {
        $members = $input->getOption('member');
        if (!\is_array($members)) {
            throw InvalidCommandUsageException::invalidType('member', 'array', \gettype($members));
        }

        if ([] === $members) {
            throw InvalidCommandUsageException::missingRequired('member');
        }

        $validatedMembers = [];
        // Ensure all members are strings
        foreach ($members as $member) {
            if (!\is_string($member)) {
                throw InvalidCommandUsageException::invalidType('member item', 'string', \gettype($member));
            }
            $validatedMembers[] = $member;
        }

        return $validatedMembers;
    }

    /**
     * 获取成员类型选项.
     */
    protected function getMemberType(InputInterface $input): string
    {
        $memberTypeOption = $input->getOption('member-type');

        return \is_string($memberTypeOption) ? $memberTypeOption : 'open_id';
    }
}
