<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\SecurityPolicy;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SecurityPolicy::class)]
#[RunTestsInSeparateProcesses]
final class SecurityPolicyTest extends AbstractIntegrationTestCase
{
    private SecurityPolicy $securityPolicy;

    private LoggerInterface $logger;

    public function testCheckDataAccessPolicy(): void
    {
        $context = [
            'data_classification' => 'internal',
            'has_approval' => true];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Policy check result', self::arrayHasKey('result'))
        ;

        // 使用带有Mock Logger的实例
        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_DATA_ACCESS, $context);
        $this->assertTrue($result);
    }

    public function testCheckDataAccessPolicyWithoutApproval(): void
    {
        $context = [
            'data_classification' => 'internal',
            'has_approval' => false];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_DATA_ACCESS, $context);
        $this->assertFalse($result);
    }

    public function testCheckFileSharingPolicy(): void
    {
        $context = [
            'file_size_mb' => 5,
            'file_type' => 'pdf',
            'malware_scan_passed' => true];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_FILE_SHARING, $context);
        $this->assertTrue($result); // File sharing is disabled by default, so it returns true (allow)
    }

    public function testCheckFileSharingPolicyWithLargeFile(): void
    {
        // Enable file sharing policy first
        $this->securityPolicy->updatePolicy(SecurityPolicy::POLICY_FILE_SHARING, ['enabled' => true]);

        $context = [
            'file_size_mb' => 20, // Exceeds default limit of 10MB
            'file_type' => 'pdf',
            'malware_scan_passed' => true];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_FILE_SHARING, $context);
        $this->assertFalse($result);
    }

    public function testCheckMessageRetentionPolicy(): void
    {
        $context = [
            'message_age_days' => 30];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_MESSAGE_RETENTION, $context);
        $this->assertTrue($result);
    }

    public function testCheckIpWhitelistPolicy(): void
    {
        // IP whitelist is disabled by default, should allow all
        $context = [
            'user_ip' => '192.168.1.1'];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_IP_WHITELIST, $context);
        $this->assertTrue($result);
    }

    public function testCheckIpWhitelistPolicyWithRestriction(): void
    {
        $this->securityPolicy->updatePolicy(SecurityPolicy::POLICY_IP_WHITELIST, [
            'enabled' => true,
            'allowed_ips' => ['192.168.1.0/24']]);

        $context1 = ['user_ip' => '192.168.1.100'];
        $result1 = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_IP_WHITELIST, $context1);
        $this->assertTrue($result1);

        $context2 = ['user_ip' => '192.168.2.100'];
        $result2 = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_IP_WHITELIST, $context2);
        $this->assertFalse($result2);
    }

    public function testCheckTimeRestrictionPolicy(): void
    {
        // Time restriction is disabled by default
        $context = [];

        $result = $this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_TIME_RESTRICTION, $context);
        $this->assertTrue($result);
    }

    public function testCheckUnknownPolicy(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Unknown policy type', ['policy_type' => 'unknown_policy'])
        ;

        // 使用带有Mock Logger的实例
        $result = $this->securityPolicy->checkPolicy('unknown_policy', []);
        $this->assertFalse($result);
    }

    public function testGetAllPolicies(): void
    {
        $policies = $this->securityPolicy->getAllPolicies();

        $this->assertIsArray($policies);
        $this->assertArrayHasKey(SecurityPolicy::POLICY_DATA_ACCESS, $policies);
        $this->assertArrayHasKey(SecurityPolicy::POLICY_FILE_SHARING, $policies);
        $this->assertArrayHasKey(SecurityPolicy::POLICY_MESSAGE_RETENTION, $policies);
        $this->assertArrayHasKey(SecurityPolicy::POLICY_AUDIT_LOGGING, $policies);
    }

    public function testGetPolicy(): void
    {
        $policy = $this->securityPolicy->getPolicy(SecurityPolicy::POLICY_DATA_ACCESS);

        $this->assertNotEmpty($policy);
        $this->assertIsArray($policy);
        $this->assertArrayHasKey('enabled', $policy);
        // Note: 默认策略结构中没有 'rules' 键
    }

    public function testGetNonExistentPolicy(): void
    {
        $policy = $this->securityPolicy->getPolicy('non_existent');
        $this->assertNull($policy);
    }

    public function testUpdatePolicy(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Policy updated', self::arrayHasKey('config'))
        ;

        // 使用带有Mock Logger的实例
        $this->securityPolicy->updatePolicy(SecurityPolicy::POLICY_FILE_SHARING, [
            'enabled' => true,
            'max_file_size_mb' => 20]);

        $policy = $this->securityPolicy->getPolicy(SecurityPolicy::POLICY_FILE_SHARING);
        $this->assertTrue($policy['enabled'] ?? false);
        $this->assertSame(20, $policy['max_file_size_mb']);
    }

    public function testResetToDefault(): void
    {
        // 分别验证两个调用的Mock
        $matcher = $this->logger->expects($this->exactly(2))
            ->method('info')
        ;

        $matcher->willReturnCallback(function (string $message, array $context) {
            static $callCount = 0;
            ++$callCount;

            if (1 === $callCount) {
                $this->assertSame('Policy updated', $message);
                $this->assertIsArray($context);
                $this->assertArrayHasKey('config', $context);
            } elseif (2 === $callCount) {
                $this->assertSame('Policy reset to default', $message);
                $this->assertSame(['policy_type' => SecurityPolicy::POLICY_FILE_SHARING], $context);
            }
        });

        // 使用带有Mock Logger的实例
        // Modify a policy first
        $this->securityPolicy->updatePolicy(SecurityPolicy::POLICY_FILE_SHARING, [
            'enabled' => true,
            'max_file_size_mb' => 50]);

        // Reset specific policy
        $this->securityPolicy->resetToDefault(SecurityPolicy::POLICY_FILE_SHARING);

        $policy = $this->securityPolicy->getPolicy(SecurityPolicy::POLICY_FILE_SHARING);
        $this->assertFalse($policy['enabled'] ?? true); // Default is disabled
        $this->assertSame(10, $policy['max_file_size_mb']); // Default is 10MB
    }

    public function testResetAllToDefault(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Policy reset to default', ['policy_type' => null])
        ;

        // 使用带有Mock Logger的实例
        $this->securityPolicy->resetToDefault();

        $policies = $this->securityPolicy->getAllPolicies();
        // Verify a policy has default values
        $this->assertFalse($policies[SecurityPolicy::POLICY_FILE_SHARING]['enabled']);
    }

    public function testCheckPolicyWithCustomConfiguration(): void
    {
        // 从容器获取新的 SecurityPolicy 实例用于自定义配置测试
        /** @var SecurityPolicy $securityPolicyWithCustomConfig */
        $securityPolicyWithCustomConfig = self::getContainer()->get(SecurityPolicy::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($securityPolicyWithCustomConfig);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($securityPolicyWithCustomConfig, $mockLogger);

        // 更新策略配置以不要求审批
        $securityPolicyWithCustomConfig->updatePolicy(SecurityPolicy::POLICY_DATA_ACCESS, [
            'enabled' => true,
            'external_access_level' => 'full',
            'require_approval' => false,
            'data_classification' => ['public', 'internal', 'confidential'],
        ]);

        $context = [
            'data_classification' => 'confidential',
            'has_approval' => false];

        $result = $securityPolicyWithCustomConfig->checkPolicy(SecurityPolicy::POLICY_DATA_ACCESS, $context);
        $this->assertTrue($result); // Approval not required in custom config
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->logger = $this->createMock(LoggerInterface::class);

        // 从容器获取 SecurityPolicy 服务
        /** @var SecurityPolicy $securityPolicy */
        $securityPolicy = self::getContainer()->get(SecurityPolicy::class);
        $this->securityPolicy = $securityPolicy;

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->securityPolicy);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->securityPolicy, $this->logger);
    }
}
