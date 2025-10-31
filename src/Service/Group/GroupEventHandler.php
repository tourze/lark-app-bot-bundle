<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Group;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GroupEvent;
use Tourze\LarkAppBotBundle\Event\GroupMemberEvent;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 群组事件处理器.
 *
 * 处理群组相关的各种事件
 */
#[Autoconfigure(public: true)]
class GroupEventHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageService $messageService,
        private readonly GroupService $groupService,
    ) {
    }

    /**
     * 处理群组创建事件.
     */
    public function handleGroupCreated(GroupEvent $event): void
    {
        $chatId = $event->getChatId();
        $this->logger->info('处理群组创建事件', [
            'chat_id' => $chatId,
            'operator_id' => $event->getOperatorId(),
        ]);

        try {
            // 获取群组信息
            $groupInfo = $this->groupService->getGroup($chatId);

            // 发送欢迎消息
            $this->sendWelcomeMessage($chatId, $groupInfo);

            // 触发自定义事件，供应用程序进一步处理
            $customEvent = new GroupEvent('group.created', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理群组创建事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理群组解散事件.
     */
    public function handleGroupDisbanded(GroupEvent $event): void
    {
        $chatId = $event->getChatId();
        $this->logger->info('处理群组解散事件', [
            'chat_id' => $chatId,
            'operator_id' => $event->getOperatorId(),
        ]);

        try {
            // 触发自定义事件
            $customEvent = new GroupEvent('group.disbanded', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理群组解散事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理群组更新事件.
     */
    public function handleGroupUpdated(GroupEvent $event): void
    {
        $chatId = $event->getChatId();
        $this->logger->info('处理群组更新事件', [
            'chat_id' => $chatId,
            'operator_id' => $event->getOperatorId(),
            'i18n_names' => $event->getI18nNames(),
        ]);

        try {
            // 触发自定义事件
            $customEvent = new GroupEvent('group.updated', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理群组更新事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理群成员加入事件.
     */
    public function handleMemberJoined(GroupMemberEvent $event): void
    {
        $chatId = $event->getChatId();
        $users = $event->getUsers();

        $this->logger->info('处理群成员加入事件', [
            'chat_id' => $chatId,
            'user_count' => \count($users),
        ]);

        try {
            // 发送欢迎消息给新成员
            foreach ($users as $user) {
                $this->sendMemberWelcomeMessage($chatId, $user);
            }

            // 触发自定义事件
            $customEvent = new GroupMemberEvent('group.member.joined', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理群成员加入事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理群成员离开事件.
     */
    public function handleMemberLeft(GroupMemberEvent $event): void
    {
        $chatId = $event->getChatId();
        $users = $event->getUsers();

        $this->logger->info('处理群成员离开事件', [
            'chat_id' => $chatId,
            'user_count' => \count($users),
        ]);

        try {
            // 触发自定义事件
            $customEvent = new GroupMemberEvent('group.member.left', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理群成员离开事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理机器人被加入群组事件.
     */
    public function handleBotAdded(GroupMemberEvent $event): void
    {
        $chatId = $event->getChatId();
        $this->logger->info('机器人被加入群组', [
            'chat_id' => $chatId,
            'operator_id' => $event->getOperatorId(),
        ]);

        try {
            // 获取群组信息
            $groupInfo = $this->groupService->getGroup($chatId);

            // 发送机器人介绍消息
            $this->sendBotIntroMessage($chatId, $groupInfo);

            // 触发自定义事件
            $customEvent = new GroupMemberEvent('group.bot.added', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理机器人加入事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理机器人被移出群组事件.
     */
    public function handleBotRemoved(GroupMemberEvent $event): void
    {
        $chatId = $event->getChatId();
        $this->logger->info('机器人被移出群组', [
            'chat_id' => $chatId,
            'operator_id' => $event->getOperatorId(),
        ]);

        try {
            // 触发自定义事件
            $customEvent = new GroupMemberEvent('group.bot.removed', $event->getData());
            $this->eventDispatcher->dispatch($customEvent);
        } catch (\Exception $e) {
            $this->logger->error('处理机器人移出事件失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送群组欢迎消息.
     *
     * @param array<string, mixed> $groupInfo
     */
    private function sendWelcomeMessage(string $chatId, array $groupInfo): void
    {
        try {
            $message = \sprintf(
                "欢迎来到群组【%s】！\n\n%s",
                $groupInfo['name'] ?? '未命名群组',
                $groupInfo['description'] ?? '让我们一起愉快地交流吧！'
            );

            $this->messageService->sendText($chatId, $message, null, 'chat_id');
        } catch (\Exception $e) {
            $this->logger->error('发送群组欢迎消息失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送成员欢迎消息.
     *
     * @param array{user_id?: string, name?: string, union_id?: string, open_id?: string} $user
     */
    private function sendMemberWelcomeMessage(string $chatId, array $user): void
    {
        try {
            $userName = $user['name'] ?? '新成员';
            $message = \sprintf('欢迎 %s 加入群组！', $userName);

            $this->messageService->sendText($chatId, $message, null, 'chat_id');
        } catch (\Exception $e) {
            $this->logger->error('发送成员欢迎消息失败', [
                'chat_id' => $chatId,
                'user' => $user,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送机器人介绍消息.
     *
     * @param array<string, mixed> $groupInfo
     */
    private function sendBotIntroMessage(string $chatId, array $groupInfo): void
    {
        try {
            $message = "大家好！我是飞书机器人助手。\n\n" .
                "我可以帮助大家：\n" .
                "• 回答问题和提供帮助\n" .
                "• 处理群组管理任务\n" .
                "• 执行自定义命令\n\n" .
                '输入 /help 查看所有可用命令。';

            $this->messageService->sendText($chatId, $message, null, 'chat_id');
        } catch (\Exception $e) {
            $this->logger->error('发送机器人介绍消息失败', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
