<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\LarkAppBotBundle\Service\User\UserDataMasker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataMasker::class)]
#[RunTestsInSeparateProcesses]
final class UserDataMaskerTest extends AbstractIntegrationTestCase
{
    private UserDataMasker $masker;

    public function testFormatContactInfoWithoutMasking(): void
    {
        $user = [
            'email' => 'test@example.com',
            'mobile' => '13812345678',
            'enterprise_email' => 'test@company.com',
            'mobile_visible' => true];

        $result = $this->masker->formatContactInfo($user, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('enterprise_email', $result);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('13812345678', $result['mobile']);
        $this->assertSame('test@company.com', $result['enterprise_email']);
    }

    public function testFormatContactInfoWithMasking(): void
    {
        $user = [
            'email' => 'test@example.com',
            'mobile' => '13812345678',
            'enterprise_email' => 'test@company.com',
            'mobile_visible' => true];

        $result = $this->masker->formatContactInfo($user, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('enterprise_email', $result);
        $this->assertSame('tes***@example.com', $result['email']);
        $this->assertSame('138****5678', $result['mobile']);
        $this->assertSame('tes***@company.com', $result['enterprise_email']);
    }

    public function testFormatContactInfoWithInvisibleMobile(): void
    {
        $user = [
            'email' => 'test@example.com',
            'mobile' => '13812345678',
            'mobile_visible' => false];

        $result = $this->masker->formatContactInfo($user, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertArrayNotHasKey('mobile', $result);
    }

    public function testFormatContactInfoWithEmptyFields(): void
    {
        $user = [];

        $result = $this->masker->formatContactInfo($user, false);

        $this->assertEmpty($result);
    }

    #[TestWith(['test@example.com', 'tes***@example.com'])]
    #[TestWith(['ab@domain.com', 'a***@domain.com'])]
    #[TestWith(['a@domain.com', 'a***@domain.com'])]
    #[TestWith(['longname@example.com', 'lon***@example.com'])]
    public function testMaskEmail(string $input, string $expected): void
    {
        $result = $this->masker->maskEmail($input);
        $this->assertSame($expected, $result);
    }

    public function testMaskEmailInvalidFormat(): void
    {
        $result = $this->masker->maskEmail('invalid-email');
        $this->assertSame('invalid-email', $result);
    }

    #[TestWith(['13812345678', '138****5678'])]
    #[TestWith(['1234567', '123****4567'])]
    #[TestWith(['12345', '12345'])]
    #[TestWith(['123456', '123456'])]
    public function testMaskMobile(string $input, string $expected): void
    {
        $result = $this->masker->maskMobile($input);
        $this->assertSame($expected, $result);
    }

    public function testMaskMobileShortNumber(): void
    {
        $result = $this->masker->maskMobile('123456');
        $this->assertSame('123456', $result);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->masker = self::getService(UserDataMasker::class);
    }
}
