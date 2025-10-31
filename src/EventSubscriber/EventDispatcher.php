<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Event\GroupEvent;
use Tourze\LarkAppBotBundle\Event\GroupMemberEvent;
use Tourze\LarkAppBotBundle\Event\LarkEvent;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Event\MessageReactionEvent;
use Tourze\LarkAppBotBundle\Event\UserEvent;

/**
 * 飞书事件分发器.
 *
 * 将飞书的事件转换为Symfony事件并分发
 */
class EventDispatcher
{
    /**
     * 事件映射表.
     *
     * @var array<string, string>
     */
    private array $eventMapping = [
        'im.message.receive_v1' => MessageEvent::class,
        'im.message.reaction.created_v1' => MessageReactionEvent::class,
        'im.message.reaction.deleted_v1' => MessageReactionEvent::class,
        'contact.user.created_v3' => UserEvent::class,
        'contact.user.updated_v3' => UserEvent::class,
        'contact.user.deleted_v3' => UserEvent::class,
        'im.chat.disbanded_v1' => GroupEvent::class,
        'im.chat.updated_v1' => GroupEvent::class,
        'im.chat.member.bot.added_v1' => GroupMemberEvent::class,
        'im.chat.member.bot.deleted_v1' => GroupMemberEvent::class,
        'im.chat.member.user.added_v1' => GroupMemberEvent::class,
        'im.chat.member.user.withdrawn_v1' => GroupMemberEvent::class,
        'im.chat.member.user.deleted_v1' => GroupMemberEvent::class,
        'application.bot.menu_v6' => MenuEvent::class,
    ];

    /**
     * 事件监听器优先级.
     *
     * @var array<string, array<array{listener: callable, priority: int}>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly EventDispatcherInterface $symfonyEventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 分发事件.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $context
     */
    public function dispatch(string $eventType, array $eventData, array $context = []): void
    {
        $this->logEventDispatch($eventType, $eventData, $context);

        // 创建对应的事件对象
        $event = $this->createEvent($eventType, $eventData, $context);

        // 分发到多个目标
        $this->dispatchToTargets($event, $eventType);
    }

    /**
     * 注册事件监听器.
     *
     * @param callable $listener 监听器回调
     * @param int      $priority 优先级（数字越大优先级越高）
     */
    public function addListener(string $eventType, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }

        $this->listeners[$eventType][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // 按优先级排序
        usort($this->listeners[$eventType], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // 同时注册到Symfony事件分发器
        $this->symfonyEventDispatcher->addListener($eventType, $listener, $priority);
    }

    /**
     * 移除事件监听器.
     */
    public function removeListener(string $eventType, callable $listener): void
    {
        if (!isset($this->listeners[$eventType])) {
            return;
        }

        $this->listeners[$eventType] = array_filter(
            $this->listeners[$eventType],
            fn ($item) => $item['listener'] !== $listener
        );

        // 同时从Symfony事件分发器移除
        $this->symfonyEventDispatcher->removeListener($eventType, $listener);
    }

    /**
     * 获取事件监听器.
     *
     * @return array<callable>
     */
    public function getListeners(string $eventType): array
    {
        if (!isset($this->listeners[$eventType])) {
            return [];
        }

        return array_column($this->listeners[$eventType], 'listener');
    }

    /**
     * 检查是否有监听器.
     */
    public function hasListeners(string $eventType): bool
    {
        return isset($this->listeners[$eventType]) && [] !== $this->listeners[$eventType];
    }

    /**
     * 获取支持的事件类型.
     *
     * @return array<string>
     */
    public function getSupportedEventTypes(): array
    {
        return array_keys($this->eventMapping);
    }

    /**
     * 注册事件映射.
     */
    public function registerEventMapping(string $eventType, string $eventClass): void
    {
        $this->eventMapping[$eventType] = $eventClass;
    }

    /**
     * 记录事件分发日志.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $context
     */
    private function logEventDispatch(string $eventType, array $eventData, array $context): void
    {
        $this->logger->debug('分发飞书事件', [
            'event_type' => $eventType,
            'event_data' => $eventData,
            'context' => $context,
        ]);
    }

    /**
     * 分发事件到多个目标.
     */
    private function dispatchToTargets(LarkEvent $event, string $eventType): void
    {
        // 分发具体的事件对象（Symfony 6+只使用一个参数）
        // 事件名称通过事件对象的getEventName()方法或类名自动识别
        $this->symfonyEventDispatcher->dispatch($event);

        // 注意：Symfony 6+的事件系统会自动使用事件类名作为事件名称
        // 如果需要额外的事件名称支持，可以在监听器中通过事件对象的getType()方法获取
    }

    /**
     * 创建事件对象.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $context
     */
    private function createEvent(string $eventType, array $eventData, array $context): LarkEvent
    {
        $eventClass = $this->eventMapping[$eventType] ?? GenericEvent::class;

        if (!class_exists($eventClass)) {
            $this->logger->warning('事件类不存在，使用通用事件', [
                'event_type' => $eventType,
                'event_class' => $eventClass,
            ]);
            $eventClass = GenericEvent::class;
        }

        try {
            $event = new $eventClass($eventType, $eventData, $context);
            \assert($event instanceof LarkEvent);

            return $event;
        } catch (\Exception $e) {
            $this->logger->error('创建事件对象失败', [
                'event_type' => $eventType,
                'event_class' => $eventClass,
                'error' => $e->getMessage(),
            ]);

            return new GenericEvent($eventType, $eventData, $context);
        }
    }
}
