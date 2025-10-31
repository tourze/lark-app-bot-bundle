<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\LarkAppBotBundle\Entity\UserSync;

/**
 * 飞书用户同步记录测试数据夹具.
 *
 * 创建不同状态的用户同步记录，用于演示和测试用户同步功能
 */
class UserSyncFixtures extends Fixture
{
    // 引用常量定义
    public const USER_SYNC_SUCCESS_REFERENCE = 'user-sync-success';
    public const USER_SYNC_PENDING_REFERENCE = 'user-sync-pending';
    public const USER_SYNC_FAILED_REFERENCE = 'user-sync-failed';

    public function load(ObjectManager $manager): void
    {
        // 创建成功同步的用户记录
        $user1 = new UserSync();
        $user1->setUserId('ou_success_user_001');
        $user1->setOpenId('od_success_open_001');
        $user1->setUnionId('on_success_union_001');
        $user1->setName('张三');
        $user1->setEmail('zhangsan@company.com');
        $user1->setMobile('13800138001');
        $user1->setDepartmentIds(['dep_001', 'dep_002']);
        $user1->setSyncStatus('success');
        $user1->setSyncAt(new \DateTimeImmutable('-1 hour'));

        $manager->persist($user1);

        // 创建另一个成功同步的用户记录
        $user2 = new UserSync();
        $user2->setUserId('ou_success_user_002');
        $user2->setOpenId('od_success_open_002');
        $user2->setUnionId('on_success_union_002');
        $user2->setName('李四');
        $user2->setEmail('lisi@company.com');
        $user2->setMobile('13800138002');
        $user2->setDepartmentIds(['dep_003']);
        $user2->setSyncStatus('success');
        $user2->setSyncAt(new \DateTimeImmutable('-2 hours'));

        $manager->persist($user2);

        // 创建待同步的用户记录
        $user3 = new UserSync();
        $user3->setUserId('ou_pending_user_001');
        $user3->setOpenId('od_pending_open_001');
        $user3->setUnionId('on_pending_union_001');
        $user3->setName('王五');
        $user3->setEmail('wangwu@company.com');
        $user3->setMobile('13800138003');
        $user3->setDepartmentIds(['dep_004', 'dep_005']);
        $user3->setSyncStatus('pending');

        $manager->persist($user3);

        // 创建同步失败的用户记录
        $user4 = new UserSync();
        $user4->setUserId('ou_failed_user_001');
        $user4->setOpenId('od_failed_open_001');
        $user4->setUnionId('on_failed_union_001');
        $user4->setName('赵六');
        $user4->setEmail('zhaoliu@company.com');
        $user4->setMobile('13800138004');
        $user4->setDepartmentIds(['dep_006']);
        $user4->setSyncStatus('failed');
        $user4->setSyncAt(new \DateTimeImmutable('-30 minutes'));
        $user4->setErrorMessage('API调用超时，无法获取用户详细信息');

        $manager->persist($user4);

        // 创建没有邮箱的用户记录
        $user5 = new UserSync();
        $user5->setUserId('ou_no_email_user_001');
        $user5->setOpenId('od_no_email_open_001');
        $user5->setUnionId('on_no_email_union_001');
        $user5->setName('孙七');
        $user5->setMobile('13800138005');
        $user5->setDepartmentIds(['dep_007']);
        $user5->setSyncStatus('success');
        $user5->setSyncAt(new \DateTimeImmutable('-3 hours'));

        $manager->persist($user5);

        // 创建外部用户记录
        $user6 = new UserSync();
        $user6->setUserId('ou_external_user_001');
        $user6->setOpenId('od_external_open_001');
        $user6->setUnionId('on_external_union_001');
        $user6->setName('外部合作伙伴-陈八');
        $user6->setEmail('chenba@partner.com');
        $user6->setMobile('13800138006');
        $user6->setDepartmentIds(null); // 外部用户可能没有部门
        $user6->setSyncStatus('success');
        $user6->setSyncAt(new \DateTimeImmutable('-4 hours'));

        $manager->persist($user6);

        // 创建同步失败（权限不足）的用户记录
        $user7 = new UserSync();
        $user7->setUserId('ou_permission_denied_001');
        $user7->setOpenId('od_permission_denied_001');
        $user7->setUnionId('on_permission_denied_001');
        $user7->setName('权限受限用户');
        $user7->setEmail('restricted@company.com');
        $user7->setDepartmentIds(['dep_secret']);
        $user7->setSyncStatus('failed');
        $user7->setSyncAt(new \DateTimeImmutable('-15 minutes'));
        $user7->setErrorMessage('权限不足，无法访问该用户信息');

        $manager->persist($user7);

        // 创建待重试的用户记录
        $user8 = new UserSync();
        $user8->setUserId('ou_retry_user_001');
        $user8->setOpenId('od_retry_open_001');
        $user8->setUnionId('on_retry_union_001');
        $user8->setName('待重试用户');
        $user8->setEmail('retry@company.com');
        $user8->setMobile('13800138008');
        $user8->setDepartmentIds(['dep_008']);
        $user8->setSyncStatus('pending');

        $manager->persist($user8);

        // 创建多部门用户记录
        $user9 = new UserSync();
        $user9->setUserId('ou_multi_dept_user_001');
        $user9->setOpenId('od_multi_dept_open_001');
        $user9->setUnionId('on_multi_dept_union_001');
        $user9->setName('多部门用户-周九');
        $user9->setEmail('zhoujiu@company.com');
        $user9->setMobile('13800138009');
        $user9->setDepartmentIds(['dep_009', 'dep_010', 'dep_011']);
        $user9->setSyncStatus('success');
        $user9->setSyncAt(new \DateTimeImmutable('-5 hours'));

        $manager->persist($user9);

        // 创建最近同步的用户记录
        $user10 = new UserSync();
        $user10->setUserId('ou_recent_user_001');
        $user10->setOpenId('od_recent_open_001');
        $user10->setUnionId('on_recent_union_001');
        $user10->setName('最近同步用户-吴十');
        $user10->setEmail('wushi@company.com');
        $user10->setMobile('13800138010');
        $user10->setDepartmentIds(['dep_012']);
        $user10->setSyncStatus('success');
        $user10->setSyncAt(new \DateTimeImmutable('-10 minutes'));

        $manager->persist($user10);

        $manager->flush();

        // 设置引用，供其他 Fixtures 使用
        $this->addReference(self::USER_SYNC_SUCCESS_REFERENCE, $user1);
        $this->addReference(self::USER_SYNC_PENDING_REFERENCE, $user3);
        $this->addReference(self::USER_SYNC_FAILED_REFERENCE, $user4);
    }
}
