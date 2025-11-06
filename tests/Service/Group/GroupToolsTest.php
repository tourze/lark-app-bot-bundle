<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;
use Tourze\LarkAppBotBundle\Service\Group\GroupTools;

/**
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（GroupService）
 * GroupService 是具体类，需要 Mock 以避免对具体群组服务的依赖
 * 测试重点在于验证 GroupTools 的批量操作逻辑，而不是 GroupService 的具体实现
 */
#[CoversClass(GroupTools::class)]
final class GroupToolsTest extends TestCase
{
    private GroupTools $groupTools;

    private GroupService&MockObject $groupService;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->groupService = $this->createMock(GroupService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->groupTools = new GroupTools($this->groupService, $this->logger);
    }

    public function testBatchAddMembersSuccess(): void
    {
        $chatId = 'oc_123456';
        $memberIds = array_map(fn ($i) => "user{$i}", range(1, 150));

        // 期望调用两次（100 + 50）
        $this->groupService->expects($this->exactly(2))
            ->method('addMembers')
            ->willReturnOnConsecutiveCalls(
                ['invalid_id_list' => ['user50'], 'not_existed_id_list' => []],
                ['invalid_id_list' => [], 'not_existed_id_list' => ['user120']]
            )
        ;

        $result = $this->groupTools->batchAddMembers($chatId, $memberIds, 100);

        $this->assertSame(150, $result['total']);
        $this->assertSame(148, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame(['user50'], $result['invalid_ids']);
        $this->assertSame(['user120'], $result['not_existed_ids']);
    }

    public function testBatchAddMembersWithInvalidBatchSize(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('批次大小必须在1-200之间');

        $this->groupTools->batchAddMembers('oc_123456', ['user1'], 250);
    }

    public function testBatchRemoveMembersSuccess(): void
    {
        $chatId = 'oc_123456';
        $memberIds = array_map(fn ($i) => "user{$i}", range(1, 50));

        $this->groupService->expects($this->once())
            ->method('removeMembers')
            ->with($chatId, $memberIds, 'user_id')
            ->willReturn(['invalid_id_list' => ['user10', 'user20']])
        ;

        $result = $this->groupTools->batchRemoveMembers($chatId, $memberIds);

        $this->assertSame(50, $result['total']);
        $this->assertSame(48, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame(['user10', 'user20'], $result['invalid_ids']);
    }

    public function testCreateGroupFromTemplateSuccess(): void
    {
        $templateName = 'team';
        $overrides = ['name' => '研发团队群'];

        $expectedParams = [
            'name' => '研发团队群',
            'description' => '团队内部沟通协作',
            'chat_mode' => 'group',
            'group_message_type' => 'all',
            'add_member_permission' => 'only_owner',
            'share_card_permission' => 'allowed',
            'at_all_permission' => 'only_owner',
            'edit_permission' => 'only_owner',
            'membership_approval' => 'no_approval'];

        $this->groupService->expects($this->once())
            ->method('createGroup')
            ->with($expectedParams)
            ->willReturn(['chat_id' => 'oc_123456', 'name' => '研发团队群'])
        ;

        $result = $this->groupTools->createGroupFromTemplate($templateName, $overrides);

        $this->assertSame('oc_123456', $result['chat_id']);
        $this->assertSame('研发团队群', $result['name'] ?? '');
    }

    public function testCreateGroupFromInvalidTemplate(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('未知的群组模板：invalid_template');

        $this->groupTools->createGroupFromTemplate('invalid_template');
    }

    public function testGetAvailableTemplates(): void
    {
        $templates = $this->groupTools->getAvailableTemplates();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('team', $templates);
        $this->assertArrayHasKey('project', $templates);
        $this->assertArrayHasKey('announcement', $templates);
        $this->assertArrayHasKey('discussion', $templates);
    }

    public function testExportGroupData(): void
    {
        $chatId = 'oc_123456';
        $groupInfo = [
            'chat_id' => $chatId,
            'name' => '测试群组',
            'description' => '测试描述'];
        $members = [
            ['member_id' => 'user1', 'name' => '用户1'],
            ['member_id' => 'user2', 'name' => '用户2']];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with($chatId)
            ->willReturn($groupInfo)
        ;

        $this->groupService->expects($this->once())
            ->method('getAllMembers')
            ->with($chatId)
            ->willReturn($members)
        ;

        $result = $this->groupTools->exportGroupData($chatId);

        $this->assertSame($groupInfo, $result['group_info']);
        $this->assertSame($members, $result['members'] ?? []);
        $this->assertSame(2, $result['member_count'] ?? 0);
        $this->assertNotEmpty($result['export_time']);
    }

    public function testExportGroupDataAsCsv(): void
    {
        $chatId = 'oc_123456';
        $groupInfo = [
            'chat_id' => $chatId,
            'name' => '测试群组',
            'description' => '测试描述',
            'owner_id' => 'owner123'];
        $members = [
            ['member_id' => 'user1', 'name' => '用户1', 'tenant_key' => 'tenant1'],
            ['member_id' => 'user2', 'name' => '用户2', 'tenant_key' => 'tenant2']];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with($chatId)
            ->willReturn($groupInfo)
        ;

        $this->groupService->expects($this->once())
            ->method('getAllMembers')
            ->with($chatId)
            ->willReturn($members)
        ;

        $csv = $this->groupTools->exportGroupDataAsCsv($chatId);

        $this->assertStringContainsString('群组信息', $csv);
        $this->assertStringContainsString($chatId, $csv);
        $this->assertStringContainsString('测试群组', $csv);
        $this->assertStringContainsString('成员列表', $csv);
        $this->assertStringContainsString('user1,用户1,tenant1', $csv);
        $this->assertStringContainsString('user2,用户2,tenant2', $csv);
    }

    public function testAnalyzeGroup(): void
    {
        $chatId = 'oc_123456';
        $groupInfo = [
            'chat_id' => $chatId,
            'name' => '测试群组',
            'description' => '测试描述',
            'owner_id' => 'owner123',
            'chat_type' => 'group',
            'external' => false,
            'user_count' => 10,
            'bot_count' => 1,
            'add_member_permission' => 'only_owner',
            'share_card_permission' => 'allowed',
            'at_all_permission' => 'only_owner',
            'edit_permission' => 'only_owner',
            'group_message_type' => 'all'];
        $members = [
            ['member_id' => 'user1', 'name' => '用户1', 'tenant_key' => 'tenant1'],
            ['member_id' => 'user2', 'name' => '用户2', 'tenant_key' => 'tenant1'],
            ['member_id' => 'user3', 'tenant_key' => 'tenant2']];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with($chatId)
            ->willReturn($groupInfo)
        ;

        $this->groupService->expects($this->once())
            ->method('getAllMembers')
            ->with($chatId)
            ->willReturn($members)
        ;

        $result = $this->groupTools->analyzeGroup($chatId);

        $this->assertSame($chatId, $result['basic']['chat_id']);
        $this->assertSame(3, $result['members']['total']);
        $this->assertSame(2, $result['members']['with_name']);
        $this->assertSame(1, $result['members']['without_name']);
        $this->assertSame(2, $result['members']['by_tenant']['tenant1']);
        $this->assertSame(1, $result['members']['by_tenant']['tenant2']);
        $this->assertSame('only_owner', $result['permissions']['add_member']);
    }

    public function testCloneGroupWithMembers(): void
    {
        $sourceChatId = 'oc_123456';
        $newName = '克隆群组';
        $sourceGroup = [
            'chat_id' => $sourceChatId,
            'name' => '原群组',
            'description' => '原描述',
            'owner_id' => 'owner123',
            'chat_mode' => 'group'];
        $members = [
            ['member_id' => 'owner123'],
            ['member_id' => 'user1'],
            ['member_id' => 'user2']];
        $newGroup = [
            'chat_id' => 'oc_789012',
            'name' => $newName];

        $this->groupService->expects($this->once())
            ->method('getGroup')
            ->with($sourceChatId)
            ->willReturn($sourceGroup)
        ;

        $this->groupService->expects($this->once())
            ->method('createGroup')
            ->willReturn($newGroup)
        ;

        $this->groupService->expects($this->once())
            ->method('getAllMembers')
            ->with($sourceChatId)
            ->willReturn($members)
        ;

        // 期望添加成员时排除群主
        $this->groupService->expects($this->once())
            ->method('addMembers')
            ->with($newGroup['chat_id'], ['user1', 'user2'], 'user_id', false)
            ->willReturn(['invalid_id_list' => [], 'not_existed_id_list' => []])
        ;

        $result = $this->groupTools->cloneGroup($sourceChatId, $newName, [], true);

        $this->assertSame('oc_789012', $result['chat_id']);
        $this->assertSame($newName, $result['name'] ?? '');
    }
}
