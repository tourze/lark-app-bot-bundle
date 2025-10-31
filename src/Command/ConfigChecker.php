<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 配置检查辅助类.
 */
class ConfigChecker
{
    public function __construct(
        /** @var array<string, mixed> */ private readonly array $config,
        private readonly SymfonyStyle $io,
    ) {
    }

    public function checkBasicConfig(): bool
    {
        $this->io->section('基本配置检查');

        $issues = $this->validateBasicConfig();

        if ([] === $issues) {
            $this->io->success('基本配置检查通过');

            return true;
        }

        $this->io->error('发现配置问题：');
        $this->io->listing($issues);

        return false;
    }

    public function checkNetworkConfig(): bool
    {
        $this->io->section('网络配置检查');

        $issues = $this->validateNetworkConfig();

        if ([] === $issues) {
            $this->io->success('网络配置检查通过');

            return true;
        }

        $this->io->warning('网络配置建议：');
        $this->io->listing($issues);

        return true; // 这些是建议，不是错误
    }

    public function checkCacheConfig(): bool
    {
        $this->io->section('缓存配置检查');

        if (!isset($this->config['cache'])) {
            $this->io->note('未配置缓存设置，将使用默认配置');

            return true;
        }

        $this->io->success('缓存配置检查通过');

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function validateBasicConfig(): array
    {
        $issues = [];

        $appId = $this->config['app_id'] ?? null;
        if (null === $appId || '' === $appId) {
            $issues[] = 'App ID 未配置或为空';
        }

        $appSecret = $this->config['app_secret'] ?? null;
        if (null === $appSecret || '' === $appSecret) {
            $issues[] = 'App Secret 未配置或为空';
        }

        $verificationToken = $this->config['verification_token'] ?? null;
        if (null === $verificationToken || '' === $verificationToken) {
            $issues[] = 'Verification Token 未配置或为空';
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function validateNetworkConfig(): array
    {
        $suggestions = [];

        if (!isset($this->config['timeout']) || $this->config['timeout'] < 5) {
            $suggestions[] = '建议设置超时时间至少5秒';
        }

        if (!isset($this->config['retry_times']) || $this->config['retry_times'] < 3) {
            $suggestions[] = '建议设置重试次数至少3次';
        }

        return $suggestions;
    }
}
