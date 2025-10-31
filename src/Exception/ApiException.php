<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 飞书API异常.
 *
 * 当API调用失败时抛出
 */
abstract class ApiException extends LarkException
{
    /**
     * API错误码.
     */
    private ?int $errorCode = null;

    /**
     * API错误详情.
     *
     * @var array<string, mixed>
     */
    private array $errorData = [];

    /**
     * 构造函数.
     *
     * @param string          $message  错误信息
     * @param int             $code     错误码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $code;
    }

    /**
     * 获取API错误码.
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * 设置API错误码.
     */
    public function setErrorCode(?int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    /**
     * 获取错误详情.
     *
     * @return array<string, mixed>
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    /**
     * 设置错误详情.
     *
     * @param array<string, mixed> $errorData
     */
    public function setErrorData(array $errorData): void
    {
        $this->errorData = $errorData;
    }

    /**
     * 创建带详情的异常.
     *
     * @param array<string, mixed> $errorData
     *
     * @phpstan-return static
     */
    public static function withDetails(string $message, int $code, array $errorData, ?\Throwable $previous = null): static
    {
        /** @phpstan-ignore new.static */
        $exception = new static($message, $code, $previous);
        $exception->setErrorData($errorData);

        return $exception;
    }
}
