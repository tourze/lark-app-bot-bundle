<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Client;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\CircuitBreakerOpenException;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;

/**
 * 飞书API客户端.
 *
 * 封装HTTP请求，处理认证、签名、重试等逻辑
 */
#[Autoconfigure(public: true)]
class LarkClient implements LarkClientInterface
{
    use DecoratorTrait;

    private const BASE_URL = 'https://open.feishu.cn';
    private const RETRY_MAX_ATTEMPTS = 3;

    private HttpClientInterface $httpClient;

    private ResponseHandler $responseHandler;

    private string $appId;

    private string $appSecret;

    private ?CircuitBreaker $circuitBreaker = null;

    public function __construct(
        private readonly TokenProviderInterface $tokenProvider,
        private readonly LoggerInterface $logger,
        ?HttpClientInterface $httpClient = null,
        private bool $debug = false,
        ?CacheItemPoolInterface $cache = null,
        private readonly ?PerformanceMonitor $performanceMonitor = null,
    ) {
        $appId = $_ENV['LARK_APP_ID'] ?? '';
        $appSecret = $_ENV['LARK_APP_SECRET'] ?? '';
        \assert(\is_string($appId));
        \assert(\is_string($appSecret));
        $this->appId = $appId;
        $this->appSecret = $appSecret;

        // 初始化HTTP客户端，添加重试机制
        $baseClient = $httpClient ?? HttpClient::create([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'max_redirects' => 0,
        ]);

        $this->httpClient = new RetryableHttpClient(
            $baseClient,
            null,
            self::RETRY_MAX_ATTEMPTS,
            $this->logger
        );

        // 设置 DecoratorTrait 期望的 client 属性
        $this->client = $this->httpClient;

        $this->responseHandler = new ResponseHandler($this->logger);

        // 初始化熔断器（如果提供了缓存）
        if (null !== $cache) {
            $this->circuitBreaker = new CircuitBreaker(
                'lark_api',
                $cache,
                $this->logger
            );
        }
    }

    /**
     * 发送HTTP请求.
     *
     * @param string               $method  HTTP方法
     * @param string               $url     URL路径
     * @param array<string, mixed> $options 请求选项
     *
     * @throws TransportExceptionInterface
     * @throws ApiException
     *
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $url = $this->normalizeUrl($url);
        $monitoringSessionId = $this->startPerformanceMonitoring($method, $url);

        try {
            // 在内部确保类型安全
            /** @var array<string, mixed> $options */
            $options = $this->prepareRequestOptions($method, $url, $options);
            $this->logRequestIfDebug($method, $url, $options);

            $response = $this->executeRequest($method, $url, $options);
            $this->endPerformanceMonitoring($monitoringSessionId, $response->getStatusCode());

            return $response;
        } catch (\Exception $e) {
            $this->endPerformanceMonitoring($monitoringSessionId, 0);
            throw $e;
        }
    }

    /**
     * 获取基础URL.
     */
    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * 获取App ID.
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * 是否开启调试模式.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * 设置调试模式.
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    private function normalizeUrl(string $url): string
    {
        return str_starts_with($url, '/') ? $url : '/' . $url;
    }

    private function startPerformanceMonitoring(string $method, string $url): ?string
    {
        return $this->performanceMonitor?->startApiRequest($method, $url);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function prepareRequestOptions(string $method, string $url, array $options): array
    {
        $options = $this->addAuthenticationHeaders($options);

        return $this->addSignature($method, $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function logRequestIfDebug(string $method, string $url, array $options): void
    {
        if ($this->debug) {
            $this->logRequest($method, $url, $options);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeRequest(string $method, string $url, array $options): ResponseInterface
    {
        if (null !== $this->circuitBreaker) {
            return $this->executeWithCircuitBreaker($method, $url, $options);
        }

        return $this->doRequest($method, $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeWithCircuitBreaker(string $method, string $url, array $options): ResponseInterface
    {
        if (null === $this->circuitBreaker) {
            throw new ConfigurationException('熔断器未初始化');
        }

        try {
            return $this->circuitBreaker->execute(function () use ($method, $url, $options) {
                return $this->doRequest($method, $url, $options);
            });
        } catch (CircuitBreakerOpenException $e) {
            $this->logger->error('熔断器打开，拒绝请求', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new GenericApiException('服务暂时不可用，请稍后重试', 503, $e);
        }
    }

    private function endPerformanceMonitoring(?string $sessionId, int $statusCode): void
    {
        if (null !== $sessionId && null !== $this->performanceMonitor) {
            $this->performanceMonitor->endApiRequest($sessionId, $statusCode);
        }
    }

    /**
     * 执行实际的HTTP请求.
     *
     * @param array<string, mixed> $options
     *
     * @throws ApiException
     */
    private function doRequest(string $method, string $url, array $options): ResponseInterface
    {
        $startTime = microtime(true);
        $this->logger->info('Lark API request', [
            'method' => $method,
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->request($method, $url, $options);

            $duration = microtime(true) - $startTime;
            $this->logger->info('Lark API response', [
                'method' => $method,
                'url' => $url,
                'status' => $response->getStatusCode(),
                'duration' => round($duration, 4),
            ]);

            // 处理响应
            $response = $this->responseHandler->handle($response);

            // 记录响应日志
            if ($this->debug) {
                $this->logResponse($response);
            }

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('飞书API请求失败', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new GenericApiException(\sprintf('飞书API请求失败: %s', $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * 添加认证头.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function addAuthenticationHeaders(array $options): array
    {
        try {
            $token = $this->tokenProvider->getToken();
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            \assert(\is_array($options['headers']));
            $options['headers']['Authorization'] = 'Bearer ' . $token;
        } catch (AuthenticationException $e) {
            $this->logger->error('获取访问令牌失败', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // 添加通用请求头
        if (!isset($options['headers'])) {
            $options['headers'] = [];
        }
        \assert(\is_array($options['headers']));
        $options['headers']['Content-Type'] ??= 'application/json; charset=utf-8';
        $options['headers']['User-Agent'] ??= 'LarkAppBot/1.0';

        return $options;
    }

    /**
     * 添加请求签名.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function addSignature(string $method, string $url, array $options): array
    {
        // 某些接口需要签名验证
        if ($this->requiresSignature($url)) {
            $timestamp = (string) time();
            $nonce = bin2hex(random_bytes(16));

            // 构建签名字符串
            $signStr = $this->buildSignatureString($method, $url, $timestamp, $nonce, $options);

            // 计算签名
            $signature = hash_hmac('sha256', $signStr, $this->appSecret);

            // 添加签名相关的头部
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            \assert(\is_array($options['headers']));
            $options['headers']['X-Lark-Request-Timestamp'] = $timestamp;
            $options['headers']['X-Lark-Request-Nonce'] = $nonce;
            $options['headers']['X-Lark-Signature'] = $signature;
        }

        return $options;
    }

    /**
     * 判断接口是否需要签名.
     */
    private function requiresSignature(string $url): bool
    {
        // 某些敏感接口需要签名
        $signatureRequiredUrls = [
            '/open-apis/auth/v3/app_access_token',
            '/open-apis/auth/v3/tenant_access_token',
        ];

        foreach ($signatureRequiredUrls as $requiredUrl) {
            if (str_starts_with($url, $requiredUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 构建签名字符串.
     *
     * @param array<string, mixed> $options
     */
    private function buildSignatureString(
        string $method,
        string $url,
        string $timestamp,
        string $nonce,
        array $options,
    ): string {
        $parts = [
            $timestamp,
            $nonce,
            strtoupper($method),
            $url,
        ];

        // 如果有请求体，添加到签名
        if (isset($options['json'])) {
            $parts[] = json_encode($options['json'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } elseif (isset($options['body'])) {
            $parts[] = $options['body'];
        }

        return implode("\n", $parts);
    }

    /**
     * 记录请求日志.
     *
     * @param array<string, mixed> $options
     */
    private function logRequest(string $method, string $url, array $options): void
    {
        $context = [
            'method' => $method,
            'url' => $url,
            'headers' => $options['headers'] ?? [],
        ];

        if (isset($options['json'])) {
            $context['body'] = $options['json'];
        } elseif (isset($options['body'])) {
            $context['body'] = $options['body'];
        }

        // 隐藏敏感信息
        if (\is_array($context['headers']) && isset($context['headers']['Authorization'])) {
            $context['headers']['Authorization'] = 'Bearer ***';
        }

        $this->logger->debug('飞书API请求', $context);
    }

    /**
     * 记录响应日志.
     */
    private function logResponse(ResponseInterface $response): void
    {
        try {
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
            $content = $response->getContent(false);

            $this->logger->debug('飞书API响应', [
                'status_code' => $statusCode,
                'headers' => $headers,
                'body' => json_decode($content, true) ?? $content,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('记录响应日志失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
