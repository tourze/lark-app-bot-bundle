<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;

/**
 * 飞书群组信息测试数据夹具.
 *
 * 创建不同类型的群组信息，用于演示和测试群组管理功能
 */
class GroupInfoFixtures extends Fixture
{
    // 引用常量定义
    public const GROUP_INFO_INTERNAL_REFERENCE = 'group-info-internal';
    public const GROUP_INFO_EXTERNAL_REFERENCE = 'group-info-external';
    public const GROUP_INFO_LARGE_REFERENCE = 'group-info-large';

    public function load(ObjectManager $manager): void
    {
        // 创建内部工作群组
        $group1 = new GroupInfo();
        $group1->setChatId('oc_internal_team_001');
        $group1->setName('产品开发团队');
        $group1->setDescription('产品开发相关的日常沟通群组，包括需求讨论、进度同步等');
        $group1->setOwnerId('ou_owner_001');
        $group1->setMemberCount(15);
        $group1->setBotCount(2);
        $group1->setChatType('group');
        $group1->setExternal(false);

        $manager->persist($group1);

        // 创建外部合作群组
        $group2 = new GroupInfo();
        $group2->setChatId('oc_external_partner_001');
        $group2->setName('外部合作伙伴交流群');
        $group2->setDescription('与外部合作伙伴的项目协作群组');
        $group2->setOwnerId('ou_owner_002');
        $group2->setMemberCount(8);
        $group2->setBotCount(1);
        $group2->setChatType('group');
        $group2->setExternal(true);

        $manager->persist($group2);

        // 创建大型全员群组
        $group3 = new GroupInfo();
        $group3->setChatId('oc_company_all_staff');
        $group3->setName('公司全员群');
        $group3->setDescription('公司全体员工群组，用于发布重要通知和公司动态');
        $group3->setOwnerId('ou_owner_003');
        $group3->setMemberCount(250);
        $group3->setBotCount(5);
        $group3->setChatType('group');
        $group3->setExternal(false);

        $manager->persist($group3);

        // 创建技术讨论群
        $group4 = new GroupInfo();
        $group4->setChatId('oc_tech_discussion_001');
        $group4->setName('技术讨论群');
        $group4->setDescription('技术相关问题讨论和知识分享');
        $group4->setOwnerId('ou_owner_004');
        $group4->setMemberCount(32);
        $group4->setBotCount(3);
        $group4->setChatType('group');
        $group4->setExternal(false);

        $manager->persist($group4);

        // 创建客户服务群
        $group5 = new GroupInfo();
        $group5->setChatId('oc_customer_service_001');
        $group5->setName('客户服务支持');
        $group5->setDescription('客户服务团队内部沟通群组');
        $group5->setOwnerId('ou_owner_005');
        $group5->setMemberCount(12);
        $group5->setBotCount(4);
        $group5->setChatType('group');
        $group5->setExternal(false);

        $manager->persist($group5);

        // 创建项目管理群
        $group6 = new GroupInfo();
        $group6->setChatId('oc_project_alpha_001');
        $group6->setName('Alpha项目群');
        $group6->setDescription('Alpha项目相关的讨论和进度跟踪');
        $group6->setOwnerId('ou_owner_006');
        $group6->setMemberCount(20);
        $group6->setBotCount(2);
        $group6->setChatType('group');
        $group6->setExternal(false);

        $manager->persist($group6);

        // 创建小型测试群
        $group7 = new GroupInfo();
        $group7->setChatId('oc_small_test_group');
        $group7->setName('测试小组');
        $group7->setDescription('用于功能测试的小型群组');
        $group7->setOwnerId('ou_owner_007');
        $group7->setMemberCount(5);
        $group7->setBotCount(1);
        $group7->setChatType('group');
        $group7->setExternal(false);

        $manager->persist($group7);

        // 创建跨部门协作群
        $group8 = new GroupInfo();
        $group8->setChatId('oc_cross_department_001');
        $group8->setName('跨部门协作群');
        $group8->setDescription('多个部门之间的协作沟通群组');
        $group8->setOwnerId('ou_owner_008');
        $group8->setMemberCount(28);
        $group8->setBotCount(3);
        $group8->setChatType('group');
        $group8->setExternal(false);

        $manager->persist($group8);

        // 创建临时项目群
        $group9 = new GroupInfo();
        $group9->setChatId('oc_temp_project_beta');
        $group9->setName('Beta临时项目群');
        $group9->setDescription('Beta项目的临时沟通群组，项目结束后会解散');
        $group9->setOwnerId('ou_owner_009');
        $group9->setMemberCount(10);
        $group9->setBotCount(1);
        $group9->setChatType('group');
        $group9->setExternal(false);

        $manager->persist($group9);

        // 创建外部客户群
        $group10 = new GroupInfo();
        $group10->setChatId('oc_external_client_001');
        $group10->setName('重要客户沟通群');
        $group10->setDescription('与重要客户的专属沟通群组');
        $group10->setOwnerId('ou_owner_010');
        $group10->setMemberCount(6);
        $group10->setBotCount(2);
        $group10->setChatType('group');
        $group10->setExternal(true);

        $manager->persist($group10);

        $manager->flush();

        // 设置引用，供其他 Fixtures 使用
        $this->addReference(self::GROUP_INFO_INTERNAL_REFERENCE, $group1);
        $this->addReference(self::GROUP_INFO_EXTERNAL_REFERENCE, $group2);
        $this->addReference(self::GROUP_INFO_LARGE_REFERENCE, $group3);
    }
}
