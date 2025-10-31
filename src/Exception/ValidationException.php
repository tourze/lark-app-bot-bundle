<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 参数验证异常.
 *
 * 当请求参数验证失败时抛出
 */
final class ValidationException extends ApiException
{
    /**
     * 验证错误详情.
     *
     * @var array<string, mixed>
     */
    private array $validationErrors = [];

    /**
     * 获取验证错误详情.
     *
     * @return array<string, mixed>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * 设置验证错误详情.
     *
     * @param array<string, mixed> $validationErrors
     */
    public function setValidationErrors(array $validationErrors): void
    {
        $this->validationErrors = $validationErrors;
    }

    /**
     * 添加单个验证错误.
     */
    public function addValidationError(string $field, mixed $error): self
    {
        $this->validationErrors[$field] = $error;

        return $this;
    }

    /**
     * 创建带验证错误的异常.
     *
     * @param array<string, mixed> $validationErrors
     */
    public static function withErrors(string $message, array $validationErrors, int $code = 0, ?\Throwable $previous = null): self
    {
        $exception = new self($message, $code, $previous);
        $exception->setValidationErrors($validationErrors);

        return $exception;
    }

    /**
     * 获取格式化的错误信息.
     */
    public function getFormattedErrors(): string
    {
        if ([] === $this->validationErrors) {
            return $this->getMessage();
        }

        $errors = [];
        foreach ($this->validationErrors as $field => $error) {
            if (\is_array($error)) {
                $errors[] = \sprintf('%s: %s', $field, implode(', ', $error));
            } elseif (\is_scalar($error)) {
                $errors[] = \sprintf('%s: %s', $field, (string) $error);
            } else {
                $errors[] = \sprintf('%s: %s', $field, \gettype($error));
            }
        }

        return \sprintf('%s (%s)', $this->getMessage(), implode('; ', $errors));
    }

    /**
     * 检查特定字段是否有错误.
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->validationErrors[$field]);
    }

    /**
     * 获取特定字段的错误.
     */
    public function getFieldError(string $field): ?string
    {
        $error = $this->validationErrors[$field] ?? null;

        return \is_string($error) ? $error : null;
    }
}
