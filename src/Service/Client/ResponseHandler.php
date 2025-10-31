<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Client;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 飞书API响应处理器.
 *
 * 处理API响应，解析错误码，抛出相应异常
 */
#[Autoconfigure(public: true)]
class ResponseHandler
{
    /**
     * 飞书API错误码映射.
     */
    private const ERROR_CODE_MAP = [
        // 认证相关错误
        99991663 => ['class' => AuthenticationException::class, 'message' => 'token无效'],
        99991664 => ['class' => AuthenticationException::class, 'message' => 'token未找到'],
        99991665 => ['class' => AuthenticationException::class, 'message' => 'token过期'],
        99991667 => ['class' => AuthenticationException::class, 'message' => '用户未授权'],
        99991668 => ['class' => AuthenticationException::class, 'message' => '权限不足'],

        // 请求限制
        99991400 => ['class' => ValidationException::class, 'message' => '请求参数非法'],
        99991401 => ['class' => ValidationException::class, 'message' => '请求路径不存在'],
        99991429 => ['class' => RateLimitException::class, 'message' => '请求频率超限'],

        // 服务器错误
        99991500 => ['class' => GenericApiException::class, 'message' => '服务器内部错误'],
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 处理API响应.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws ValidationException
     */
    public function handle(ResponseInterface $response): ResponseInterface
    {
        try {
            // 获取响应状态码
            $statusCode = $response->getStatusCode();

            // 2xx响应直接返回
            if ($statusCode >= 200 && $statusCode < 300) {
                return $this->validateSuccessResponse($response);
            }

            // 处理错误响应
            $this->handleErrorResponse($response, $statusCode);
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $this->handleHttpException($e, $response);
        }

        return $response;
    }

    /**
     * 从响应中提取数据.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function extractData(ResponseInterface $response): array
    {
        try {
            $content = $response->getContent();
            $data = json_decode($content, true);

            if (!\is_array($data)) {
                throw new GenericApiException('响应格式错误：期望JSON格式');
            }

            // 确保键是字符串类型
            /** @var array<string, mixed> $validatedData */
            $validatedData = $data;

            // 检查是否有错误
            if (isset($validatedData['code']) && 0 !== $validatedData['code']) {
                $code = $validatedData['code'];
                $message = $validatedData['msg'] ?? '未知错误';
                // 确保消息是字符串
                if (\is_array($message)) {
                    $message = json_encode($message);
                }
                \assert(\is_int($code) && \is_string($message));
                $this->handleApiError($code, $message, $validatedData);
            }

            $data = $validatedData['data'] ?? $validatedData;
            \assert(\is_array($data));

            // 确保返回的数组键是字符串类型
            /** @var array<string, mixed> $typedData */
            $typedData = $data;

            return $typedData;
        } catch (\JsonException $e) {
            throw new GenericApiException(\sprintf('JSON解析失败: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * 验证成功响应.
     *
     * 即使HTTP状态码是200，飞书API也可能返回业务错误
     */
    private function validateSuccessResponse(ResponseInterface $response): ResponseInterface
    {
        try {
            $content = $response->getContent();
            $data = json_decode($content, true);

            if (!\is_array($data)) {
                return $response;
            }

            // 确保键是字符串类型
            /** @var array<string, mixed> $validatedData */
            $validatedData = $data;

            // 检查飞书API响应中的code字段
            if (isset($validatedData['code']) && 0 !== $validatedData['code']) {
                $code = $validatedData['code'];
                $message = $validatedData['msg'] ?? '未知错误';
                // 确保消息是字符串
                if (\is_array($message)) {
                    $message = json_encode($message);
                }
                \assert(\is_int($code) && \is_string($message));
                $this->handleApiError($code, $message, $validatedData);
            }
        } catch (\JsonException $e) {
            // 非JSON响应，可能是文件下载等，直接返回
            $this->logger->debug('响应不是JSON格式', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    /**
     * 处理错误响应.
     *
     * @throws ApiException
     */
    private function handleErrorResponse(ResponseInterface $response, int $statusCode): void
    {
        try {
            $content = $response->getContent(false);
            $data = json_decode($content, true);

            if (!\is_array($data)) {
                throw new GenericApiException(\sprintf('HTTP %d: %s', $statusCode, $this->getHttpStatusMessage($statusCode)), $statusCode);
            }

            // 确保键是字符串类型
            /** @var array<string, mixed> $validatedData */
            $validatedData = $data;

            $code = $validatedData['code'] ?? 0;
            $message = $validatedData['msg'] ?? $this->getHttpStatusMessage($statusCode);

            // 确保消息是字符串
            if (\is_array($message)) {
                $message = json_encode($message);
            }

            \assert(\is_int($code) && \is_string($message));
            $this->handleApiError($code, $message, $validatedData);
        } catch (\Exception $e) {
            // 无法解析响应内容
            throw new GenericApiException(\sprintf('HTTP %d: %s', $statusCode, $this->getHttpStatusMessage($statusCode)), $statusCode);
        }
    }

    /**
     * 处理HTTP异常.
     *
     * @throws ApiException
     */
    private function handleHttpException(\Throwable $e, ResponseInterface $response): void
    {
        $statusCode = 0;
        $headers = [];

        try {
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders();
        } catch (\Exception $headerException) {
            // 忽略获取头部的错误
        }

        $this->logger->error('HTTP请求异常', [
            'status_code' => $statusCode,
            'headers' => $headers,
            'error' => $e->getMessage(),
        ]);

        throw new GenericApiException(\sprintf('HTTP请求失败: %s', $e->getMessage()), $statusCode, $e);
    }

    /**
     * 处理API错误.
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws ValidationException
     */
    private function handleApiError(int $code, string $message, array $data = []): void
    {
        // 记录错误日志
        $this->logger->error('飞书API错误', [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);

        // 根据错误码映射抛出相应异常
        if (isset(self::ERROR_CODE_MAP[$code])) {
            $errorInfo = self::ERROR_CODE_MAP[$code];
            $exceptionClass = $errorInfo['class'];
            $defaultMessage = $errorInfo['message'];

            $formattedMessage = \sprintf('[%d] %s: %s', $code, $defaultMessage, $message);
            throw new $exceptionClass($formattedMessage, $code);
        }

        // 默认异常
        throw new GenericApiException(\sprintf('[%d] %s', $code, $message), $code);
    }

    /**
     * 获取HTTP状态码对应的消息.
     */
    private function getHttpStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => '请求参数错误',
            401 => '未授权',
            403 => '禁止访问',
            404 => '资源不存在',
            429 => '请求过于频繁',
            500 => '服务器内部错误',
            502 => '网关错误',
            503 => '服务暂时不可用',
            504 => '网关超时',
            default => '未知错误',
        };
    }
}
