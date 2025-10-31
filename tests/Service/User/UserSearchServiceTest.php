<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\LarkAppBotBundle\Service\User\UserSearchService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserSearchService::class)]
#[RunTestsInSeparateProcesses]
final class UserSearchServiceTest extends AbstractIntegrationTestCase
{
    private UserSearchService $service;

    private LarkClientInterface&MockObject $client;

    public function testSearchUsersWithEmptyParams(): void
    {
        $params = [];
        $mockResponse = self::createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => [],
                    'has_more' => false,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $result = $this->service->searchUsers($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
        $this->assertFalse($result['has_more']);
    }

    public function testSearchUsersWithQuery(): void
    {
        $params = ['query' => 'test user'];
        $mockResponse = self::createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => [
                        ['user' => ['user_id' => 'u_123', 'name' => 'Test User']],
                    ],
                    'has_more' => false,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $result = $this->service->searchUsers($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function testFindSubordinates(): void
    {
        $userId = 'u_123';
        $mockResponse = self::createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => [
                        ['user' => ['user_id' => 'u_456', 'name' => 'Subordinate 1', 'leader_user_id' => $userId]],
                    ],
                    'has_more' => false,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $result = $this->service->findSubordinates($userId);

        $this->assertIsArray($result);
    }

    public function testFindUserDepartments(): void
    {
        $userId = 'u_123';
        $mockResponse = self::createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'user' => [
                        'department_ids' => ['dept_1', 'dept_2'],
                    ],
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $result = $this->service->findUserDepartments($userId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);

        // 确保 items 是数组类型
        $this->assertArrayHasKey('items', $result);
        $this->assertIsArray($result['items']);
        $this->assertCount(2, $result['items']);
        $this->assertArrayHasKey(0, $result['items']);
        $this->assertArrayHasKey(1, $result['items']);
        $this->assertSame(['department_id' => 'dept_1'], $result['items'][0]);
        $this->assertSame(['department_id' => 'dept_2'], $result['items'][1]);
    }

    protected function prepareMockServices(): void
    {
        $this->client = self::createMock(LarkClientInterface::class);
    }

    protected function onSetUp(): void
    {
        // 先创建并注入 LarkClient 的 mock，再从容器获取被测服务
        $this->client = self::createMock(LarkClientInterface::class);
        self::getContainer()->set(LarkClientInterface::class, $this->client);
        $this->service = self::getService(UserSearchService::class);
    }
}
