<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 访问控制列表.
 *
 * 管理资源的访问权限
 */
#[Autoconfigure(public: true)]
class AccessControlList
{
    /**
     * 访问控制类型.
     */
    public const TYPE_ALLOW = 'allow';
    public const TYPE_DENY = 'deny';

    /**
     * 资源类型.
     */
    public const RESOURCE_CHAT = 'chat';
    public const RESOURCE_FILE = 'file';
    public const RESOURCE_API = 'api';
    public const RESOURCE_FEATURE = 'feature';

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    /**
     * ACL规则存储.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $rules = [];

    public function __construct(
        CacheItemPoolInterface $cache,
        ?LoggerInterface $logger = null,
    ) {
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
        $this->loadRules();
    }

    /**
     * 添加访问规则.
     *
     * @param string               $resourceType 资源类型
     * @param string               $resourceId   资源ID
     * @param string               $principal    主体（用户ID或角色）
     * @param string               $type         规则类型（allow/deny）
     * @param array<string, mixed> $conditions   条件
     */
    public function addRule(
        string $resourceType,
        string $resourceId,
        string $principal,
        string $type,
        array $conditions = [],
    ): void {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);

        $rule = [
            'principal' => $principal,
            'type' => $type,
            'conditions' => $conditions,
            'created_at' => time(),
        ];

        if (!isset($this->rules[$ruleKey])) {
            $this->rules[$ruleKey] = [];
        }

        // 检查是否已存在相同的规则（资源类型、资源ID、主体和类型都相同）
        foreach ($this->rules[$ruleKey] as $index => $existingRule) {
            if ($existingRule['principal'] === $principal && $existingRule['type'] === $type) {
                // 更新现有规则的条件
                $this->rules[$ruleKey][$index]['conditions'] = $conditions;
                $this->rules[$ruleKey][$index]['updated_at'] = time();
                $this->saveRules();

                return;
            }
        }

        // 添加新规则
        $this->rules[$ruleKey][] = $rule;
        $this->saveRules();

        $this->logger->info('ACL rule added', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'principal' => $principal,
            'type' => $type,
        ]);
    }

    /**
     * 移除访问规则.
     *
     * @param string $resourceType 资源类型
     * @param string $resourceId   资源ID
     * @param string $principal    主体
     */
    public function removeRule(string $resourceType, string $resourceId, string $principal): void
    {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);

        if (!isset($this->rules[$ruleKey])) {
            return;
        }

        $this->rules[$ruleKey] = array_filter(
            $this->rules[$ruleKey],
            fn ($rule) => $rule['principal'] !== $principal
        );

        if (!isset($this->rules[$ruleKey]) || [] === $this->rules[$ruleKey]) {
            unset($this->rules[$ruleKey]);
        }

        $this->saveRules();

        $this->logger->info('ACL rule removed', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'principal' => $principal,
        ]);
    }

    /**
     * 检查访问权限.
     *
     * @param string               $resourceType 资源类型
     * @param string               $resourceId   资源ID
     * @param string               $userId       用户ID
     * @param array<string, mixed> $context      上下文信息
     */
    public function checkAccess(
        string $resourceType,
        string $resourceId,
        string $userId,
        array $context = [],
    ): bool {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);

        if (!isset($this->rules[$ruleKey])) {
            return $this->getDefaultPolicy($resourceType);
        }

        $accessResult = $this->evaluateRules($this->rules[$ruleKey], $userId, $context);
        $hasAccess = $this->applyDenyPriority($accessResult);

        $this->logAccessCheck($resourceType, $resourceId, $userId, $hasAccess);

        return $hasAccess;
    }

    /**
     * 获取资源的所有规则.
     *
     * @param string $resourceType 资源类型
     * @param string $resourceId   资源ID
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRules(string $resourceType, string $resourceId): array
    {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);

        return $this->rules[$ruleKey] ?? [];
    }

    /**
     * 批量设置规则.
     *
     * @param string                           $resourceType 资源类型
     * @param string                           $resourceId   资源ID
     * @param array<int, array<string, mixed>> $rules        规则列表
     */
    public function setRules(string $resourceType, string $resourceId, array $rules): void
    {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);
        $this->rules[$ruleKey] = $rules;
        $this->saveRules();

        $this->logger->info('ACL rules set', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'rule_count' => \count($rules),
        ]);
    }

    /**
     * 清除资源的所有规则.
     *
     * @param string $resourceType 资源类型
     * @param string $resourceId   资源ID
     */
    public function clearRules(string $resourceType, string $resourceId): void
    {
        $ruleKey = $this->getRuleKey($resourceType, $resourceId);
        unset($this->rules[$ruleKey]);
        $this->saveRules();

        $this->logger->info('ACL rules cleared', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * 评估规则并返回访问结果.
     *
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed>             $context
     *
     * @return array{allow: bool, deny: bool}
     */
    private function evaluateRules(array $rules, string $userId, array $context): array
    {
        $allow = false;
        $deny = false;

        foreach ($rules as $rule) {
            if (!$this->shouldApplyRule($rule, $userId, $context)) {
                continue;
            }

            if (self::TYPE_ALLOW === $rule['type']) {
                $allow = true;
            } elseif (self::TYPE_DENY === $rule['type']) {
                $deny = true;
            }
        }

        return ['allow' => $allow, 'deny' => $deny];
    }

    /**
     * 判断是否应该应用规则.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $context
     */
    private function shouldApplyRule(array $rule, string $userId, array $context): bool
    {
        // 确保必要的字段存在且类型正确
        assert(isset($rule['principal']), 'Rule must have a principal');
        assert(isset($rule['conditions']), 'Rule must have conditions');
        assert(is_string($rule['principal']), 'Principal must be a string');
        assert(is_array($rule['conditions']), 'Conditions must be an array');

        return $this->matchPrincipal($rule['principal'], $userId, $context)
            && $this->evaluateConditions($rule['conditions'], $context);
    }

    /**
     * 应用 Deny 优先策略.
     *
     * @param array{allow: bool, deny: bool} $accessResult
     */
    private function applyDenyPriority(array $accessResult): bool
    {
        return !$accessResult['deny'] && $accessResult['allow'];
    }

    /**
     * 记录访问检查日志.
     */
    private function logAccessCheck(string $resourceType, string $resourceId, string $userId, bool $hasAccess): void
    {
        $this->logger->debug('Access check result', [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'user_id' => $userId,
            'has_access' => $hasAccess,
        ]);
    }

    /**
     * 匹配主体.
     *
     * @param string               $principal 规则中的主体
     * @param string               $userId    用户ID
     * @param array<string, mixed> $context   上下文
     */
    private function matchPrincipal(string $principal, string $userId, array $context): bool
    {
        // 直接匹配用户ID
        if ($principal === $userId) {
            return true;
        }

        // 匹配角色
        if (str_starts_with($principal, 'role:')) {
            $role = substr($principal, 5);
            /** @var mixed $userRoles */
            $userRoles = $context['roles'] ?? [];

            // 确保 userRoles 是数组
            assert(is_array($userRoles), 'User roles must be an array');

            return \in_array($role, $userRoles, true);
        }

        // 匹配组
        if (str_starts_with($principal, 'group:')) {
            $group = substr($principal, 6);
            /** @var mixed $userGroups */
            $userGroups = $context['groups'] ?? [];

            // 确保 userGroups 是数组
            assert(is_array($userGroups), 'User groups must be an array');

            return \in_array($group, $userGroups, true);
        }

        // 匹配所有外部用户
        if ('external:*' === $principal) {
            return $context['is_external'] ?? false;
        }

        // 匹配所有内部用户
        if ('internal:*' === $principal) {
            return !((bool) ($context['is_external'] ?? false));
        }

        // 匹配所有用户
        if ('*' === $principal) {
            return true;
        }

        return false;
    }

    /**
     * 评估条件.
     *
     * @param array<string, mixed> $conditions 条件
     * @param array<string, mixed> $context    上下文
     */
    private function evaluateConditions(array $conditions, array $context): bool
    {
        if ([] === $conditions) {
            return true;
        }

        foreach ($conditions as $key => $value) {
            if (!$this->evaluateSingleCondition($key, $value, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 评估单个条件.
     *
     * @param array<string, mixed> $context
     */
    private function evaluateSingleCondition(string|int $key, mixed $value, array $context): bool
    {
        if (!isset($context[$key])) {
            return false;
        }

        if (\is_array($value)) {
            return $this->evaluateMultiValueCondition($context[$key], $value);
        }

        return $this->evaluateSingleValueCondition($context[$key], $value);
    }

    /**
     * 评估多值条件.
     *
     * @param mixed[] $allowedValues
     */
    private function evaluateMultiValueCondition(mixed $contextValue, array $allowedValues): bool
    {
        return \in_array($contextValue, $allowedValues, true);
    }

    /**
     * 评估单值条件.
     */
    private function evaluateSingleValueCondition(mixed $contextValue, mixed $expectedValue): bool
    {
        return $contextValue === $expectedValue;
    }

    /**
     * 获取默认策略.
     *
     * @param string $resourceType 资源类型
     */
    private function getDefaultPolicy(string $resourceType): bool
    {
        // 默认策略：内部资源允许访问，外部资源拒绝访问
        return match ($resourceType) {
            self::RESOURCE_API, self::RESOURCE_FILE => false,
            self::RESOURCE_CHAT, self::RESOURCE_FEATURE => true,
            default => false,
        };
    }

    /**
     * 获取规则键.
     *
     * @param string $resourceType 资源类型
     * @param string $resourceId   资源ID
     */
    private function getRuleKey(string $resourceType, string $resourceId): string
    {
        return \sprintf('%s:%s', $resourceType, $resourceId);
    }

    /**
     * 加载规则.
     */
    private function loadRules(): void
    {
        $cacheItem = $this->cache->getItem('acl_rules');

        if ($cacheItem->isHit()) {
            $this->rules = $cacheItem->get();
        }
    }

    /**
     * 保存规则.
     */
    private function saveRules(): void
    {
        $cacheItem = $this->cache->getItem('acl_rules');
        $cacheItem->set($this->rules);
        $cacheItem->expiresAfter(null); // 永久保存
        $this->cache->save($cacheItem);
    }
}
