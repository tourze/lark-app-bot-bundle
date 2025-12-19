<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 合规性检查器.
 *
 * 确保外部协作符合法规和公司政策
 */
#[Autoconfigure(public: true)]
final class ComplianceChecker
{
    /**
     * 合规检查类型.
     */
    public const CHECK_DATA_PRIVACY = 'data_privacy';
    public const CHECK_EXPORT_CONTROL = 'export_control';
    public const CHECK_RETENTION_POLICY = 'retention_policy';
    public const CHECK_ACCESS_CONTROL = 'access_control';
    public const CHECK_AUDIT_TRAIL = 'audit_trail';
    public const CHECK_ENCRYPTION = 'encryption';
    public const CHECK_GDPR = 'gdpr';
    public const CHECK_HIPAA = 'hipaa';

    private SecurityPolicy $securityPolicy;

    private AuditLogger $auditLogger;

    private LoggerInterface $logger;

    /**
     * 合规规则配置.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $complianceRules = [
        self::CHECK_DATA_PRIVACY => [
            'enabled' => true,
            'rules' => [
                'personal_data_protection' => true,
                'data_minimization' => true,
                'purpose_limitation' => true,
            ],
        ],
        self::CHECK_EXPORT_CONTROL => [
            'enabled' => true,
            'rules' => [
                'restricted_countries' => ['XX', 'YY'],
                'sensitive_technologies' => ['encryption', 'ai'],
            ],
        ],
        self::CHECK_RETENTION_POLICY => [
            'enabled' => true,
            'rules' => [
                'min_retention_days' => 30,
                'max_retention_days' => 365,
                'deletion_required' => true,
            ],
        ],
        self::CHECK_GDPR => [
            'enabled' => true,
            'rules' => [
                'consent_required' => true,
                'right_to_erasure' => true,
                'data_portability' => true,
            ],
        ],
    ];

    public function __construct(
        SecurityPolicy $securityPolicy,
        AuditLogger $auditLogger,
        ?LoggerInterface $logger = null,
    ) {
        $this->securityPolicy = $securityPolicy;
        $this->auditLogger = $auditLogger;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 执行合规性检查.
     *
     * @param string               $checkType 检查类型
     * @param array<string, mixed> $data      检查数据
     *
     * @return array<string, mixed>
     */
    public function check(string $checkType, array $data): array
    {
        $result = [
            'compliant' => true,
            'violations' => [],
            'warnings' => [],
            'check_type' => $checkType,
            'checked_at' => time(),
        ];

        if (!isset($this->complianceRules[$checkType])) {
            $result['compliant'] = false;
            $result['violations'][] = 'Unknown compliance check type';

            return $result;
        }

        $rules = $this->complianceRules[$checkType];

        if (!((bool) ($rules['enabled'] ?? false))) {
            $result['warnings'][] = 'Compliance check is disabled';

            return $result;
        }

        // 执行具体的合规检查
        $checkResult = match ($checkType) {
            self::CHECK_DATA_PRIVACY => $this->checkDataPrivacy($data, $rules),
            self::CHECK_EXPORT_CONTROL => $this->checkExportControl($data, $rules),
            self::CHECK_RETENTION_POLICY => $this->checkRetentionPolicy($data, $rules),
            self::CHECK_ACCESS_CONTROL => $this->checkAccessControl($data, $rules),
            self::CHECK_GDPR => $this->checkGdprCompliance($data, $rules),
            default => ['compliant' => false, 'violations' => ['Check not implemented']],
        };

        $result = array_merge($result, $checkResult);

        // 记录合规检查结果
        $this->logComplianceCheck($checkType, $data, $result);

        return $result;
    }

    /**
     * 批量执行合规性检查.
     *
     * @param array<int, string>   $checkTypes 检查类型列表
     * @param array<string, mixed> $data       检查数据
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkAll(array $checkTypes, array $data): array
    {
        $results = [];

        foreach ($checkTypes as $checkType) {
            $results[$checkType] = $this->check($checkType, $data);
        }

        return $results;
    }

    /**
     * 生成合规报告.
     *
     * @param array<string, mixed> $checkResults 检查结果
     *
     * @return array<string, mixed>
     */
    public function generateReport(array $checkResults): array
    {
        $report = [
            'generated_at' => time(),
            'overall_compliance' => true,
            'total_checks' => \count($checkResults),
            'failed_checks' => 0,
            'total_violations' => 0,
            'total_warnings' => 0,
            'details' => [],
        ];

        foreach ($checkResults as $checkType => $result) {
            // 确保$result是数组类型
            assert(is_array($result));

            $isCompliant = isset($result['compliant']) && true === $result['compliant'];
            if (!$isCompliant) {
                $report['overall_compliance'] = false;
                ++$report['failed_checks'];
            }

            $violations = $result['violations'] ?? [];
            $warnings = $result['warnings'] ?? [];

            $report['total_violations'] += \count($violations);
            $report['total_warnings'] += \count($warnings);

            $report['details'][$checkType] = [
                'compliant' => $result['compliant'] ?? false,
                'violations' => $violations,
                'warnings' => $warnings,
            ];
        }

        return $report;
    }

    /**
     * 检查数据隐私合规性.
     *
     * @param array<string, mixed> $data  数据
     * @param array<string, mixed> $rules 规则
     *
     * @return array<string, mixed>
     */
    private function checkDataPrivacy(array $data, array $rules): array
    {
        $result = ['compliant' => true, 'violations' => [], 'warnings' => []];

        $result = $this->checkPersonalDataProtection($data, $rules, $result);
        $result = $this->checkDataMinimization($data, $rules, $result);
        $result = $this->checkPurposeLimitation($data, $rules, $result);

        return $result;
    }

    /**
     * 检查个人数据保护.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkPersonalDataProtection(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'personal_data_protection')) {
            return $result;
        }

        if ($this->containsPersonalData($data) && !$this->hasDataProtection($data)) {
            $result['compliant'] = false;
            // 确保violations数组存在
            if (!isset($result['violations'])) {
                $result['violations'] = [];
            }
            \assert(\is_array($result['violations']));
            $result['violations'][] = 'Personal data found without proper protection';
        }

        return $result;
    }

    /**
     * 检查数据最小化原则.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkDataMinimization(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'data_minimization')) {
            return $result;
        }

        if ($this->hasExcessiveData($data)) {
            // 确保warnings数组存在
            if (!isset($result['warnings'])) {
                $result['warnings'] = [];
            }
            \assert(\is_array($result['warnings']));
            $result['warnings'][] = 'Data collection may violate minimization principle';
        }

        return $result;
    }

    /**
     * 检查目的限制.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkPurposeLimitation(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'purpose_limitation')) {
            return $result;
        }

        if (!$this->hasDefinedPurpose($data)) {
            $result['compliant'] = false;
            // 确保violations数组存在
            if (!isset($result['violations'])) {
                $result['violations'] = [];
            }
            \assert(\is_array($result['violations']));
            $result['violations'][] = 'Data collection purpose not clearly defined';
        }

        return $result;
    }

    /**
     * 检查规则是否启用.
     *
     * @param array<string, mixed> $rules
     */
    private function isRuleEnabled(array $rules, string $ruleName): bool
    {
        $rulesConfig = $rules['rules'] ?? [];
        \assert(\is_array($rulesConfig));

        return (bool) ($rulesConfig[$ruleName] ?? false);
    }

    /**
     * 检查出口管制合规性.
     *
     * @param array<string, mixed> $data  数据
     * @param array<string, mixed> $rules 规则
     *
     * @return array<string, mixed>
     */
    private function checkExportControl(array $data, array $rules): array
    {
        $result = ['compliant' => true, 'violations' => [], 'warnings' => []];

        // 检查受限国家
        $userCountry = $data['user_country'] ?? '';
        \assert(\is_string($userCountry));
        $rulesConfig = $rules['rules'] ?? [];
        \assert(\is_array($rulesConfig));
        $restrictedCountries = $rulesConfig['restricted_countries'] ?? [];
        if (!is_array($restrictedCountries)) {
            $restrictedCountries = [];
        }

        if (\in_array($userCountry, $restrictedCountries, true)) {
            $result['compliant'] = false;
            $result['violations'][] = \sprintf('Access from restricted country: %s', $userCountry);
        }

        // 检查敏感技术
        $technologies = $data['technologies'] ?? [];
        $sensitiveTech = $rulesConfig['sensitive_technologies'] ?? [];
        if (!is_iterable($technologies)) {
            $technologies = [];
        }
        if (!is_array($sensitiveTech)) {
            $sensitiveTech = [];
        }

        foreach ($technologies as $tech) {
            if (is_scalar($tech) && \in_array((string) $tech, $sensitiveTech, true)) {
                $result['warnings'][] = \sprintf('Sensitive technology involved: %s', (string) $tech);
            }
        }

        return $result;
    }

    /**
     * 检查数据保留策略.
     *
     * @param array<string, mixed> $data  数据
     * @param array<string, mixed> $rules 规则
     *
     * @return array<string, mixed>
     */
    private function checkRetentionPolicy(array $data, array $rules): array
    {
        $result = ['compliant' => true, 'violations' => [], 'warnings' => []];

        $retentionDays = $data['retention_days'] ?? 0;
        \assert(\is_int($retentionDays));
        $rulesConfig = $rules['rules'] ?? [];
        \assert(\is_array($rulesConfig));
        $minDays = $rulesConfig['min_retention_days'] ?? 30;
        \assert(\is_int($minDays));
        $maxDays = $rulesConfig['max_retention_days'] ?? 365;
        \assert(\is_int($maxDays));

        if ($retentionDays < $minDays) {
            $result['compliant'] = false;
            $result['violations'][] = \sprintf(
                'Retention period too short: %d days (minimum: %d)',
                $retentionDays,
                $minDays
            );
        }

        if ($retentionDays > $maxDays) {
            $result['compliant'] = false;
            $result['violations'][] = \sprintf(
                'Retention period too long: %d days (maximum: %d)',
                $retentionDays,
                $maxDays
            );
        }

        return $result;
    }

    /**
     * 检查访问控制合规性.
     *
     * @param array<string, mixed> $data  数据
     * @param array<string, mixed> $rules 规则
     *
     * @return array<string, mixed>
     */
    private function checkAccessControl(array $data, array $rules): array
    {
        $result = ['compliant' => true, 'violations' => [], 'warnings' => []];

        // 检查是否有适当的访问控制
        if (!isset($data['access_controls']) || [] === $data['access_controls']) {
            $result['compliant'] = false;
            $result['violations'][] = 'No access controls defined';
        }

        // 使用安全策略检查数据访问权限
        if (!$this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_DATA_ACCESS, $data)) {
            $result['compliant'] = false;
            $result['violations'][] = 'Data access violates security policy';
        }

        // 检查是否有审计跟踪
        if (!((bool) ($data['audit_enabled'] ?? false))) {
            $result['warnings'][] = 'Audit trail not enabled';
        }

        return $result;
    }

    /**
     * 检查GDPR合规性.
     *
     * @param array<string, mixed> $data  数据
     * @param array<string, mixed> $rules 规则
     *
     * @return array<string, mixed>
     */
    private function checkGdprCompliance(array $data, array $rules): array
    {
        $result = ['compliant' => true, 'violations' => [], 'warnings' => []];

        $result = $this->checkConsent($data, $rules, $result);
        $result = $this->checkRightToErasure($data, $rules, $result);
        $result = $this->checkDataPortability($data, $rules, $result);

        return $result;
    }

    /**
     * 检查用户同意.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkConsent(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'consent_required')) {
            return $result;
        }

        if (!((bool) ($data['user_consent'] ?? false))) {
            $result['compliant'] = false;
            \assert(\is_array($result['violations']));
            $result['violations'][] = 'User consent not obtained';
        }

        return $result;
    }

    /**
     * 检查删除权.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkRightToErasure(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'right_to_erasure')) {
            return $result;
        }

        if (!((bool) ($data['erasure_supported'] ?? false))) {
            $result['compliant'] = false;
            \assert(\is_array($result['violations']));
            $result['violations'][] = 'Right to erasure not implemented';
        }

        return $result;
    }

    /**
     * 检查数据可携性.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function checkDataPortability(array $data, array $rules, array $result): array
    {
        if (!$this->isRuleEnabled($rules, 'data_portability')) {
            return $result;
        }

        if (!((bool) ($data['export_supported'] ?? false))) {
            \assert(\is_array($result['warnings']));
            $result['warnings'][] = 'Data portability not fully supported';
        }

        return $result;
    }

    /**
     * 检查是否包含个人数据.
     *
     * @param array<string, mixed> $data 数据
     */
    private function containsPersonalData(array $data): bool
    {
        $personalDataKeys = ['email', 'phone', 'ssn', 'name', 'address', 'id_card'];

        foreach ($personalDataKeys as $key) {
            if (isset($data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否有数据保护.
     *
     * @param array<string, mixed> $data 数据
     */
    private function hasDataProtection(array $data): bool
    {
        return ((bool) ($data['encrypted'] ?? false)) && ((bool) ($data['access_controlled'] ?? false));
    }

    /**
     * 检查是否有过多数据.
     *
     * @param array<string, mixed> $data 数据
     */
    private function hasExcessiveData(array $data): bool
    {
        // 简化的检查：如果数据字段超过20个，可能违反最小化原则
        return \count($data) > 20;
    }

    /**
     * 检查是否有明确的目的.
     *
     * @param array<string, mixed> $data 数据
     */
    private function hasDefinedPurpose(array $data): bool
    {
        return isset($data['collection_purpose']) && '' !== $data['collection_purpose'];
    }

    /**
     * 记录合规检查.
     *
     * @param string               $checkType 检查类型
     * @param array<string, mixed> $data      数据
     * @param array<string, mixed> $result    结果
     */
    private function logComplianceCheck(string $checkType, array $data, array $result): void
    {
        $userId = $data['user_id'] ?? 'system';
        \assert(\is_string($userId));

        $this->auditLogger->log(
            'compliance_check',
            $userId,
            [
                'check_type' => $checkType,
                'compliant' => $result['compliant'],
                'violations' => $result['violations'],
                'warnings' => $result['warnings'],
            ],
            (isset($result['compliant']) && true === $result['compliant']) ? AuditLogger::LEVEL_INFO : AuditLogger::LEVEL_WARNING
        );

        $this->logger->info('Compliance check completed', [
            'check_type' => $checkType,
            'compliant' => $result['compliant'],
        ]);
    }
}
