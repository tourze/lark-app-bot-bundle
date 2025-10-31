<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\LarkAppBotBundle\Service\User\UserTools;

/**
 * @internal
 */
#[CoversClass(UserTools::class)]
class UserToolsTest extends TestCase
{
    public function testValidateUserIdTypeValid(): void
    {
        $validTypes = ['open_id', 'union_id', 'user_id', 'email', 'mobile'];

        foreach ($validTypes as $validType) {
            UserTools::validateUserIdType($validType);
            $this->expectNotToPerformAssertions();
        }
    }

    public function testValidateUserIdTypeInvalid(): void
    {
        $this->expectException(ValidationException::class);

        UserTools::validateUserIdType('invalid_type');
    }

    public function testExtractUserId(): void
    {
        $user = [
            'open_id' => 'open123',
            'union_id' => 'union456',
            'user_id' => 'user789',
            'email' => 'test@example.com',
            'mobile' => '13800138000',
        ];

        $this->assertSame('open123', UserTools::extractUserId($user, 'open_id'));
        $this->assertSame('union456', UserTools::extractUserId($user, 'union_id'));
        $this->assertSame('user789', UserTools::extractUserId($user, 'user_id'));
        $this->assertSame('test@example.com', UserTools::extractUserId($user, 'email'));
        $this->assertSame('13800138000', UserTools::extractUserId($user, 'mobile'));
    }

    public function testExtractUserIdWithMissingField(): void
    {
        $user = [
            'open_id' => 'open123',
            'name' => 'John Doe',
        ];

        $this->assertNull(UserTools::extractUserId($user, 'union_id'));
        $this->assertNull(UserTools::extractUserId($user, 'email'));
    }

    public function testGetAllUserIds(): void
    {
        $user = [
            'open_id' => 'open123',
            'union_id' => 'union456',
            'user_id' => 'user789',
            'email' => 'test@example.com',
            'name' => 'John Doe',
        ];

        $result = UserTools::getAllUserIds($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('open_id', $result);
        $this->assertArrayHasKey('union_id', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('open123', $result['open_id']);
        $this->assertSame('union456', $result['union_id']);
        $this->assertSame('user789', $result['user_id']);
        $this->assertSame('test@example.com', $result['email']);
    }

    public function testFormatDisplayName(): void
    {
        $user = [
            'name' => '张三',
            'en_name' => 'Zhang San',
            'job_title' => '软件工程师',
        ];

        // Test Chinese locale
        $result = UserTools::formatDisplayName($user, 'zh_CN');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Test English locale
        $result = UserTools::formatDisplayName($user, 'en_US');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Test with title
        $result = UserTools::formatDisplayName($user, 'zh_CN', true);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testGetAvatarUrl(): void
    {
        $user = [
            'avatar' => [
                'avatar_72' => 'https://example.com/avatar_72.jpg',
                'avatar_240' => 'https://example.com/avatar_240.jpg',
                'avatar_640' => 'https://example.com/avatar_640.jpg',
            ],
        ];

        $this->assertSame('https://example.com/avatar_240.jpg', UserTools::getAvatarUrl($user));
        $this->assertSame('https://example.com/avatar_72.jpg', UserTools::getAvatarUrl($user, '72'));
        $this->assertSame('https://example.com/avatar_640.jpg', UserTools::getAvatarUrl($user, '640'));
    }

    public function testGetAvatarUrlWithMissingAvatar(): void
    {
        $user = ['name' => 'John Doe'];

        $this->assertNull(UserTools::getAvatarUrl($user));
    }

    public function testCheckUserStatus(): void
    {
        $user = [
            'status' => [
                'is_frozen' => false,
                'is_resigned' => false,
                'is_activated' => true,
            ],
        ];

        $result = UserTools::checkUserStatus($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('is_frozen', $result);
        $this->assertArrayHasKey('is_resigned', $result);
        $this->assertArrayHasKey('status_text', $result);
        $this->assertIsBool($result['is_active']);
        $this->assertIsBool($result['is_frozen']);
        $this->assertIsBool($result['is_resigned']);
        $this->assertIsString($result['status_text']);
    }

    public function testParsePermissions(): void
    {
        $user = [
            'is_tenant_manager' => true,
            'custom_attrs' => [
                'permissions' => ['read', 'write', 'admin'],
            ],
        ];

        $result = UserTools::parsePermissions($user);

        $this->assertIsArray($result);
        $this->assertContains('tenant_admin', $result);
    }

    public function testCalculateOrgLevel(): void
    {
        $user = [
            'department_ids' => ['dept1', 'dept2'],
        ];

        $departments = [
            'dept1' => ['level' => 1],
            'dept2' => ['level' => 2],
        ];

        $result = UserTools::calculateOrgLevel($user, $departments);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testFormatContactInfo(): void
    {
        $user = [
            'email' => 'john@example.com',
            'mobile' => '13800138000',
            'enterprise_email' => 'john@company.com',
        ];

        // Without masking
        $result = UserTools::formatContactInfo($user, false);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('enterprise_email', $result);
        $this->assertSame('john@example.com', $result['email']);

        // With masking
        $result = UserTools::formatContactInfo($user, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('enterprise_email', $result);
        // Should be masked
        $this->assertNotSame('john@example.com', $result['email']);
    }

    public function testGetPrimaryDepartmentId(): void
    {
        $departments = [
            [
                'department_id' => 'dept1',
                'is_primary_dept' => false,
            ],
            [
                'department_id' => 'dept2',
                'is_primary_dept' => true,
            ],
            [
                'department_id' => 'dept3',
                'is_primary_dept' => false,
            ],
        ];

        $result = UserTools::getPrimaryDepartmentId($departments);

        $this->assertSame('dept2', $result);
    }

    public function testGetPrimaryDepartmentIdWithNoPrimary(): void
    {
        $departments = [
            [
                'department_id' => 'dept1',
                'is_primary_dept' => false,
            ],
            [
                'department_id' => 'dept2',
                'is_primary_dept' => false,
            ],
        ];

        $result = UserTools::getPrimaryDepartmentId($departments);

        // Should return first department if no primary
        $this->assertSame('dept1', $result);
    }

    public function testGetPrimaryDepartmentIdWithEmptyArray(): void
    {
        $result = UserTools::getPrimaryDepartmentId([]);

        $this->assertNull($result);
    }

    public function testCompareUserInfo(): void
    {
        $oldUser = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];

        $newUser = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'mobile' => '13800138000',
        ];

        $result = UserTools::compareUserInfo($oldUser, $newUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('added', $result);
        $this->assertArrayHasKey('removed', $result);
        $this->assertArrayHasKey('changed', $result);
    }

    public function testGenerateUserSummary(): void
    {
        $user = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => [
                'is_activated' => true,
                'is_frozen' => false,
                'is_resigned' => false,
            ],
            'department_ids' => ['dept1'],
        ];

        $result = UserTools::generateUserSummary($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('is_active', $result);
    }

    public function testGetUserByEmail(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $email = 'john@example.com';

        // Mock the service to return a user array
        $userService->method('getUser')
            ->with($email, 'email')
            ->willReturn(['name' => 'John Doe', 'email' => $email])
        ;

        $result = UserTools::getUserByEmail($userService, $email);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testGetUserByEmailWithException(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $email = 'nonexistent@example.com';

        // Mock the service to throw an exception
        $userService->method('getUser')
            ->with($email, 'email')
            ->willThrowException(new \RuntimeException('User not found'))
        ;

        $result = UserTools::getUserByEmail($userService, $email);

        // 验证返回结果类型
        $this->assertNull($result);
    }

    public function testGetUserByMobile(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $mobile = '13800138000';

        // Mock the service to return a user array
        $userService->method('getUser')
            ->with($mobile, 'mobile')
            ->willReturn(['name' => 'John Doe', 'mobile' => $mobile])
        ;

        $result = UserTools::getUserByMobile($userService, $mobile);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testGetUserByMobileWithException(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $mobile = '13900139000';

        // Mock the service to throw an exception
        $userService->method('getUser')
            ->with($mobile, 'mobile')
            ->willThrowException(new \RuntimeException('User not found'))
        ;

        $result = UserTools::getUserByMobile($userService, $mobile);

        // 验证返回结果类型
        $this->assertNull($result);
    }

    public function testGetUserByEmailWithFields(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $email = 'john@example.com';
        $fields = ['name', 'email'];

        // Mock the service to return a user array
        $userService->method('getUser')
            ->with($email, 'email', $fields)
            ->willReturn(['name' => 'John Doe', 'email' => $email])
        ;

        $result = UserTools::getUserByEmail($userService, $email, $fields);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testGetUserByMobileWithFields(): void
    {
        $userService = static::createMock(UserServiceInterface::class);
        $mobile = '13800138000';
        $fields = ['name', 'mobile'];

        // Mock the service to return a user array
        $userService->method('getUser')
            ->with($mobile, 'mobile', $fields)
            ->willReturn(['name' => 'John Doe', 'mobile' => $mobile])
        ;

        $result = UserTools::getUserByMobile($userService, $mobile, $fields);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }
}
