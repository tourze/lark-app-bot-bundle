<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\BaseChecker;
use Tourze\LarkAppBotBundle\Tests\TestDouble\TestableBaseChecker;
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
        $checker = new TestableBaseChecker($config);

        $this->assertInstanceOf(BaseChecker::class, $checker);
        $this->assertSame($config, $checker->getConfig());
    }

    #[DataProvider('maskSecretDataProvider')]
    public function testMaskSecret(mixed $input, string $expected): void
    {
        $checker = new TestableBaseChecker([]);
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
        $checker = new TestableBaseChecker([]);
        $io = $this->createMock(SymfonyStyle::class);

        $this->assertSame('Test Checker', $checker->getName());
        $this->assertTrue($checker->check($io));
    }

    protected function onSetUp(): void
    {
        // Checker tests do not need service initialization
    }
}
