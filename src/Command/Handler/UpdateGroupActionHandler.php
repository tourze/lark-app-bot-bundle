<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;

/**
 * 更新群组的操作处理器.
 */
final class UpdateGroupActionHandler extends AbstractActionHandler
{
    public function __construct(
        private readonly GroupService $groupService,
        private readonly ShowGroupInfoActionHandler $infoHandler,
    ) {
    }

    public function getActionName(): string
    {
        return 'update';
    }

    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int
    {
        $this->validateChatId($chatId);
        $params = $this->buildUpdateGroupParams($input);

        $this->groupService->updateGroup((string) $chatId, $params);
        $io->success('群组信息更新成功！');

        if ((bool) $input->getOption('verbose')) {
            $this->infoHandler->execute($chatId, $input, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * 构建更新群组参数.
     *
     * @return array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string}
     *
     * @phpstan-return array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string}
     */
    private function buildUpdateGroupParams(InputInterface $input): array
    {
        /** @var array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string} */
        $params = [];

        $params = $this->addNameToParams($params, $input);
        $params = $this->addDescriptionToParams($params, $input);
        $params = $this->addOwnerToParams($params, $input);

        if ([] === $params) {
            throw InvalidCommandUsageException::invalidValue('update', '无更新内容', '至少需要提供一个要更新的字段');
        }

        return $params;
    }

    /**
     * 添加名称到参数.
     *
     * @param array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string} $params
     *
     * @return array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string}
     */
    private function addNameToParams(array $params, InputInterface $input): array
    {
        $name = $input->getOption('name');
        if (null !== $name && \is_string($name) && '' !== $name) {
            $params['name'] = $name;
        }

        return $params;
    }

    /**
     * 添加描述到参数.
     *
     * @param array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string} $params
     *
     * @return array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string}
     */
    private function addDescriptionToParams(array $params, InputInterface $input): array
    {
        $description = $input->getOption('description');
        if (null !== $description && '' !== $description) {
            \assert(\is_string($description));
            $params['description'] = $description;
        }

        return $params;
    }

    /**
     * 添加群主到参数.
     *
     * @param array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string} $params
     *
     * @return array{avatar?: string, name?: string, description?: string, i18n_names?: array<string, string>, add_member_permission?: string, share_card_permission?: string, at_all_permission?: string, edit_permission?: string, owner_id?: string}
     */
    private function addOwnerToParams(array $params, InputInterface $input): array
    {
        $owner = $input->getOption('owner');
        if (null !== $owner && '' !== $owner) {
            \assert(\is_string($owner));
            $params['owner_id'] = $owner;
        }

        return $params;
    }
}
