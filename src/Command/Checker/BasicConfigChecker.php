<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\LarkAppBotBundle;

/**
 * 基本配置检查器.
 */
final class BasicConfigChecker extends BaseChecker
{
    public function getName(): string
    {
        return '基本配置';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        $checks = [
            fn () => $this->checkBundleRegistration($io, $fix),
            fn () => $this->checkConfigurationFile($io, $fix),
            fn () => $this->checkRequiredConfigKeys($io),
            fn () => $this->checkApiDomainConfig($io),
        ];

        return $this->executeCheckList($checks);
    }

    /** @param array<callable> $checks */
    private function executeCheckList(array $checks): bool
    {
        $hasError = false;

        foreach ($checks as $check) {
            $hasError = $check() || $hasError;
        }

        return $hasError;
    }

    private function checkBundleRegistration(SymfonyStyle $io, bool $fix): bool
    {
        if ($this->isBundleRegistered()) {
            $io->success('Bundle已正确注册');

            return false;
        }

        return $this->handleBundleRegistrationError($io, $fix);
    }

    private function checkConfigurationFile(SymfonyStyle $io, bool $fix): bool
    {
        if ($this->isConfigurationLoaded()) {
            $io->success('配置文件已加载');

            return false;
        }

        return $this->handleConfigurationFileError($io, $fix);
    }

    private function checkRequiredConfigKeys(SymfonyStyle $io): bool
    {
        $requiredKeys = ['app_id', 'app_secret'];
        $hasError = false;

        foreach ($requiredKeys as $key) {
            $hasError = $this->checkSingleConfigKey($io, $key) || $hasError;
        }

        return $hasError;
    }

    private function checkApiDomainConfig(SymfonyStyle $io): bool
    {
        $apiDomain = $this->config['api_domain'] ?? 'https://open.feishu.cn';
        $validDomains = ['https://open.feishu.cn', 'https://open.larksuite.com'];

        if ($this->isValidApiDomain($apiDomain, $validDomains)) {
            $io->success(\sprintf('API域名: %s', \is_string($apiDomain) ? $apiDomain : 'invalid'));

            return false;
        }

        $this->showApiDomainWarning($io, $apiDomain);

        return false; // 这只是警告，不算错误
    }

    private function isBundleRegistered(): bool
    {
        $bundles = $this->getParameter('kernel.bundles');

        return \is_array($bundles) && isset($bundles[LarkAppBotBundle::class]);
    }

    private function isConfigurationLoaded(): bool
    {
        return [] !== $this->config;
    }

    private function checkSingleConfigKey(SymfonyStyle $io, string $key): bool
    {
        $configValue = $this->config[$key] ?? null;
        if (null === $configValue || '' === $configValue) {
            $io->error(\sprintf('缺少必需的配置项: %s', $key));

            return true;
        }

        $maskedValue = \is_string($configValue) ? $this->maskSecret($configValue) : 'invalid';
        $io->success(\sprintf('%s: %s', $key, $maskedValue));

        return false;
    }

    private function handleBundleRegistrationError(SymfonyStyle $io, bool $fix): bool
    {
        $io->error('LarkAppBotBundle 未在 config/bundles.php 中注册');
        if ($fix) {
            $this->showBundleRegistrationFix($io);
        }

        return true;
    }

    private function handleConfigurationFileError(SymfonyStyle $io, bool $fix): bool
    {
        $io->error('未找到 lark_app_bot 配置');
        if ($fix) {
            $this->showConfigurationFileFix($io);
        }

        return true;
    }

    /**
     * @param array<string> $validDomains
     */
    private function isValidApiDomain(mixed $apiDomain, array $validDomains): bool
    {
        return \in_array($apiDomain, $validDomains, true);
    }

    private function showApiDomainWarning(SymfonyStyle $io, mixed $apiDomain): void
    {
        $io->warning(\sprintf('API域名配置可能不正确: %s', \is_string($apiDomain) ? $apiDomain : 'invalid'));
        $io->comment('建议使用: https://open.feishu.cn (中国) 或 https://open.larksuite.com (国际)');
    }

    private function showBundleRegistrationFix(SymfonyStyle $io): void
    {
        $io->comment('请在 config/bundles.php 中添加：');
        $io->text('Tourze\Component\Lark\AppBot\LarkAppBotBundle::class => [\'all\' => true],');
    }

    private function showConfigurationFileFix(SymfonyStyle $io): void
    {
        $io->comment('请创建 config/packages/lark_app_bot.yaml 文件');
        $this->showConfigExample($io);
    }

    private function showConfigExample(SymfonyStyle $io): void
    {
        $example = <<<'YAML'
            lark_app_bot:
                app_id: '%env(LARK_APP_ID)%'
                app_secret: '%env(LARK_APP_SECRET)%'
                api_domain: 'https://open.feishu.cn'

                webhook:
                    encrypt_key: '%env(LARK_ENCRYPT_KEY)%'
                    verification_token: '%env(LARK_VERIFICATION_TOKEN)%'
                    enabled_events:
                        - 'im.message.receive_v1'
                        - 'im.chat.member.user.added_v1'
                        - 'im.chat.member.user.deleted_v1'

                cache:
                    pool: 'cache.app'
                    ttl: 3600

                token_provider: 'file_cache'

                rate_limit:
                    max_requests: 100
                    time_window: 60
            YAML;

        $io->text($example);
    }

    private function getParameter(string $name): mixed
    {
        return match ($name) {
            'kernel.bundles' => [],
            'kernel.cache_dir' => sys_get_temp_dir() . '/symfony/cache',
            default => null,
        };
    }
}
