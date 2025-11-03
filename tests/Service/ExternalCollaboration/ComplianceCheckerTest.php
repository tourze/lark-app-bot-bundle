<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AuditLogger;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ComplianceChecker;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\SecurityPolicy;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ComplianceChecker::class)]
#[RunTestsInSeparateProcesses]
final class ComplianceCheckerTest extends AbstractIntegrationTestCase
{
    private ComplianceChecker $complianceChecker;

    private SecurityPolicy $securityPolicy;

    private AuditLogger $auditLogger;

    private LoggerInterface $logger;

    public function testCheckDataPrivacyCompliant(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'encrypted' => true,
            'access_controlled' => true,
            'collection_purpose' => 'user_management'];

        $this->auditLogger
            ->expects($this->once())
            ->method('log')
            ->with('compliance_check', 'test_user', self::isArray(), AuditLogger::LEVEL_INFO)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Compliance check completed', self::isArray())
        ;

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertSame(ComplianceChecker::CHECK_DATA_PRIVACY, $result['check_type']);
        $this->assertEmpty($result['violations']);
        $this->assertIsInt($result['checked_at']);
    }

    public function testCheckDataPrivacyViolationPersonalDataWithoutProtection(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'email' => 'test@example.com', // 个人数据
            'encrypted' => false,  // 没有加密保护
            'access_controlled' => false,  // 没有访问控制
            'collection_purpose' => 'user_management'];

        $this->auditLogger
            ->expects($this->once())
            ->method('log')
            ->with('compliance_check', 'test_user', self::isArray(), AuditLogger::LEVEL_WARNING)
        ;

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Personal data found without proper protection', $result['violations']);
    }

    public function testCheckDataPrivacyViolationNoPurpose(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'encrypted' => true,
            'access_controlled' => true,
            // 缺少 collection_purpose
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Data collection purpose not clearly defined', $result['violations']);
    }

    public function testCheckDataPrivacyWarningExcessiveData(): void
    {
        // 准备过多字段的数据（超过20个）
        $data = [
            'user_id' => 'test_user',
            'collection_purpose' => 'testing',
            'encrypted' => true,
            'access_controlled' => true];
        // 添加额外字段使总数超过20
        for ($i = 1; $i <= 18; ++$i) {
            $data["field_{$i}"] = "value_{$i}";
        }

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains('Data collection may violate minimization principle', $result['warnings']);
    }

    public function testCheckExportControlCompliant(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_country' => 'US',  // 非受限国家
            'technologies' => ['standard_tech'],  // 非敏感技术
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_EXPORT_CONTROL, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertEmpty($result['violations']);
    }

    public function testCheckExportControlViolationRestrictedCountry(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_country' => 'XX',  // 受限国家
            'technologies' => ['standard_tech']];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_EXPORT_CONTROL, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Access from restricted country: XX', $result['violations']);
    }

    public function testCheckExportControlWarningSensitiveTechnology(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_country' => 'US',
            'technologies' => ['encryption', 'ai'],  // 敏感技术
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_EXPORT_CONTROL, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains('Sensitive technology involved: encryption', $result['warnings']);
        $this->assertContains('Sensitive technology involved: ai', $result['warnings']);
    }

    public function testCheckRetentionPolicyCompliant(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'retention_days' => 90,  // 在30-365范围内
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_RETENTION_POLICY, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertEmpty($result['violations']);
    }

    public function testCheckRetentionPolicyViolationTooShort(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'retention_days' => 15,  // 小于最小值30
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_RETENTION_POLICY, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Retention period too short: 15 days (minimum: 30)', $result['violations']);
    }

    public function testCheckRetentionPolicyViolationTooLong(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'retention_days' => 400,  // 大于最大值365
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_RETENTION_POLICY, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Retention period too long: 400 days (maximum: 365)', $result['violations']);
    }

    public function testCheckAccessControlCompliant(): void
    {
        // 先添加访问控制规则配置
        $reflection = new \ReflectionClass($this->complianceChecker);
        $rulesProperty = $reflection->getProperty('complianceRules');
        $rulesProperty->setAccessible(true);
        $rules = $rulesProperty->getValue($this->complianceChecker);
        $this->assertIsArray($rules);
        $rules[ComplianceChecker::CHECK_ACCESS_CONTROL] = [
            'enabled' => true,
            'rules' => []];
        $rulesProperty->setValue($this->complianceChecker, $rules);

        // Mock SecurityPolicy 返回 true
        $this->securityPolicy
            ->expects($this->once())
            ->method('checkPolicy')
            ->with(SecurityPolicy::POLICY_DATA_ACCESS, self::callback(fn ($value) => \is_array($value)))
            ->willReturn(true)
        ;

        // 准备
        $data = [
            'user_id' => 'test_user',
            'access_controls' => ['role_based', 'permission_check'],
            'audit_enabled' => true,
            'data_classification' => 'public',  // 使用允许的数据分类
            'has_approval' => true,  // 设置审批通过标志
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_ACCESS_CONTROL, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertEmpty($result['violations']);
    }

    public function testCheckAccessControlViolationNoAccessControls(): void
    {
        // 先添加访问控制规则配置
        $reflection = new \ReflectionClass($this->complianceChecker);
        $rulesProperty = $reflection->getProperty('complianceRules');
        $rulesProperty->setAccessible(true);
        $rules = $rulesProperty->getValue($this->complianceChecker);
        $this->assertIsArray($rules);
        $rules[ComplianceChecker::CHECK_ACCESS_CONTROL] = [
            'enabled' => true,
            'rules' => []];
        $rulesProperty->setValue($this->complianceChecker, $rules);

        // 准备
        $data = [
            'user_id' => 'test_user',
            'audit_enabled' => true,
            // 缺少 access_controls
        ];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_ACCESS_CONTROL, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('No access controls defined', $result['violations']);
    }

    public function testCheckAccessControlWarningNoAudit(): void
    {
        // 先添加访问控制规则配置
        $reflection = new \ReflectionClass($this->complianceChecker);
        $rulesProperty = $reflection->getProperty('complianceRules');
        $rulesProperty->setAccessible(true);
        $rules = $rulesProperty->getValue($this->complianceChecker);
        $this->assertIsArray($rules);
        $rules[ComplianceChecker::CHECK_ACCESS_CONTROL] = [
            'enabled' => true,
            'rules' => []];
        $rulesProperty->setValue($this->complianceChecker, $rules);

        // Mock SecurityPolicy 检查通过
        $this->securityPolicy->expects($this->once())
            ->method('checkPolicy')
            ->with(SecurityPolicy::POLICY_DATA_ACCESS, self::anything())
            ->willReturn(true)
        ;

        // 准备
        $data = [
            'user_id' => 'test_user',
            'access_controls' => ['role_based'],
            'audit_enabled' => false];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_ACCESS_CONTROL, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains('Audit trail not enabled', $result['warnings']);
    }

    public function testCheckGdprCompliant(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_consent' => true,
            'erasure_supported' => true,
            'export_supported' => true];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_GDPR, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertEmpty($result['violations']);
    }

    public function testCheckGdprViolationNoConsent(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_consent' => false,
            'erasure_supported' => true,
            'export_supported' => true];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_GDPR, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('User consent not obtained', $result['violations']);
    }

    public function testCheckGdprViolationNoErasure(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_consent' => true,
            'erasure_supported' => false,
            'export_supported' => true];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_GDPR, $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Right to erasure not implemented', $result['violations']);
    }

    public function testCheckGdprWarningNoDataPortability(): void
    {
        // 准备
        $data = [
            'user_id' => 'test_user',
            'user_consent' => true,
            'erasure_supported' => true,
            'export_supported' => false];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_GDPR, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains('Data portability not fully supported', $result['warnings']);
    }

    public function testCheckUnknownCheckType(): void
    {
        // 准备
        $data = ['user_id' => 'test_user'];

        // 执行
        $result = $this->complianceChecker->check('unknown_check', $data);

        // 验证
        $this->assertFalse($result['compliant']);
        $this->assertIsArray($result['violations']);
        $this->assertContains('Unknown compliance check type', $result['violations']);
    }

    public function testCheckDisabledCheckType(): void
    {
        // 通过反射访问和修改私有属性来禁用检查
        $reflection = new \ReflectionClass($this->complianceChecker);
        $rulesProperty = $reflection->getProperty('complianceRules');
        $rulesProperty->setAccessible(true);
        $rules = $rulesProperty->getValue($this->complianceChecker);
        $this->assertIsArray($rules);
        $this->assertIsArray($rules[ComplianceChecker::CHECK_DATA_PRIVACY] ?? null);
        $rules[ComplianceChecker::CHECK_DATA_PRIVACY]['enabled'] = false;
        $rulesProperty->setValue($this->complianceChecker, $rules);

        $data = ['user_id' => 'test_user'];

        // 执行
        $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertTrue($result['compliant']);
        $this->assertIsArray($result['warnings']);
        $this->assertContains('Compliance check is disabled', $result['warnings']);
    }

    public function testCheckAllMultipleTypes(): void
    {
        // 准备
        $checkTypes = [
            ComplianceChecker::CHECK_DATA_PRIVACY,
            ComplianceChecker::CHECK_EXPORT_CONTROL,
            ComplianceChecker::CHECK_RETENTION_POLICY];
        $data = [
            'user_id' => 'test_user',
            'encrypted' => true,
            'access_controlled' => true,
            'collection_purpose' => 'testing',
            'user_country' => 'US',
            'technologies' => ['standard'],
            'retention_days' => 90];

        $this->auditLogger
            ->expects($this->exactly(3))
            ->method('log')
        ;

        $this->logger
            ->expects($this->exactly(3))
            ->method('info')
        ;

        // 执行
        $results = $this->complianceChecker->checkAll($checkTypes, $data);

        // 验证
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertArrayHasKey(ComplianceChecker::CHECK_DATA_PRIVACY, $results);
        $this->assertArrayHasKey(ComplianceChecker::CHECK_EXPORT_CONTROL, $results);
        $this->assertArrayHasKey(ComplianceChecker::CHECK_RETENTION_POLICY, $results);

        foreach ($results as $result) {
            $this->assertTrue($result['compliant']);
        }
    }

    public function testGenerateReportAllCompliant(): void
    {
        // 准备
        $checkResults = [
            'check1' => [
                'compliant' => true,
                'violations' => [],
                'warnings' => ['minor warning']],
            'check2' => [
                'compliant' => true,
                'violations' => [],
                'warnings' => []]];

        // 执行
        $report = $this->complianceChecker->generateReport($checkResults);

        // 验证
        $this->assertTrue($report['overall_compliance']);
        $this->assertSame(2, $report['total_checks']);
        $this->assertSame(0, $report['failed_checks']);
        $this->assertSame(0, $report['total_violations']);
        $this->assertSame(1, $report['total_warnings']);
        $this->assertIsArray($report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('details', $report);
    }

    public function testGenerateReportWithFailures(): void
    {
        // 准备
        $checkResults = [
            'check1' => [
                'compliant' => false,
                'violations' => ['violation1', 'violation2'],
                'warnings' => ['warning1']],
            'check2' => [
                'compliant' => true,
                'violations' => [],
                'warnings' => ['warning2']]];

        // 执行
        $report = $this->complianceChecker->generateReport($checkResults);

        // 验证
        $this->assertFalse($report['overall_compliance']);
        $this->assertSame(2, $report['total_checks']);
        $this->assertSame(1, $report['failed_checks']);
        $this->assertSame(2, $report['total_violations']);
        $this->assertSame(2, $report['total_warnings']);

        // 验证详细信息
        $this->assertIsArray($report);
        $this->assertArrayHasKey('details', $report);
        $this->assertIsArray($report['details']);
        $this->assertArrayHasKey('check1', $report['details']);
        $this->assertArrayHasKey('check2', $report['details']);
        $this->assertFalse((bool) $report['details']['check1']['compliant']);
        $this->assertTrue((bool) $report['details']['check2']['compliant']);
    }

    public function testConstructorWithNullLogger(): void
    {
        // 测试构造函数使用空Logger
        $securityPolicy = $this->createMock(SecurityPolicy::class);
        $auditLogger = $this->createMock(AuditLogger::class);

        // 配置 auditLogger mock
        $auditLogger->expects($this->once())
            ->method('log')
            ->with('compliance_check', 'test_user', self::isArray(), self::anything())
        ;

        // 从容器获取 ComplianceChecker 服务，然后替换依赖
        /** @var ComplianceChecker $checker */
        $checker = self::getContainer()->get(ComplianceChecker::class);

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($checker);

        $securityPolicyProperty = $reflection->getProperty('securityPolicy');
        $securityPolicyProperty->setAccessible(true);
        $securityPolicyProperty->setValue($checker, $securityPolicy);

        $auditLoggerProperty = $reflection->getProperty('auditLogger');
        $auditLoggerProperty->setAccessible(true);
        $auditLoggerProperty->setValue($checker, $auditLogger);

        $data = [
            'user_id' => 'test_user',
            'encrypted' => true,
            'access_controlled' => true,
            'collection_purpose' => 'testing'];

        // 执行（不应该抛出异常）
        $result = $checker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

        // 验证
        $this->assertIsArray($result);
        $this->assertArrayHasKey('compliant', $result);
    }

    public function testCheckPersonalDataDetection(): void
    {
        // 测试不同类型的个人数据检测
        $personalDataFields = ['email', 'phone', 'ssn', 'name', 'address', 'id_card'];

        foreach ($personalDataFields as $field) {
            $data = [
                'user_id' => 'test_user',
                $field => 'test_value',
                'encrypted' => false,  // 没有保护
                'access_controlled' => false,
                'collection_purpose' => 'testing'];

            $result = $this->complianceChecker->check(ComplianceChecker::CHECK_DATA_PRIVACY, $data);

            $this->assertFalse(
                $result['compliant'],
                "Personal data field '{$field}' should trigger compliance violation"
            );
            $this->assertIsArray($result['violations']);
            $this->assertContains('Personal data found without proper protection', $result['violations']);
        }
    }

    protected function onSetUp(): void
    {
        // 从容器获取 ComplianceChecker 服务
        /** @var ComplianceChecker $complianceChecker */
        $complianceChecker = self::getContainer()->get(ComplianceChecker::class);
        self::assertInstanceOf(ComplianceChecker::class, $complianceChecker);
        $this->complianceChecker = $complianceChecker;

        // 创建 mock 对象用于测试
        $this->securityPolicy = $this->createMock(SecurityPolicy::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->complianceChecker);

        $securityPolicyProperty = $reflection->getProperty('securityPolicy');
        $securityPolicyProperty->setAccessible(true);
        $securityPolicyProperty->setValue($this->complianceChecker, $this->securityPolicy);

        $auditLoggerProperty = $reflection->getProperty('auditLogger');
        $auditLoggerProperty->setAccessible(true);
        $auditLoggerProperty->setValue($this->complianceChecker, $this->auditLogger);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->complianceChecker, $this->logger);
    }
}
