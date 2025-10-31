<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Authentication;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 基于文件缓存的令牌提供者.
 *
 * 使用本地文件系统缓存访问令牌
 */
class FileCacheTokenProvider implements TokenProviderInterface
{
    private readonly TokenManager $tokenManager;

    public function __construct(
        HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null,
    ) {
        $cacheDirectory = $_ENV['LARK_CACHE_DIR'] ?? sys_get_temp_dir() . '/lark_app_bot';
        \assert(\is_string($cacheDirectory));

        $cache = new FilesystemAdapter(
            namespace: 'lark_app_bot',
            defaultLifetime: 0,
            directory: $cacheDirectory
        );

        $this->tokenManager = new TokenManager($httpClient, $cache, $logger ?? new NullLogger());
    }

    public function getToken(): string
    {
        return $this->tokenManager->getToken();
    }

    public function refresh(): string
    {
        return $this->tokenManager->refresh();
    }

    public function clear(): void
    {
        $this->tokenManager->clear();
    }

    public function isValid(): bool
    {
        return $this->tokenManager->isValid();
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->tokenManager->getExpiresAt();
    }

    public static function getDefaultCacheDirectory(): string
    {
        $cacheDir = $_ENV['LARK_CACHE_DIR'] ?? sys_get_temp_dir() . '/lark_app_bot';
        \assert(\is_string($cacheDir));

        return $cacheDir;
    }
}
