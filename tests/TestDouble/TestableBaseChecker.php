<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\BaseChecker;

/**
 * 用于测试的 BaseChecker 可测试实现.
 */
class TestableBaseChecker extends BaseChecker
{
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Test Checker';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function testMaskSecret(mixed $secret): string
    {
        return $this->maskSecret($secret);
    }
}
