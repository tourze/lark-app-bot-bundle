<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\LarkAppBotBundle\Service\Menu\MenuService;

/**
 * 菜单事件订阅器
 * 负责监听和分发菜单点击事件.
 */
#[Autoconfigure(public: true)]
final readonly class MenuEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MenuService $menuService,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MenuEvent::class => ['onMenuEvent', 10],
        ];
    }

    /**
     * 处理菜单事件.
     */
    public function onMenuEvent(MenuEvent $event): void
    {
        $this->logger->debug('收到菜单事件', [
            'event_key' => $event->getEventKey(),
            'operator_id' => $event->getOperatorOpenId(),
            'event_id' => $event->getEventId(),
        ]);

        try {
            // 委托给MenuService处理
            $this->menuService->handleMenuEvent($event);
        } catch (\Exception $e) {
            $this->logger->error('处理菜单事件时发生异常', [
                'event_key' => $event->getEventKey(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
