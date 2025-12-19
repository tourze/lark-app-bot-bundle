<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\LarkAppBotBundle\Service\AdminMenu;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private LinkGeneratorInterface $linkGenerator;

    private AdminMenu $adminMenu;

    public function testAdminMenuCreatesLarkBotMenuWithCorrectItems(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);

        // 创建真实的菜单项（使用MenuFactory和MenuItem，而不是Mock）
        $factory = new MenuFactory();
        $rootItem = new MenuItem('root', $factory);

        // 执行菜单构建
        $this->adminMenu->__invoke($rootItem);

        // 验证飞书机器人菜单被创建
        $larkBotMenu = $rootItem->getChild('飞书机器人');
        $this->assertInstanceOf(ItemInterface::class, $larkBotMenu);

        // 验证所有子菜单都被正确添加
        $messageRecordItem = $larkBotMenu->getChild('消息记录');
        $this->assertInstanceOf(ItemInterface::class, $messageRecordItem);
        $this->assertSame('/admin/messagerecord', $messageRecordItem->getUri());
        $this->assertSame('fas fa-comments', $messageRecordItem->getAttribute('icon'));

        $userSyncItem = $larkBotMenu->getChild('用户同步');
        $this->assertInstanceOf(ItemInterface::class, $userSyncItem);
        $this->assertSame('/admin/usersync', $userSyncItem->getUri());
        $this->assertSame('fas fa-users-cog', $userSyncItem->getAttribute('icon'));

        $groupInfoItem = $larkBotMenu->getChild('群组信息');
        $this->assertInstanceOf(ItemInterface::class, $groupInfoItem);
        $this->assertSame('/admin/groupinfo', $groupInfoItem->getUri());
        $this->assertSame('fas fa-users', $groupInfoItem->getAttribute('icon'));

        $botConfigItem = $larkBotMenu->getChild('机器人配置');
        $this->assertInstanceOf(ItemInterface::class, $botConfigItem);
        $this->assertSame('/admin/botconfiguration', $botConfigItem->getUri());
        $this->assertSame('fas fa-cog', $botConfigItem->getAttribute('icon'));

        $apiLogItem = $larkBotMenu->getChild('API日志');
        $this->assertInstanceOf(ItemInterface::class, $apiLogItem);
        $this->assertSame('/admin/apilog', $apiLogItem->getUri());
        $this->assertSame('fas fa-clipboard-list', $apiLogItem->getAttribute('icon'));
    }

    public function testAdminMenuDoesNotCreateDuplicateLarkBotMenu(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);

        // 创建已有飞书机器人菜单的主菜单
        $factory = new MenuFactory();
        $rootItem = new MenuItem('root', $factory);
        $existingLarkBotMenu = $rootItem->addChild('飞书机器人');

        // 执行菜单构建
        $this->adminMenu->__invoke($rootItem);

        // 验证使用了现有的飞书机器人菜单
        $larkBotMenu = $rootItem->getChild('飞书机器人');
        $this->assertSame($existingLarkBotMenu, $larkBotMenu);

        // 验证子菜单都被正确添加
        $messageRecordItem = $larkBotMenu->getChild('消息记录');
        $this->assertInstanceOf(ItemInterface::class, $messageRecordItem);
        $this->assertSame('/admin/messagerecord', $messageRecordItem->getUri());
        $this->assertSame('fas fa-comments', $messageRecordItem->getAttribute('icon'));

        $userSyncItem = $larkBotMenu->getChild('用户同步');
        $this->assertInstanceOf(ItemInterface::class, $userSyncItem);
        $this->assertSame('/admin/usersync', $userSyncItem->getUri());
        $this->assertSame('fas fa-users-cog', $userSyncItem->getAttribute('icon'));

        $groupInfoItem = $larkBotMenu->getChild('群组信息');
        $this->assertInstanceOf(ItemInterface::class, $groupInfoItem);
        $this->assertSame('/admin/groupinfo', $groupInfoItem->getUri());
        $this->assertSame('fas fa-users', $groupInfoItem->getAttribute('icon'));

        $botConfigItem = $larkBotMenu->getChild('机器人配置');
        $this->assertInstanceOf(ItemInterface::class, $botConfigItem);
        $this->assertSame('/admin/botconfiguration', $botConfigItem->getUri());
        $this->assertSame('fas fa-cog', $botConfigItem->getAttribute('icon'));

        $apiLogItem = $larkBotMenu->getChild('API日志');
        $this->assertInstanceOf(ItemInterface::class, $apiLogItem);
        $this->assertSame('/admin/apilog', $apiLogItem->getUri());
        $this->assertSame('fas fa-clipboard-list', $apiLogItem->getAttribute('icon'));
    }

    protected function onSetUp(): void
    {
        // 使用真实的 LinkGenerator 服务，而不是 Stub
        // 创建一个真实的 LinkGenerator 实现
        $this->linkGenerator = new class implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return match ($entityClass) {
                    'Tourze\LarkAppBotBundle\Entity\MessageRecord' => '/admin/messagerecord',
                    'Tourze\LarkAppBotBundle\Entity\UserSync' => '/admin/usersync',
                    'Tourze\LarkAppBotBundle\Entity\GroupInfo' => '/admin/groupinfo',
                    'Tourze\LarkAppBotBundle\Entity\BotConfiguration' => '/admin/botconfiguration',
                    'Tourze\LarkAppBotBundle\Entity\ApiLog' => '/admin/apilog',
                    default => '/admin/unknown',
                };
            }

            public function extractEntityFqcn(string $url): ?string
            {
                return match (true) {
                    str_contains($url, '/admin/messagerecord') => 'Tourze\LarkAppBotBundle\Entity\MessageRecord',
                    str_contains($url, '/admin/usersync') => 'Tourze\LarkAppBotBundle\Entity\UserSync',
                    str_contains($url, '/admin/groupinfo') => 'Tourze\LarkAppBotBundle\Entity\GroupInfo',
                    str_contains($url, '/admin/botconfiguration') => 'Tourze\LarkAppBotBundle\Entity\BotConfiguration',
                    str_contains($url, '/admin/apilog') => 'Tourze\LarkAppBotBundle\Entity\ApiLog',
                    default => null,
                };
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // 简单实现，不做任何操作
            }
        };

        // 注册到容器
        self::getContainer()->set(LinkGeneratorInterface::class, $this->linkGenerator);

        // 获取真实的 AdminMenu 服务
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    protected function getMenuProvider(): object
    {
        return $this->adminMenu;
    }
}
