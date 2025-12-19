<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;

/**
 * 认证配置检查器.
 */
final class AuthConfigChecker extends BaseChecker
{
    public function __construct(
        array $config,
        private readonly TokenProviderInterface $tokenManager,
    ) {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return '认证配置';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        return $this->checkWithTokenVisibility($io, $fix, false);
    }

    public function checkWithTokenVisibility(SymfonyStyle $io, bool $fix, bool $showToken): bool
    {
        $hasError = $this->verifyTokenAccess($io, $showToken, $fix);
        $this->displayTokenProviderInfo($io);

        return $hasError;
    }

    private function verifyTokenAccess(SymfonyStyle $io, bool $showToken, bool $fix): bool
    {
        try {
            $token = $this->tokenManager->getToken();
            $io->success('成功获取Access Token');
            $this->displayTokenInfo($io, $token, $showToken);

            return false;
        } catch (\Exception $e) {
            return $this->handleTokenError($io, $e, $fix);
        }
    }

    private function displayTokenInfo(SymfonyStyle $io, string $token, bool $showToken): void
    {
        if ($showToken) {
            $io->comment(\sprintf('Token: %s', $token));
            $expiresAt = $this->tokenManager->getExpiresAt();
            if (null !== $expiresAt) {
                $io->comment(\sprintf('过期时间: %s', $expiresAt->format('Y-m-d H:i:s')));
            }
        } else {
            $io->comment('使用 --show-token 选项查看Token详情');
        }
    }

    private function handleTokenError(SymfonyStyle $io, \Exception $e, bool $fix): bool
    {
        $io->error(\sprintf('认证检查失败: %s', $e->getMessage()));

        if (!$fix) {
            return true;
        }

        return $this->attemptTokenRefresh($io);
    }

    private function displayTokenProviderInfo(SymfonyStyle $io): void
    {
        $providerClass = $this->config['token_provider'] ?? 'file_cache';
        assert(\is_scalar($providerClass), 'Provider class must be scalar');
        $io->comment(\sprintf('Token提供者: %s', (string) $providerClass));
    }

    private function attemptTokenRefresh(SymfonyStyle $io): bool
    {
        $io->comment('尝试刷新Token...');
        try {
            $this->tokenManager->refresh();
            $io->success('Token刷新成功');

            return false;
        } catch (\Exception $ex) {
            $io->error(\sprintf('Token刷新失败: %s', $ex->getMessage()));

            return true;
        }
    }
}
