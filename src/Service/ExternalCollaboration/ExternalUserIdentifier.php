<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 外部用户识别器.
 *
 * 用于识别和验证外部用户身份
 */
#[Autoconfigure(public: true)]
final class ExternalUserIdentifier
{
    /**
     * 外部用户标识前缀
     */
    private const EXTERNAL_USER_PREFIX = 'ou_external_';

    /**
     * 外部群组标识前缀
     */
    private const EXTERNAL_GROUP_PREFIX = 'oc_external_';

    private LoggerInterface $logger;

    public function __construct(
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 判断是否为外部用户.
     *
     * @param string $userId 用户ID
     */
    public function isExternalUser(string $userId): bool
    {
        // 飞书外部用户的open_id通常有特定前缀
        $isExternal = str_starts_with($userId, self::EXTERNAL_USER_PREFIX);

        $this->logger->debug('External user check', [
            'user_id' => $userId,
            'is_external' => $isExternal,
        ]);

        return $isExternal;
    }

    /**
     * 判断是否为外部群组.
     *
     * @param string $chatId 群组ID
     */
    public function isExternalGroup(string $chatId): bool
    {
        // 外部群组的chat_id通常有特定前缀
        $isExternal = str_starts_with($chatId, self::EXTERNAL_GROUP_PREFIX);

        $this->logger->debug('External group check', [
            'chat_id' => $chatId,
            'is_external' => $isExternal,
        ]);

        return $isExternal;
    }

    /**
     * 从用户信息中识别外部用户.
     *
     * @param array<string, mixed> $userInfo 用户信息
     */
    public function identifyFromUserInfo(array $userInfo): bool
    {
        // 检查明确的用户类型标识
        $userType = $userInfo['user_type'] ?? null;
        if ('external' === $userType) {
            return true;
        }
        if ('internal' === $userType) {
            return false;
        }

        // 检查用户ID是否为外部用户
        if ($this->isExternalUserById($userInfo)) {
            return true;
        }

        // 检查邮箱域名是否为外部域名
        if ($this->isExternalUserByEmail($userInfo)) {
            return true;
        }

        // 检查部门信息（仅当缺乏其他明确标识时）
        return $this->isExternalUserByDepartment($userInfo);
    }

    /**
     * 获取外部用户标签.
     *
     * @param string $userId 用户ID
     *
     * @return array<string, mixed>
     */
    public function getExternalUserTags(string $userId): array
    {
        if (!$this->isExternalUser($userId)) {
            return [];
        }

        return [
            'is_external' => true,
            'user_type' => 'external',
            'access_level' => 'restricted',
            'requires_approval' => true,
        ];
    }

    /**
     * 验证外部用户访问权限.
     *
     * @param string $userId   用户ID
     * @param string $resource 资源标识
     */
    public function validateExternalAccess(string $userId, string $resource): bool
    {
        if (!$this->isExternalUser($userId)) {
            // 内部用户默认有访问权限
            return true;
        }

        // 外部用户需要特殊权限检查
        $this->logger->info('Validating external user access', [
            'user_id' => $userId,
            'resource' => $resource,
        ]);

        // TODO: 实现具体的权限检查逻辑
        return false;
    }

    /**
     * 通过用户ID判断是否为外部用户.
     *
     * @param array<string, mixed> $userInfo
     */
    private function isExternalUserById(array $userInfo): bool
    {
        return isset($userInfo['open_id']) && $this->isExternalUser($userInfo['open_id']);
    }

    /**
     * 通过邮箱域名判断是否为外部用户.
     *
     * @param array<string, mixed> $userInfo
     */
    private function isExternalUserByEmail(array $userInfo): bool
    {
        return isset($userInfo['email']) && !$this->isInternalEmailDomain($userInfo['email']);
    }

    /**
     * 通过部门信息判断是否为外部用户（仅当缺乏其他明确标识时）.
     *
     * @param array<string, mixed> $userInfo
     */
    private function isExternalUserByDepartment(array $userInfo): bool
    {
        $hasExplicitIdentifiers = isset($userInfo['user_type'])
            || isset($userInfo['open_id'])
            || isset($userInfo['email']);

        if ($hasExplicitIdentifiers) {
            return false;
        }

        $hasDepartment = isset($userInfo['department_ids']) && [] !== $userInfo['department_ids'];
        if ($hasDepartment) {
            return false;
        }

        $this->logger->info('User identified as external due to missing department info', [
            'user_info' => $userInfo,
        ]);

        return true;
    }

    /**
     * 检查邮箱是否为内部域名.
     *
     * @param string $email 邮箱地址
     */
    private function isInternalEmailDomain(string $email): bool
    {
        // 这里应该从配置中获取公司内部域名列表
        $internalDomains = $this->getInternalDomains();

        $atPos = strrchr($email, '@');
        if (false === $atPos) {
            // 没有找到@符号，不是有效的邮箱地址
            return false;
        }

        $domain = substr($atPos, 1);

        return \in_array($domain, $internalDomains, true);
    }

    /**
     * 获取内部域名列表.
     *
     * @return array<int, string>
     */
    private function getInternalDomains(): array
    {
        // TODO: 从配置中读取
        return [
            'company.com',
            'company.cn',
            'internal.company.com',
        ];
    }
}
