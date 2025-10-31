<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Helper;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 配置验证辅助类.
 */
class ConfigurationValidator
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    /**
     * 检查Bundle是否已注册.
     */
    public function isBundleRegistered(): bool
    {
        return class_exists('Tourze\LarkAppBotBundle\LarkAppBotBundle');
    }

    /**
     * 检查配置是否已加载.
     */
    public function isConfigurationLoaded(): bool
    {
        return [] !== $this->config;
    }

    /**
     * 检查单个配置键.
     */
    public function checkSingleConfigKey(SymfonyStyle $io, string $key): bool
    {
        $value = $this->getParameter($key);

        if (null === $value || '' === $value) {
            $io->error("配置项 {$key} 未设置或为空");

            return true;
        }

        $io->success("配置项 {$key}: " . $this->maskSecret($value));

        return false;
    }

    /**
     * 检查必需的配置键.
     *
     * @return bool true if has error
     */
    public function checkRequiredConfigKeys(SymfonyStyle $io): bool
    {
        $requiredKeys = [
            'lark_app_bot.app_id',
            'lark_app_bot.app_secret',
            'lark_app_bot.api_domain',
        ];

        $hasError = false;
        foreach ($requiredKeys as $key) {
            $hasError = $this->checkSingleConfigKey($io, $key) || $hasError;
        }

        return $hasError;
    }

    /**
     * 检查API域名配置.
     */
    public function checkApiDomainConfig(SymfonyStyle $io): bool
    {
        $apiDomain = $this->getParameter('lark_app_bot.api_domain');
        $validDomains = [
            'https://open.feishu.cn',
            'https://open.larksuite.com',
        ];

        if (!$this->isValidApiDomain($apiDomain, $validDomains)) {
            $this->showApiDomainWarning($io, $apiDomain);

            return true;
        }

        $domainStr = \is_scalar($apiDomain) ? (string) $apiDomain : 'unknown';
        $io->success("API域名配置正确: {$domainStr}");

        return false;
    }

    /**
     * 检查API域名是否有效.
     *
     * @param array<string> $validDomains
     */
    private function isValidApiDomain(mixed $apiDomain, array $validDomains): bool
    {
        if (!\is_string($apiDomain)) {
            return false;
        }

        return \in_array($apiDomain, $validDomains, true);
    }

    /**
     * 显示API域名警告.
     */
    private function showApiDomainWarning(SymfonyStyle $io, mixed $apiDomain): void
    {
        $domain = \is_scalar($apiDomain) ? (string) $apiDomain : 'unknown';
        $io->warning([
            "API域名配置可能不正确: {$domain}",
            '推荐使用:',
            '  - https://open.feishu.cn (飞书)',
            '  - https://open.larksuite.com (Lark)',
        ]);
    }

    /**
     * 获取配置参数.
     */
    private function getParameter(string $name): mixed
    {
        $keys = explode('.', $name);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * 遮盖敏感信息.
     */
    private function maskSecret(mixed $secret): string
    {
        if (!\is_string($secret) || \strlen($secret) <= 8) {
            return '***';
        }

        return substr($secret, 0, 4) . '***' . substr($secret, -4);
    }
}
