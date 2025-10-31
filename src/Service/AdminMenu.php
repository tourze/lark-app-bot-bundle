<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\LarkAppBotBundle\Entity\ApiLog;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;
use Tourze\LarkAppBotBundle\Entity\UserSync;

#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('飞书机器人')) {
            $item->addChild('飞书机器人');
        }

        $larkMenu = $item->getChild('飞书机器人');
        if (null === $larkMenu) {
            return;
        }

        $larkMenu
            ->addChild('消息记录')
            ->setUri($this->linkGenerator->getCurdListPage(MessageRecord::class))
            ->setAttribute('icon', 'fas fa-comments')
        ;

        $larkMenu
            ->addChild('用户同步')
            ->setUri($this->linkGenerator->getCurdListPage(UserSync::class))
            ->setAttribute('icon', 'fas fa-users-cog')
        ;

        $larkMenu
            ->addChild('群组信息')
            ->setUri($this->linkGenerator->getCurdListPage(GroupInfo::class))
            ->setAttribute('icon', 'fas fa-users')
        ;

        $larkMenu
            ->addChild('机器人配置')
            ->setUri($this->linkGenerator->getCurdListPage(BotConfiguration::class))
            ->setAttribute('icon', 'fas fa-cog')
        ;

        $larkMenu
            ->addChild('API日志')
            ->setUri($this->linkGenerator->getCurdListPage(ApiLog::class))
            ->setAttribute('icon', 'fas fa-clipboard-list')
        ;
    }
}
