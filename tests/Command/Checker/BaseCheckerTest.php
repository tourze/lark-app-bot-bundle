<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\BaseChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BaseChecker::class)]
#[RunTestsInSeparateProcesses]
final class BaseCheckerTest extends AbstractIntegrationTestCase
{
    public function testConstructor(): void
    {
        $config = ['key' => 'value'];
        $checker = new class($config) extends BaseChecker {
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

            public function testMaskSecret(mixed $secret): string
            {
                return $this->maskSecret($secret);
            }
        };

        $this->assertInstanceOf(BaseChecker::class, $checker);
        // 匿名类继承自BaseChecker，config应该正确设置
    }

    #[DataProvider('maskSecretDataProvider')]
    public function testMaskSecret(mixed $input, string $expected): void
    {
        $checker = new class([]) extends BaseChecker {
            public function check(SymfonyStyle $io, bool $fix = false): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'Test Checker';
            }

            public function testMaskSecret(mixed $secret): string
            {
                return $this->maskSecret($secret);
            }
        };

        $result = $checker->testMaskSecret($input);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function maskSecretDataProvider(): array
    {
        return [
            'empty string' => ['', ''],
            'short string' => ['short', '*****'],
            'medium string' => ['12345678', '********'],
            'long string' => ['1234567890abcdef', '1234********cdef'],
            'very long string' => ['1234567890abcdef1234567890', '1234******************7890'],
            'integer' => [123, '*******'],
            'array' => [['key' => 'value'], '*******'],
            'null' => [null, '*******'],
            'boolean' => [true, '*******'],
        ];
    }

    public function testAbstractMethods(): void
    {
        $checker = new class([]) extends BaseChecker {
            public function check(SymfonyStyle $io, bool $fix = false): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'Test Checker';
            }
        };

        $io = $this->createMock(SymfonyStyle::class);

        $this->assertSame('Test Checker', $checker->getName());
        $this->assertTrue($checker->check($io));
    }

    protected function onSetUp(): void
    {
        // Checker tests do not need service initialization
    }
}
