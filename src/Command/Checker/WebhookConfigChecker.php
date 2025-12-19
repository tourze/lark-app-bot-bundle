<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Webhook配置检查器.
 */
final class WebhookConfigChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Webhook配置';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        $webhookConfig = $this->normalizeWebhookConfig();
        $this->runWebhookChecks($io, $webhookConfig);

        return false; // Webhook配置问题通常不是致命错误
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeWebhookConfig(): array
    {
        $webhookConfig = $this->config['webhook'] ?? [];

        // 如果不是数组，返回空数组
        if (!\is_array($webhookConfig)) {
            return [];
        }

        // 确保返回类型为 array<string, mixed>
        $result = [];
        foreach ($webhookConfig as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /** @param array<string, mixed> $webhookConfig */
    private function runWebhookChecks(SymfonyStyle $io, array $webhookConfig): void
    {
        $this->checkEncryptKey($io, $webhookConfig);
        $this->checkVerificationToken($io, $webhookConfig);
        $this->checkEventSubscriptions($io, $webhookConfig);
        $this->showWebhookInfo($io, $webhookConfig);
    }

    /** @param array<string, mixed> $webhookConfig */
    private function checkEncryptKey(SymfonyStyle $io, array $webhookConfig): void
    {
        $encryptKey = $webhookConfig['encrypt_key'] ?? null;
        if (null === $encryptKey || '' === $encryptKey) {
            $io->warning('未配置Webhook加密密钥，建议配置以提高安全性');
        } else {
            $io->success('Webhook加密密钥已配置');
        }
    }

    /** @param array<string, mixed> $webhookConfig */
    private function checkVerificationToken(SymfonyStyle $io, array $webhookConfig): void
    {
        $verificationToken = $webhookConfig['verification_token'] ?? null;
        if (null === $verificationToken || '' === $verificationToken) {
            $io->warning('未配置Webhook验证令牌，某些事件可能无法验证');
        } else {
            $io->success('Webhook验证令牌已配置');
        }
    }

    /** @param array<string, mixed> $webhookConfig */
    private function checkEventSubscriptions(SymfonyStyle $io, array $webhookConfig): void
    {
        $enabledEvents = $webhookConfig['enabled_events'] ?? [];
        if (!\is_array($enabledEvents)) {
            $enabledEvents = [];
        }

        if ([] === $enabledEvents) {
            $io->comment('未配置特定的事件订阅，将接收所有事件');
        } else {
            $io->success(\sprintf('已订阅 %d 个事件类型', \count($enabledEvents)));
            if ($io->isVerbose()) {
                $io->listing($enabledEvents);
            }
        }
    }

    /** @param array<string, mixed> $webhookConfig */
    private function showWebhookInfo(SymfonyStyle $io, array $webhookConfig): void
    {
        $io->section('Webhook配置信息');
        $io->text('请在飞书开放平台配置以下信息：');

        $encryptKeyExists = null !== ($webhookConfig['encrypt_key'] ?? null) && '' !== ($webhookConfig['encrypt_key'] ?? '');

        $io->definitionList(
            ['请求地址' => 'https://your-domain.com/lark/webhook'],
            ['加密策略' => $encryptKeyExists ? '加密' : '不加密'],
            ['验证方式' => 'Challenge Code'],
        );
    }
}
