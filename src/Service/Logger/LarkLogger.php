<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 飞书应用日志装饰器.
 *
 * 为日志添加飞书特定的上下文信息
 */
#[Autoconfigure(public: true)]
class LarkLogger implements LoggerInterface
{
    /**
     * 日志前缀.
     */
    private const LOG_PREFIX = '[LarkAppBot]';

    /**
     * 敏感字段列表.
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'secret',
        'token',
        'authorization',
        'app_secret',
        'access_token',
        'refresh_token',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly string $appId = '',
        private readonly bool $debug = false,
    ) {
    }

    public function emergency(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(mixed $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(mixed $message, array $context = []): void
    {
        if ($this->debug) {
            $this->log(LogLevel::DEBUG, $message, $context);
        }
    }

    public function log(mixed $level, mixed $message, array $context = []): void
    {
        // 添加前缀
        $message = self::LOG_PREFIX . ' ' . (\is_scalar($message) ? (string) $message : (string) json_encode($message));

        // 添加飞书上下文
        $context = $this->enrichContext($context);

        // 过滤敏感信息
        $context = $this->filterSensitiveData($context);

        $this->logger->log($level, $message, $context);
    }

    /**
     * 记录API请求.
     *
     * @param array<string, mixed> $headers
     * @param mixed|null           $body
     */
    public function logApiRequest(string $method, string $url, array $headers = [], $body = null): void
    {
        $context = [
            'type' => 'api_request',
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
        ];

        if (null !== $body) {
            $context['body'] = \is_string($body) ? $body : json_encode($body);
        }

        $this->info(\sprintf('API请求: %s %s', $method, $url), $context);
    }

    /**
     * 记录API响应.
     *
     * @param array<string, mixed> $headers
     * @param mixed|null           $body
     */
    public function logApiResponse(int $statusCode, array $headers = [], $body = null, float $duration = 0): void
    {
        $context = [
            'type' => 'api_response',
            'status_code' => $statusCode,
            'headers' => $headers,
            'duration_ms' => round($duration * 1000, 2),
        ];

        if (null !== $body) {
            $context['body'] = \is_string($body) ? $body : json_encode($body);
        }

        $level = $statusCode >= 400 ? LogLevel::ERROR : LogLevel::INFO;
        $this->log($level, \sprintf('API响应: HTTP %d (%.2fms)', $statusCode, $duration * 1000), $context);
    }

    /**
     * 记录性能指标.
     *
     * @param array<string, mixed> $metrics
     */
    public function logPerformance(string $operation, float $duration, array $metrics = []): void
    {
        $context = array_merge([
            'type' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
        ], $metrics);

        $this->info(\sprintf('性能: %s 耗时 %.2fms', $operation, $duration * 1000), $context);
    }

    /**
     * 丰富日志上下文.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function enrichContext(array $context): array
    {
        // 添加应用ID
        if ('' !== $this->appId) {
            $context['lark_app_id'] = $this->appId;
        }

        // 添加时间戳
        $context['timestamp'] = date('Y-m-d H:i:s');
        $context['microtime'] = microtime(true);

        $request = $this->requestStack->getCurrentRequest();

        // 添加请求ID（如果存在）
        if (null !== $request && $request->headers->has('X-Request-ID')) {
            $context['request_id'] = $request->headers->get('X-Request-ID');
        }

        // 添加飞书请求ID（如果存在）
        if (null !== $request && $request->headers->has('X-Lark-Request-ID')) {
            $context['lark_request_id'] = $request->headers->get('X-Lark-Request-ID');
        }

        return $context;
    }

    /**
     * 过滤敏感数据.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterSensitiveData(array $data): array
    {
        $filteredData = [];

        foreach ($data as $key => $value) {
            assert(\is_string($key), 'Array key must be string');

            $filteredValue = $this->processValueForSensitiveData($key, $value);
            $filteredData[$key] = $filteredValue;
        }

        return $filteredData;
    }

    /**
     * 处理单个值的敏感数据过滤.
     *
     * @param string $key   键名
     * @param mixed  $value 值
     *
     * @return mixed 处理后的值
     */
    private function processValueForSensitiveData(string $key, mixed $value): mixed
    {
        // 检查是否为敏感字段并掩码
        if ($this->isSensitiveField($key)) {
            return $this->maskValue($value);
        }

        // 递归处理数组
        return $this->filterArrayRecursively($value);
    }

    /**
     * 检查字段是否为敏感字段.
     *
     * @param string $key 字段名
     *
     * @return bool 是否为敏感字段
     */
    private function isSensitiveField(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
            if (str_contains($lowerKey, $sensitiveField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 递归过滤数组中的敏感数据.
     *
     * @param mixed $value 值
     *
     * @return mixed 过滤后的值
     */
    private function filterArrayRecursively(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        // 检查是否为索引数组
        if (array_keys($value) === range(0, count($value) - 1)) {
            return array_map(
                fn ($item) => is_array($item) ? $this->filterSensitiveData($item) : $item,
                $value
            );
        }

        // 关联数组，直接过滤
        return $this->filterSensitiveData($value);
    }

    /**
     * 掩码敏感值.
     */
    private function maskValue(mixed $value): string
    {
        if (!\is_string($value)) {
            return '***';
        }

        $length = \strlen($value);
        if ($length <= 4) {
            return '***';
        }

        if ($length <= 8) {
            return substr($value, 0, 2) . str_repeat('*', $length - 2);
        }

        // 保留前4个和后4个字符
        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }
}
