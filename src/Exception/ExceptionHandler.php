<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * 飞书应用异常处理器.
 *
 * 统一处理飞书相关异常，返回标准化的错误响应
 */
#[Autoconfigure(public: true)]
final class ExceptionHandler
{
    /**
     * 错误级别映射.
     */
    private const ERROR_LEVELS = [
        AuthenticationException::class => 'error',
        RateLimitException::class => 'warning',
        ValidationException::class => 'notice',
        ApiException::class => 'error',
        ConfigurationException::class => 'critical',
        LarkException::class => 'error',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * 处理异常事件.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // 只处理飞书相关异常
        if (!$exception instanceof LarkException) {
            return;
        }

        // 记录日志
        $this->logException($exception);

        // 创建响应
        $response = $this->createErrorResponse($exception);

        // 设置响应
        $event->setResponse($response);
    }

    /**
     * 处理异常并返回标准化的数组.
     *
     * @return array<string, mixed>
     */
    public function handle(\Throwable $exception): array
    {
        // 记录日志
        if ($exception instanceof LarkException) {
            $this->logException($exception);
        }

        return [
            'success' => false,
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $this->getErrorCode($exception),
                'type' => $exception instanceof LarkException ? $this->getErrorType($exception) : 'unknown_error',
            ],
        ];
    }

    /**
     * 记录异常日志.
     */
    private function logException(\Throwable $exception): void
    {
        $context = [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // 添加额外的上下文信息
        if ($exception instanceof ApiException) {
            $context['error_code'] = $exception->getErrorCode();
            $context['error_data'] = $exception->getErrorData();
        }

        if ($exception instanceof RateLimitException) {
            $context['retry_after'] = $exception->getRetryAfter();
            $context['rate_limit_info'] = $exception->getRateLimitInfo();
        }

        if ($exception instanceof ValidationException) {
            $context['validation_errors'] = $exception->getValidationErrors();
        }

        // 在调试模式下添加堆栈跟踪
        if ($this->debug) {
            $context['trace'] = $exception->getTraceAsString();
        }

        // 获取日志级别
        $level = $this->getLogLevel($exception);

        // 记录日志
        $this->logger->log($level, '飞书应用异常: ' . $exception->getMessage(), $context);
    }

    /**
     * 获取异常对应的日志级别.
     */
    private function getLogLevel(\Throwable $exception): string
    {
        foreach (self::ERROR_LEVELS as $exceptionClass => $level) {
            if ($exception instanceof $exceptionClass) {
                return $level;
            }
        }

        return 'error';
    }

    /**
     * 创建错误响应.
     */
    private function createErrorResponse(\Throwable $exception): JsonResponse
    {
        $data = [
            'error' => true,
            'message' => $exception->getMessage(),
            'code' => $this->getErrorCode($exception),
            'type' => $this->getErrorType($exception),
        ];

        // 添加额外信息
        if ($exception instanceof ApiException && [] !== $exception->getErrorData()) {
            $data['details'] = $exception->getErrorData();
        }

        if ($exception instanceof RateLimitException && $exception->canRetry()) {
            $data['retry_after'] = $exception->getRetryAfter();
            $data['retry_timestamp'] = $exception->getRetryTimestamp();
        }

        if ($exception instanceof ValidationException && [] !== $exception->getValidationErrors()) {
            $data['validation_errors'] = $exception->getValidationErrors();
        }

        // 在调试模式下添加调试信息
        if ($this->debug) {
            $data['debug'] = [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        // 确定HTTP状态码
        $statusCode = $this->getHttpStatusCode($exception);

        return new JsonResponse($data, $statusCode);
    }

    /**
     * 获取错误码.
     */
    private function getErrorCode(\Throwable $exception): string
    {
        if ($exception instanceof ApiException && null !== $exception->getErrorCode()) {
            return (string) $exception->getErrorCode();
        }

        // 使用异常类名作为错误码
        $className = (new \ReflectionClass($exception))->getShortName();

        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('Exception', '', $className)) ?? '');
    }

    /**
     * 获取错误类型.
     */
    private function getErrorType(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AuthenticationException => 'authentication_error',
            $exception instanceof RateLimitException => 'rate_limit_error',
            $exception instanceof ValidationException => 'validation_error',
            $exception instanceof ConfigurationException => 'configuration_error',
            $exception instanceof ApiException => 'api_error',
            default => 'lark_error',
        };
    }

    /**
     * 获取HTTP状态码.
     */
    private function getHttpStatusCode(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
            $exception instanceof RateLimitException => Response::HTTP_TOO_MANY_REQUESTS,
            $exception instanceof ValidationException => Response::HTTP_BAD_REQUEST,
            $exception instanceof ConfigurationException => Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception instanceof ApiException => $this->getApiExceptionStatusCode($exception),
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * 获取API异常的HTTP状态码.
     */
    private function getApiExceptionStatusCode(ApiException $exception): int
    {
        $code = $exception->getCode();

        // 如果异常码已经是HTTP状态码，直接使用
        if ($code >= 400 && $code < 600) {
            return $code;
        }

        // 否则返回500
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
