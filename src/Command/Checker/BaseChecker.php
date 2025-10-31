<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 基础检查器抽象类.
 */
abstract class BaseChecker
{
    /** @var array<string, mixed> */
    protected readonly array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 执行检查.
     */
    abstract public function check(SymfonyStyle $io, bool $fix = false): bool;

    /**
     * 获取检查器名称.
     */
    abstract public function getName(): string;

    /**
     * 遮罩敏感信息.
     */
    protected function maskSecret(mixed $secret): string
    {
        $secretStr = \is_string($secret) ? $secret : 'invalid';
        $length = \strlen($secretStr);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secretStr, 0, 4) . str_repeat('*', $length - 8) . substr($secretStr, -4);
    }
}
