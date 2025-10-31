<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\User\UserValidator;

/**
 * @internal
 */
#[CoversClass(UserValidator::class)]
final class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UserValidator();
    }

    #[TestWith(['open_id'])]
    #[TestWith(['union_id'])]
    #[TestWith(['user_id'])]
    #[TestWith(['email'])]
    #[TestWith(['mobile'])]
    public function testValidateUserIdTypeSuccess(string $validType): void
    {
        $this->validator->validateUserIdType($validType);

        $this->assertContains($validType, ['open_id', 'union_id', 'user_id', 'email', 'mobile']);
    }

    public function testValidateUserIdTypeFailure(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的用户ID类型: invalid');

        $this->validator->validateUserIdType('invalid');
    }

    public function testGetAllUserIds(): void
    {
        $user = [
            'open_id' => 'ou_123',
            'union_id' => 'on_456',
            'user_id' => 'u_789',
            'email' => 'test@example.com',
            'mobile' => '13812345678'];

        $result = $this->validator->getAllUserIds($user);

        $this->assertSame([
            'open_id' => 'ou_123',
            'union_id' => 'on_456',
            'user_id' => 'u_789',
            'email' => 'test@example.com',
            'mobile' => '13812345678'], $result);
    }

    public function testGetAllUserIdsPartial(): void
    {
        $user = [
            'open_id' => 'ou_123',
            'email' => 'test@example.com'];

        $result = $this->validator->getAllUserIds($user);

        $this->assertSame([
            'open_id' => 'ou_123',
            'email' => 'test@example.com'], $result);
    }

    public function testGetAllUserIdsEmpty(): void
    {
        $user = [];

        $result = $this->validator->getAllUserIds($user);

        $this->assertEmpty($result);
    }

    /**
     * @param array<string, mixed> $user
     */
    #[TestWith(['open_id', ['open_id' => 'ou_123'], 'ou_123'])]
    #[TestWith(['union_id', ['union_id' => 'on_456'], 'on_456'])]
    #[TestWith(['user_id', ['user_id' => 'u_789'], 'u_789'])]
    #[TestWith(['email', ['email' => 'test@example.com'], 'test@example.com'])]
    #[TestWith(['mobile', ['mobile' => '13812345678'], '13812345678'])]
    #[TestWith(['open_id', [], null])]
    #[TestWith(['invalid', ['open_id' => 'ou_123'], null])]
    public function testExtractUserId(string $targetType, array $user, ?string $expected): void
    {
        $result = $this->validator->extractUserId($user, $targetType);
        $this->assertSame($expected, $result);
    }

    public function testExtractUserIdWithEmptyValue(): void
    {
        $user = ['open_id' => ''];

        $result = $this->validator->extractUserId($user, 'open_id');

        $this->assertNull($result);
    }

    public function testGetPrimaryDepartmentId(): void
    {
        $departments = [
            ['department_id' => 'dept_1', 'is_primary_dept' => false],
            ['department_id' => 'dept_2', 'is_primary_dept' => true],
            ['department_id' => 'dept_3', 'is_primary_dept' => false]];

        $result = $this->validator->getPrimaryDepartmentId($departments);

        $this->assertSame('dept_2', $result);
    }

    public function testGetPrimaryDepartmentIdFallbackToFirst(): void
    {
        $departments = [
            ['department_id' => 'dept_1', 'is_primary_dept' => false],
            ['department_id' => 'dept_2', 'is_primary_dept' => false]];

        $result = $this->validator->getPrimaryDepartmentId($departments);

        $this->assertSame('dept_1', $result);
    }

    public function testGetPrimaryDepartmentIdEmpty(): void
    {
        $departments = [];

        $result = $this->validator->getPrimaryDepartmentId($departments);

        $this->assertNull($result);
    }

    public function testGetPrimaryDepartmentIdMissingId(): void
    {
        $departments = [
            ['name' => 'Department 1']];

        $result = $this->validator->getPrimaryDepartmentId($departments);

        $this->assertNull($result);
    }
}
