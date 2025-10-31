<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 缓存配置检查器.
 */
class CacheConfigChecker extends BaseChecker
{
    public function getName(): string
    {
        return '缓存配置';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        $cacheConfig = $this->normalizeCacheConfig();
        $this->showCachePoolInfo($io, $cacheConfig);
        $hasError = $this->checkCacheDirectory($io, $fix);
        $this->showCacheTtlInfo($io, $cacheConfig);

        return $hasError;
    }

    /** @return array<string, mixed> */
    private function normalizeCacheConfig(): array
    {
        $cacheConfig = $this->config['cache'] ?? [];

        /** @var array<string, mixed> $cacheConfig */
        return \is_array($cacheConfig) ? $cacheConfig : [];
    }

    /** @param array<string, mixed> $cacheConfig */
    private function showCachePoolInfo(SymfonyStyle $io, array $cacheConfig): void
    {
        $cachePool = $cacheConfig['pool'] ?? 'cache.app';
        $io->comment(\sprintf('缓存池: %s', \is_string($cachePool) ? $cachePool : 'unknown'));
    }

    private function checkCacheDirectory(SymfonyStyle $io, bool $fix): bool
    {
        if (!$this->isUsingFileCache()) {
            return false;
        }

        $cacheDir = $this->getParameter('kernel.cache_dir');

        if ($this->isCacheDirectoryWritable($cacheDir)) {
            $io->success('缓存目录可写');

            return false;
        }

        return $this->handleCacheDirectoryError($io, \is_string($cacheDir) ? $cacheDir : '/tmp', $fix);
    }

    private function handleCacheDirectoryError(SymfonyStyle $io, string $cacheDir, bool $fix): bool
    {
        $io->error(\sprintf('缓存目录不可写: %s', $cacheDir));

        if (!$fix) {
            return true;
        }

        return $this->attemptCacheDirectoryFix($io, $cacheDir);
    }

    /** @param array<string, mixed> $cacheConfig */
    private function showCacheTtlInfo(SymfonyStyle $io, array $cacheConfig): void
    {
        $cacheTtl = $cacheConfig['ttl'] ?? 3600;
        $io->comment(\sprintf('缓存TTL: %d 秒', \is_int($cacheTtl) ? $cacheTtl : 3600));
    }

    private function isUsingFileCache(): bool
    {
        return 'file_cache' === ($this->config['token_provider'] ?? 'file_cache');
    }

    private function isCacheDirectoryWritable(mixed $cacheDir): bool
    {
        return \is_string($cacheDir) && is_writable($cacheDir);
    }

    private function attemptCacheDirectoryFix(SymfonyStyle $io, string $cacheDir): bool
    {
        $io->comment('尝试修复权限...');
        if (@chmod($cacheDir, 0o777)) {
            $io->success('权限修复成功');

            return false;
        }

        $io->error('权限修复失败，请手动执行: chmod -R 777 ' . $cacheDir);

        return true;
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
