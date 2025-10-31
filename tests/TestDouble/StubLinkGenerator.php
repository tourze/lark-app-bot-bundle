<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\LarkAppBotBundle\Entity\ApiLog;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;
use Tourze\LarkAppBotBundle\Entity\UserSync;

/**
 * LinkGenerator 的测试桩实现.
 *
 * @internal 仅用于测试
 */
final class StubLinkGenerator implements LinkGeneratorInterface
{
    /** @var string 仪表板控制器类名 */
    private string $dashboardControllerFqcn = '';

    public function getCurdListPage(string $entityClass): string
    {
        return match ($entityClass) {
            MessageRecord::class => '/admin/messagerecord',
            UserSync::class => '/admin/usersync',
            GroupInfo::class => '/admin/groupinfo',
            BotConfiguration::class => '/admin/botconfiguration',
            ApiLog::class => '/admin/apilog',
            default => '/admin/unknown',
        };
    }

    public function extractEntityFqcn(string $url): ?string
    {
        return match (true) {
            str_contains($url, '/admin/messagerecord') => MessageRecord::class,
            str_contains($url, '/admin/usersync') => UserSync::class,
            str_contains($url, '/admin/groupinfo') => GroupInfo::class,
            str_contains($url, '/admin/botconfiguration') => BotConfiguration::class,
            str_contains($url, '/admin/apilog') => ApiLog::class,
            default => null,
        };
    }

    public function setDashboard(string $dashboardControllerFqcn): void
    {
        $this->dashboardControllerFqcn = $dashboardControllerFqcn;
    }

    /**
     * 获取仪表板控制器类名
     */
    public function getDashboard(): string
    {
        return $this->dashboardControllerFqcn;
    }
}
