<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 创建群组的操作处理器.
 */
class CreateGroupActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
        private readonly ShowGroupInfoActionHandler $infoHandler,
    ) {
    }

    public function getActionName(): string
    {
        return 'create';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $params = $this->buildCreateGroupParams($input);
        $result = $this->groupService->createGroup($params);

        $io->success(\sprintf('群组创建成功！群组ID: %s', $result['chat_id'] ?? 'N/A'));

        if ((bool) $input->getOption('verbose')) {
            $this->infoHandler->execute($result['chat_id'], $input, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * 构建创建群组参数.
     *
     * @return array{name?: string, description?: string, chat_mode?: string, external?: bool, owner_id?: string, user_id_list?: array<string>}
     */
    private function buildCreateGroupParams(InputInterface $input): array
    {
        $name = $input->getOption('name');
        if (null === $name || '' === $name) {
            throw InvalidCommandUsageException::missingRequired('name');
        }

        // 确保name是字符串
        \assert(\is_string($name), 'name option must be string');

        $description = $input->getOption('description') ?? '';
        \assert(\is_string($description));

        $externalOption = $input->getOption('external');
        $external = null === $externalOption ? false : (bool) $externalOption;

        $params = [
            'name' => $name,
            'description' => $description,
            'chat_mode' => (bool) $input->getOption('public') ? 'public' : 'private',
            'external' => $external,
        ];

        $params = $this->addOwnerToParams($params, $input);

        return $this->addMembersToParams($params, $input);
    }

    /**
     * 添加群主到参数.
     *
     * @param array{name?: string, description?: string, chat_mode?: string, external?: bool, owner_id?: string, user_id_list?: array<string>} $params
     *
     * @return array{name?: string, description?: string, chat_mode?: string, external?: bool, owner_id?: string, user_id_list?: array<string>}
     */
    private function addOwnerToParams(array $params, InputInterface $input): array
    {
        $owner = $input->getOption('owner');
        if (null !== $owner && '' !== $owner) {
            // 确保owner是字符串
            \assert(\is_string($owner), 'owner option must be string');
            $params['owner_id'] = $owner;
        }

        return $params;
    }

    /**
     * 添加成员到参数.
     *
     * @param array{name?: string, description?: string, chat_mode?: string, external?: bool, owner_id?: string, user_id_list?: array<string>} $params
     *
     * @return array{name?: string, description?: string, chat_mode?: string, external?: bool, owner_id?: string, user_id_list?: array<string>}
     */
    private function addMembersToParams(array $params, InputInterface $input): array
    {
        $members = $input->getOption('member');
        $memberType = $this->getMemberType($input);

        if (\is_array($members) && [] !== $members) {
            // Validate all members are strings
            $validatedMembers = [];
            foreach ($members as $member) {
                if (!\is_string($member)) {
                    throw InvalidCommandUsageException::invalidType('member item', 'string', \gettype($member));
                }
                $validatedMembers[] = $member;
            }
            // GroupService期望user_id_list为string数组
            if ('user_id' === $memberType) {
                $params['user_id_list'] = $validatedMembers;
            } else {
                // 对于其他类型，保留原有的member_id_list格式
                $params['member_id_list'] = array_map(
                    fn (string $member): array => [$memberType => $member],
                    $validatedMembers
                );
            }
        }

        return $params;
    }
}
