<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\LarkAppBotBundle\Service\User\UserDataService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataService::class)]
#[RunTestsInSeparateProcesses]
class UserDataServiceTest extends AbstractIntegrationTestCase
{
    private UserDataService $service;

    private LarkClientInterface $client;

    private LoggerInterface $logger;

    public function testFetchUserSuccess(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'open_id' => 'user123',
        ];

        // 使用 InterfaceStubTrait 创建测试替身
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getContent')->willReturn(json_encode([
            'data' => [
                'user' => $userData,
            ],
        ]));
        $response->method('toArray')->willReturn([
            'data' => [
                'user' => $userData,
            ],
        ]);

        $testClient = self::createStub(LarkClientInterface::class);
        $testClient->method('request')->willReturn($response);

        // 使用容器获取服务
        $container = static::getContainer();
        $container->set('Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface', $testClient);
        $testService = $container->get(UserDataService::class);
        self::assertInstanceOf(UserDataService::class, $testService);
        $result = $testService->fetchUser($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testFetchUserApiException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用 InterfaceStubTrait 创建测试替身
        $testClient = self::createStub(LarkClientInterface::class);
        $testClient->method('request')
            ->willThrowException(new \RuntimeException('API Error'))
        ;

        // 使用容器获取服务
        $container = static::getContainer();
        $container->set('Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface', $testClient);
        $testService = $container->get(UserDataService::class);
        self::assertInstanceOf(UserDataService::class, $testService);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('获取用户信息失败: API Error');

        $testService->fetchUser($userId, $userIdType);
    }

    public function testFetchUserWithEmptyResponse(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用 InterfaceStubTrait 创建测试替身
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getContent')->willReturn(json_encode(['data' => []]));
        $response->method('toArray')->willReturn(['data' => []]);

        $testClient = self::createStub(LarkClientInterface::class);
        $testClient->method('request')->willReturn($response);

        // 使用容器获取服务
        $container = static::getContainer();
        $container->set('Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface', $testClient);
        $testService = $container->get(UserDataService::class);
        self::assertInstanceOf(UserDataService::class, $testService);
        $result = $testService->fetchUser($userId, $userIdType);

        $this->assertSame([], $result);
    }

    public function testBatchFetchUsersSuccess(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'open_id';
        $usersData = [
            ['user' => ['open_id' => 'user1', 'name' => 'User 1']],
            ['user' => ['open_id' => 'user2', 'name' => 'User 2']],
            ['user' => ['open_id' => 'user3', 'name' => 'User 3']],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => $usersData,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', '/open-apis/contact/v3/users/batch', [
                'query' => [
                    'user_id_type' => $userIdType,
                ],
                'json' => [
                    'user_ids' => $userIds,
                ],
            ])
            ->willReturn($response)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('批量获取用户信息成功', [
                'total_count' => 3,
                'fetched_count' => 3,
            ])
        ;

        $result = $this->service->batchFetchUsers($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(['open_id' => 'user1', 'name' => 'User 1'], $result['user1']);
        $this->assertSame(['open_id' => 'user2', 'name' => 'User 2'], $result['user2']);
        $this->assertSame(['open_id' => 'user3', 'name' => 'User 3'], $result['user3']);
    }

    public function testBatchFetchUsersWithEmptyArray(): void
    {
        $result = $this->service->batchFetchUsers([], 'open_id');

        $this->assertSame([], $result);
    }

    public function testBatchFetchUsersWithLargeBatch(): void
    {
        $userIds = array_map(fn ($i) => "user{$i}", range(1, 100)); // 100 users
        $userIdType = 'open_id';

        // First batch (50 users)
        $firstBatchData = array_map(fn ($i) => [
            'user' => ['open_id' => "user{$i}", 'name' => "User {$i}"],
        ], range(1, 50));

        // Second batch (50 users)
        $secondBatchData = array_map(fn ($i) => [
            'user' => ['open_id' => "user{$i}", 'name' => "User {$i}"],
        ], range(51, 100));

        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['data' => ['items' => $firstBatchData]]))
        ;

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['data' => ['items' => $secondBatchData]]))
        ;

        $this->client->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($response1, $response2)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('批量获取用户信息成功', [
                'total_count' => 100,
                'fetched_count' => 100,
            ])
        ;

        $result = $this->service->batchFetchUsers($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertCount(100, $result);
    }

    public function testBatchFetchUsersApiException(): void
    {
        $userIds = ['user1'];
        $userIdType = 'open_id';

        $this->client->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Batch API Error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('批量获取用户信息失败', [
                'user_ids' => $userIds,
                'error' => 'Batch API Error',
            ])
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('批量获取用户信息失败: Batch API Error');

        $this->service->batchFetchUsers($userIds, $userIdType);
    }

    public function testGetKeyFieldByType(): void
    {
        $testCases = [
            ['open_id', 'open_id'],
            ['union_id', 'union_id'],
            ['user_id', 'user_id'],
            ['email', 'email'],
            ['mobile', 'mobile'],
            ['invalid_type', 'open_id'], // Default case
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->service->getKeyFieldByType($input);
            $this->assertSame($expected, $result);
        }
    }

    public function testFetchUserDepartmentsSuccess(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $departments = [
            ['department_id' => 'dept1', 'name' => 'IT'],
            ['department_id' => 'dept2', 'name' => 'Engineering'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => $departments,
                    'has_more' => false,
                    'page_token' => null,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/contact/v3/users/user123/departments', [
                'query' => [
                    'user_id_type' => $userIdType,
                    'page_size' => 100,
                ],
            ])
            ->willReturn($response)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('获取用户部门列表成功', [
                'user_id' => $userId,
                'department_count' => 2,
            ])
        ;

        $result = $this->service->fetchUserDepartments($userId, $userIdType);

        $this->assertSame([
            'items' => $departments,
            'has_more' => false,
            'page_token' => null,
        ], $result);
    }

    public function testFetchUserDepartmentsApiException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        $this->client->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Department API Error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('获取用户部门列表失败', [
                'user_id' => $userId,
                'error' => 'Department API Error',
            ])
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('获取用户部门列表失败: Department API Error');

        $this->service->fetchUserDepartments($userId, $userIdType);
    }

    public function testValidateUserIdTypeValid(): void
    {
        $validTypes = ['open_id', 'union_id', 'user_id', 'email', 'mobile'];

        foreach ($validTypes as $validType) {
            $this->service->validateUserIdType($validType);
            $this->expectNotToPerformAssertions();
        }
    }

    public function testValidateUserIdTypeInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的用户ID类型: invalid_type，有效类型为: open_id, union_id, user_id, email, mobile');

        $this->service->validateUserIdType('invalid_type');
    }

    public function testFilterFieldsWithEmptyFields(): void
    {
        $user = ['name' => 'John', 'email' => 'john@example.com', 'status' => 1];
        $fields = [];

        $result = $this->service->filterFields($user, $fields);

        $this->assertSame($user, $result);
    }

    public function testFilterFieldsWithSpecificFields(): void
    {
        $user = ['name' => 'John', 'email' => 'john@example.com', 'status' => 1];
        $fields = ['name', 'email'];

        $result = $this->service->filterFields($user, $fields);

        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    public function testFilterFieldsWithNonExistentFields(): void
    {
        $user = ['name' => 'John', 'email' => 'john@example.com'];
        $fields = ['name', 'phone', 'address'];

        $result = $this->service->filterFields($user, $fields);

        $this->assertSame(['name' => 'John'], $result);
    }

    public function testBatchFetchUsersWithInvalidUserData(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'open_id';
        $usersData = [
            ['user' => ['open_id' => 'user1', 'name' => 'User 1']],
            ['user' => null], // Invalid user data
            ['invalid_structure' => 'data'], // Invalid structure
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => $usersData,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->service->batchFetchUsers($userIds, $userIdType);

        // Only valid user should be returned
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(['open_id' => 'user1', 'name' => 'User 1'], $result['user1']);
    }

    public function testBatchFetchUsersWithMissingUserIdField(): void
    {
        $userIds = ['user1'];
        $userIdType = 'union_id';
        $usersData = [
            ['user' => ['open_id' => 'user1', 'name' => 'User 1']], // Missing union_id
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => $usersData,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->service->batchFetchUsers($userIds, $userIdType);

        // No users should be returned since union_id is missing
        $this->assertEmpty($result);
    }

    public function testBatchFetchUsersWithEmptyUserIdField(): void
    {
        $userIds = ['user1'];
        $userIdType = 'open_id';
        $usersData = [
            ['user' => ['open_id' => '', 'name' => 'User 1']], // Empty open_id
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'data' => [
                    'items' => $usersData,
                ],
            ]))
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->service->batchFetchUsers($userIds, $userIdType);

        // No users should be returned since open_id is empty
        $this->assertEmpty($result);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->client = $this->createMock(LarkClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        // 直接使用依赖注入实例化被测服务，避免容器已初始化服务的替换限制
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->service = new UserDataService($this->client, $this->logger);
    }
}
