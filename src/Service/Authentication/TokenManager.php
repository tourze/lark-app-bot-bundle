<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Authentication;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;

/**
 * 飞书应用访问令牌管理器.
 *
 * 负责获取、缓存和刷新飞书应用的访问令牌
 */
#[Autoconfigure(public: true)]
class TokenManager implements TokenProviderInterface
{
    private const TOKEN_URL = 'https://open.feishu.cn/open-apis/auth/v3/app_access_token/internal';
    private const CACHE_KEY = 'lark_app_bot.access_token';
    private const TOKEN_BUFFER_TIME = 300; // 5分钟缓冲时间

    private readonly string $appId;

    private readonly string $appSecret;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        ?LoggerInterface $logger = null,
    ) {
        $appId = $_ENV['LARK_APP_ID'] ?? '';
        $appSecret = $_ENV['LARK_APP_SECRET'] ?? '';
        \assert(\is_string($appId));
        \assert(\is_string($appSecret));
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->logger = $logger ?? new NullLogger();
    }

    public function getToken(): string
    {
        $this->logger->debug('Getting Lark access token');

        $cacheItem = $this->cache->getItem(self::CACHE_KEY);

        if ($cacheItem->isHit()) {
            /** @var array<string, mixed>|null $tokenData */
            $tokenData = $cacheItem->get();
            if ($this->isTokenDataValid($tokenData)) {
                $this->logger->debug('Using cached token');

                \assert(\is_array($tokenData) && isset($tokenData['token']) && \is_string($tokenData['token']));

                return $tokenData['token'];
            }
        }

        $this->logger->info('Token not found or expired, refreshing');

        return $this->refresh();
    }

    public function refresh(): string
    {
        $this->logger->info('Refreshing Lark access token');
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'json' => [
                    'app_id' => $this->appId,
                    'app_secret' => $this->appSecret,
                ],
                'timeout' => 10,
            ]);

            $duration = microtime(true) - $startTime;
            $this->logger->info('Request completed', [
                'method' => 'POST',
                'url' => self::TOKEN_URL,
                'duration' => round($duration, 4),
                'status' => $response->getStatusCode(),
            ]);

            $data = $response->toArray();

            if (0 !== ($data['code'] ?? -1)) {
                $msg = $data['msg'] ?? 'Unknown error';
                // $msg 来自API响应，可能是各种类型，需要安全转换为字符串
                if (\is_array($msg)) {
                    $msg = json_encode($msg);
                }
                \assert(\is_string($msg));
                throw new AuthenticationException(\sprintf('Failed to get access token: %s', $msg));
            }

            $token = $data['app_access_token'] ?? null;
            $expire = $data['expire'] ?? 0;

            \assert(\is_string($token) || null === $token);
            \assert(\is_int($expire));

            if (null === $token || '' === $token) {
                throw new AuthenticationException('Empty access token received');
            }

            // 计算过期时间，减去缓冲时间
            $expiresAt = new \DateTimeImmutable(\sprintf('+%d seconds', $expire - self::TOKEN_BUFFER_TIME));

            // 缓存令牌
            $cacheItem = $this->cache->getItem(self::CACHE_KEY);
            $cacheItem->set([
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);
            $cacheItem->expiresAt($expiresAt);
            $this->cache->save($cacheItem);

            $this->logger->info('Token refreshed successfully', [
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            // Token已经通过上方null检查，这里的断言是冗余的

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh token', [
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof AuthenticationException) {
                throw $e;
            }

            throw new AuthenticationException('Failed to refresh access token', 0, $e);
        }
    }

    public function clear(): void
    {
        $this->logger->info('Clearing cached token');
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    public function isValid(): bool
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);

        if (!$cacheItem->isHit()) {
            return false;
        }

        /** @var array<string, mixed>|null $tokenData */
        $tokenData = $cacheItem->get();

        return $this->isTokenDataValid($tokenData);
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);

        if (!$cacheItem->isHit()) {
            return null;
        }

        /** @var array<string, mixed>|null $tokenData */
        $tokenData = $cacheItem->get();
        if (!\is_array($tokenData)) {
            return null;
        }

        $expiresAt = $tokenData['expires_at'] ?? null;

        return $expiresAt instanceof \DateTimeInterface ? $expiresAt : null;
    }

    /**
     * 检查令牌数据是否有效.
     *
     * @param array<string, mixed>|null $tokenData
     */
    private function isTokenDataValid(?array $tokenData): bool
    {
        if (null === $tokenData || !isset($tokenData['token']) || '' === $tokenData['token']) {
            return false;
        }

        $expiresAt = $tokenData['expires_at'] ?? null;
        if (!$expiresAt instanceof \DateTimeInterface) {
            return false;
        }

        return $expiresAt > new \DateTimeImmutable();
    }
}
