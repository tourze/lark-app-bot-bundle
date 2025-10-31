<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 安全策略.
 *
 * 定义和执行外部协作的安全规则
 */
#[Autoconfigure(public: true)]
class SecurityPolicy
{
    /**
     * 安全策略类型.
     */
    public const POLICY_DATA_ACCESS = 'data_access';
    public const POLICY_FILE_SHARING = 'file_sharing';
    public const POLICY_MESSAGE_RETENTION = 'message_retention';
    public const POLICY_AUDIT_LOGGING = 'audit_logging';
    public const POLICY_IP_WHITELIST = 'ip_whitelist';
    public const POLICY_TIME_RESTRICTION = 'time_restriction';

    private LoggerInterface $logger;

    /**
     * 默认安全策略配置.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $defaultPolicies = [
        self::POLICY_DATA_ACCESS => [
            'enabled' => true,
            'external_access_level' => 'restricted',
            'require_approval' => true,
            'data_classification' => ['public', 'internal'],
        ],
        self::POLICY_FILE_SHARING => [
            'enabled' => false,
            'max_file_size_mb' => 10,
            'allowed_file_types' => ['pdf', 'txt', 'doc', 'docx'],
            'scan_for_malware' => true,
        ],
        self::POLICY_MESSAGE_RETENTION => [
            'enabled' => true,
            'retention_days' => 90,
            'delete_after_retention' => false,
            'archive_location' => 'compliance_archive',
        ],
        self::POLICY_AUDIT_LOGGING => [
            'enabled' => true,
            'log_level' => 'all',
            'include_message_content' => true,
            'retention_days' => 365,
        ],
        self::POLICY_IP_WHITELIST => [
            'enabled' => false,
            'allowed_ips' => [],
            'allow_vpn' => false,
        ],
        self::POLICY_TIME_RESTRICTION => [
            'enabled' => false,
            'allowed_hours' => ['9:00', '18:00'],
            'timezone' => 'Asia/Shanghai',
            'weekends_allowed' => false,
        ],
    ];

    /**
     * 活动策略配置.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $activePolicies;

    /**
     * @param LoggerInterface|null                     $logger         日志记录器
     * @param array<string, array<string, mixed>>|null $customPolicies 自定义策略配置
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        ?array $customPolicies = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->activePolicies = $customPolicies ?? $this->defaultPolicies;
    }

    /**
     * 检查策略是否允许操作.
     *
     * @param string               $policyType 策略类型
     * @param array<string, mixed> $context    上下文信息
     */
    public function checkPolicy(string $policyType, array $context): bool
    {
        if (!isset($this->activePolicies[$policyType])) {
            $this->logger->warning('Unknown policy type', [
                'policy_type' => $policyType,
            ]);

            return false;
        }

        $policy = $this->activePolicies[$policyType];

        if (!((bool) ($policy['enabled'] ?? false))) {
            return true; // 策略未启用，默认允许
        }

        $result = match ($policyType) {
            self::POLICY_DATA_ACCESS => $this->checkDataAccessPolicy($policy, $context),
            self::POLICY_FILE_SHARING => $this->checkFileSharingPolicy($policy, $context),
            self::POLICY_MESSAGE_RETENTION => $this->checkMessageRetentionPolicy($policy, $context),
            self::POLICY_IP_WHITELIST => $this->checkIpWhitelistPolicy($policy, $context),
            self::POLICY_TIME_RESTRICTION => $this->checkTimeRestrictionPolicy($policy, $context),
            default => false,
        };

        $this->logger->info('Policy check result', [
            'policy_type' => $policyType,
            'context' => $context,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * 获取所有策略配置.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllPolicies(): array
    {
        return $this->activePolicies;
    }

    /**
     * 获取特定策略配置.
     *
     * @param string $policyType 策略类型
     *
     * @return array<string, mixed>|null
     */
    public function getPolicy(string $policyType): ?array
    {
        return $this->activePolicies[$policyType] ?? null;
    }

    /**
     * 更新策略配置.
     *
     * @param string               $policyType 策略类型
     * @param array<string, mixed> $config     配置
     */
    public function updatePolicy(string $policyType, array $config): void
    {
        $this->activePolicies[$policyType] = array_merge(
            $this->activePolicies[$policyType] ?? [],
            $config
        );

        $this->logger->info('Policy updated', [
            'policy_type' => $policyType,
            'config' => $config,
        ]);
    }

    /**
     * 重置策略到默认值
     *
     * @param string|null $policyType 策略类型，null表示重置所有
     */
    public function resetToDefault(?string $policyType = null): void
    {
        if (null === $policyType) {
            $this->activePolicies = $this->defaultPolicies;
        } else {
            $this->activePolicies[$policyType] = $this->defaultPolicies[$policyType] ?? [];
        }

        $this->logger->info('Policy reset to default', [
            'policy_type' => $policyType,
        ]);
    }

    /**
     * 检查数据访问策略.
     *
     * @param array<string, mixed> $policy  策略配置
     * @param array<string, mixed> $context 上下文
     */
    private function checkDataAccessPolicy(array $policy, array $context): bool
    {
        $dataClassification = $context['data_classification'] ?? 'internal';
        $allowedClassifications = $policy['data_classification'] ?? [];

        if (!\in_array($dataClassification, $allowedClassifications, true)) {
            return false;
        }

        if ((bool) ($policy['require_approval'] ?? false)) {
            // 检查是否有审批记录
            return (bool) ($context['has_approval'] ?? false);
        }

        return true;
    }

    /**
     * 检查文件共享策略.
     *
     * @param array<string, mixed> $policy  策略配置
     * @param array<string, mixed> $context 上下文
     */
    private function checkFileSharingPolicy(array $policy, array $context): bool
    {
        $fileSize = $context['file_size_mb'] ?? 0;
        $fileType = $context['file_type'] ?? '';

        if ($fileSize > ($policy['max_file_size_mb'] ?? 10)) {
            return false;
        }

        $allowedTypes = $policy['allowed_file_types'] ?? [];
        if (!\in_array($fileType, $allowedTypes, true)) {
            return false;
        }

        if ((bool) ($policy['scan_for_malware'] ?? true)) {
            // 这里应该调用病毒扫描服务
            return (bool) ($context['malware_scan_passed'] ?? false);
        }

        return true;
    }

    /**
     * 检查消息保留策略.
     *
     * @param array<string, mixed> $policy  策略配置
     * @param array<string, mixed> $context 上下文
     */
    private function checkMessageRetentionPolicy(array $policy, array $context): bool
    {
        // 消息保留策略通常用于合规性，这里只记录
        $messageAge = $context['message_age_days'] ?? 0;
        $retentionDays = $policy['retention_days'] ?? 90;

        return $messageAge <= $retentionDays;
    }

    /**
     * 检查IP白名单策略.
     *
     * @param array<string, mixed> $policy  策略配置
     * @param array<string, mixed> $context 上下文
     */
    private function checkIpWhitelistPolicy(array $policy, array $context): bool
    {
        $userIp = $context['user_ip'] ?? '';
        $allowedIps = $policy['allowed_ips'] ?? [];
        if (!is_iterable($allowedIps)) {
            $allowedIps = [];
        }

        if ([] === $allowedIps) {
            return true; // 没有配置白名单，允许所有
        }

        foreach ($allowedIps as $allowedIp) {
            if (is_scalar($allowedIp)) {
                if ($this->isIpInRange((string) $userIp, (string) $allowedIp)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查时间限制策略.
     *
     * @param array<string, mixed> $policy  策略配置
     * @param array<string, mixed> $context 上下文
     */
    private function checkTimeRestrictionPolicy(array $policy, array $context): bool
    {
        $timezone = new \DateTimeZone($policy['timezone'] ?? 'Asia/Shanghai');
        $now = new \DateTime('now', $timezone);

        // 检查是否在允许的时间范围内
        $allowedHours = $policy['allowed_hours'] ?? ['9:00', '18:00'];

        // 验证时间格式和数组结构
        if (!is_array($allowedHours) || \count($allowedHours) < 2) {
            $this->logger->warning('Invalid allowed_hours configuration, using default', [
                'allowed_hours' => $allowedHours,
            ]);
            $allowedHours = ['9:00', '18:00'];
        }

        $currentTime = $now->format('H:i');
        $startTime = $allowedHours[0];
        $endTime = $allowedHours[1];

        if ($currentTime < $startTime || $currentTime > $endTime) {
            return false;
        }

        // 检查是否是周末
        if (!((bool) ($policy['weekends_allowed'] ?? false))) {
            $dayOfWeek = (int) $now->format('w');
            if (0 === $dayOfWeek || 6 === $dayOfWeek) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查IP是否在指定范围内.
     *
     * @param string $ip    IP地址
     * @param string $range IP范围（支持CIDR格式）
     */
    private function isIpInRange(string $ip, string $range): bool
    {
        if ($ip === $range) {
            return true;
        }

        if (str_contains($range, '/')) {
            // CIDR格式
            [$subnet, $bits] = explode('/', $range);
            $ip_binary = \sprintf('%032b', ip2long($ip));
            $subnet_binary = \sprintf('%032b', ip2long($subnet));

            return substr($ip_binary, 0, (int) $bits) === substr($subnet_binary, 0, (int) $bits);
        }

        return false;
    }
}
