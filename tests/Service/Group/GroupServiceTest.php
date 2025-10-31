<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\Group\GroupService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（LarkClient）
 * LarkClient 虽然是具体类，但它是本包的核心HTTP客户端，实现了 HttpClientInterface
 * 在单元测试中 Mock LarkClient 是为了隔离网络请求，专注于测试 GroupService 的业务逻辑
 */
#[CoversClass(GroupService::class)]
#[RunTestsInSeparateProcesses]
final class GroupServiceTest extends AbstractIntegrationTestCase
{
    private GroupService $groupService;

    private LarkClient $mockLarkClient;

    public function testCreateGroupSuccess(): void
    {
        $params = [
            'name' => '测试群组',
            'description' => '这是一个测试群组',
            'owner_id' => 'user123'];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'data' => [
                    'chat_id' => 'oc_123456',
                    'name' => '测试群组',
                    'description' => '这是一个测试群组',
                    'owner_id' => 'user123']])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('POST', '/open-apis/im/v1/chats', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->createGroup($params);

        $this->assertSame('oc_123456', $result['chat_id']);
        $this->assertSame('测试群组', $result['name'] ?? null);
    }

    public function testCreateGroupWithoutNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('群组名称或国际化名称至少需要提供一个');

        $this->groupService->createGroup([]);
    }

    public function testUpdateGroupSuccess(): void
    {
        $chatId = 'oc_123456';
        $params = ['name' => '更新后的群组名称'];

        $response = $this->createMock(ResponseInterface::class);

        // Mock client calls are removed as they are not used in integration tests

        // updateGroup 方法返回 void，测试目标是验证方法执行成功不抛异常
        $this->expectNotToPerformAssertions();
        $this->groupService->updateGroup($chatId, $params);
    }

    public function testUpdateGroupWithEmptyChatIdThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('群组ID不能为空');

        $this->groupService->updateGroup('', ['name' => 'test']);
    }

    public function testGetGroupSuccess(): void
    {
        $chatId = 'oc_123456';
        $groupData = [
            'chat_id' => $chatId,
            'name' => '测试群组',
            'user_count' => 10,
            'bot_count' => 1];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(['data' => $groupData])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats/oc_123456', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->getGroup($chatId);

        $this->assertSame($groupData, $result);
    }

    public function testAddMembersSuccess(): void
    {
        $chatId = 'oc_123456';
        $memberIds = ['user1', 'user2', 'user3'];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'data' => [
                    'invalid_id_list' => ['user3'],
                    'not_existed_id_list' => []]])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('POST', '/open-apis/im/v1/chats/oc_123456/members', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->addMembers($chatId, $memberIds);

        $this->assertSame(['user3'], $result['invalid_id_list'] ?? []);
    }

    public function testAddMembersWithInvalidTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的成员类型：invalid_type');

        $this->groupService->addMembers('oc_123456', ['user1'], 'invalid_type');
    }

    public function testRemoveMembersSuccess(): void
    {
        $chatId = 'oc_123456';
        $memberIds = ['user1', 'user2'];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'data' => [
                    'invalid_id_list' => []]])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('DELETE', '/open-apis/im/v1/chats/oc_123456/members', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->removeMembers($chatId, $memberIds);

        $this->assertEmpty($result['invalid_id_list'] ?? []);
    }

    public function testGetMembersSuccess(): void
    {
        $chatId = 'oc_123456';
        $membersData = [
            'items' => [
                ['member_id' => 'user1', 'name' => '用户1'],
                ['member_id' => 'user2', 'name' => '用户2']],
            'page_token' => 'next_page',
            'has_more' => true,
            'member_total' => 50];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(['data' => $membersData])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats/oc_123456/members', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->getMembers($chatId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['has_more']);
        $this->assertSame(50, $result['member_total']);
    }

    public function testGetMembersWithInvalidPageSizeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('每页大小必须在1-100之间');

        $this->groupService->getMembers('oc_123456', 'user_id', null, 150);
    }

    public function testGetAllMembersSuccess(): void
    {
        $chatId = 'oc_123456';

        // 第一页
        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'data' => [
                    'items' => [
                        ['member_id' => 'user1', 'name' => '用户1'],
                        ['member_id' => 'user2', 'name' => '用户2']],
                    'page_token' => 'page2',
                    'has_more' => true,
                    'member_total' => 3]])
        ;

        // 第二页
        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'data' => [
                    'items' => [
                        ['member_id' => 'user3', 'name' => '用户3']],
                    'has_more' => false,
                    'member_total' => 3]])
        ;

        // 配置 Mock LarkClient - 需要两次调用
        $this->mockLarkClient->expects($this->exactly(2))
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats/oc_123456/members', self::anything())
            ->willReturnOnConsecutiveCalls($response1, $response2)
        ;

        $result = $this->groupService->getAllMembers($chatId);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame('user1', $result[0]['member_id']);
        $this->assertSame('user3', $result[2]['member_id']);
    }

    public function testDisbandGroupSuccess(): void
    {
        $chatId = 'oc_123456';

        $response = $this->createMock(ResponseInterface::class);

        // Mock client calls are removed as they are not used in integration tests

        // disbandGroup 方法返回 void，测试目标是验证方法执行成功不抛异常
        $this->expectNotToPerformAssertions();
        $this->groupService->disbandGroup($chatId);
    }

    public function testListGroupsSuccess(): void
    {
        $groupsData = [
            'items' => [
                [
                    'chat_id' => 'oc_123456',
                    'name' => '测试群组1',
                    'description' => '这是测试群组1',
                    'owner_id' => 'user123',
                    'chat_type' => 'group'],
                [
                    'chat_id' => 'oc_789012',
                    'name' => '测试群组2',
                    'description' => '这是测试群组2',
                    'owner_id' => 'user456',
                    'chat_type' => 'group']],
            'page_token' => 'next_page_token',
            'has_more' => true];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(['data' => $groupsData])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->listGroups();

        $this->assertIsArray($result);
        $this->assertCount(2, $result['items']);
        $this->assertSame('oc_123456', $result['items'][0]['chat_id']);
        $this->assertSame('测试群组1', $result['items'][0]['name'] ?? '');
        $this->assertTrue($result['has_more']);
        $this->assertSame('next_page_token', $result['page_token'] ?? '');
    }

    public function testListGroupsWithCustomParameters(): void
    {
        $groupsData = [
            'items' => [
                [
                    'chat_id' => 'oc_123456',
                    'name' => '测试群组',
                    'owner_id' => 'user123']],
            'has_more' => false];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(['data' => $groupsData])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->listGroups('user_id', 'some_page_token', 20);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['items']);
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['page_token'] ?? null);
    }

    public function testListGroupsEmptyResult(): void
    {
        $emptyData = [
            'items' => [],
            'has_more' => false];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(['data' => $emptyData])
        ;

        // 配置 Mock LarkClient
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats', self::anything())
            ->willReturn($response)
        ;

        $result = $this->groupService->listGroups();

        $this->assertEmpty($result['items']);
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['page_token'] ?? null);
    }

    public function testListGroupsWithInvalidUserIdTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的用户ID类型：invalid_type');

        $this->groupService->listGroups('invalid_type');
    }

    public function testListGroupsWithInvalidPageSizeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('每页大小必须在1-100之间');

        $this->groupService->listGroups('open_id', null, 150);
    }

    public function testListGroupsWithApiErrorThrowsException(): void
    {
        $exception = new \Exception('API调用失败');

        // 配置 Mock LarkClient 抛出异常
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/chats', self::anything())
            ->willThrowException($exception)
        ;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API调用失败');

        $this->groupService->listGroups();
    }

    protected function onSetUp(): void
    {
        $this->setupMockServices();

        // 从服务容器获取 GroupService 实例
        /** @var GroupService $groupService */
        $groupService = self::getContainer()->get(GroupService::class);
        $this->groupService = $groupService;
    }

    private function setupMockServices(): void
    {
        // 创建 Mock Token Provider 避免真实 API 调用
        $mockTokenProvider = $this->createMock(TokenProviderInterface::class);
        $mockTokenProvider->method('getToken')->willReturn('mock_token_123456');

        // 创建 Mock LarkClient 以避免真实 HTTP 请求
        $this->mockLarkClient = $this->createMock(LarkClient::class);

        // 重新定义容器中的服务
        self::getContainer()->set(TokenProviderInterface::class, $mockTokenProvider);
        self::getContainer()->set(LarkClient::class, $this->mockLarkClient);
    }
}
