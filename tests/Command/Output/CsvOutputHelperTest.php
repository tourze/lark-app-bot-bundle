<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Output\CsvOutputHelper;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CsvOutputHelper::class)]
#[RunTestsInSeparateProcesses]
final class CsvOutputHelperTest extends AbstractIntegrationTestCase
{
    private CsvOutputHelper $helper;

    private SymfonyStyle&MockObject $mockIo;

    public function testOutputCsvBasic(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30, 'city' => 'Beijing'],
            ['name' => 'Bob', 'age' => 25, 'city' => 'Shanghai'],
        ];
        $headers = ['name', 'age', 'city'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(3))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertSame('name,age,city', $line);
                } elseif (2 === $callCount) {
                    $this->assertSame('Alice,30,Beijing', $line);
                } elseif (3 === $callCount) {
                    $this->assertSame('Bob,25,Shanghai', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithCommasInValues(): void
    {
        $data = [
            ['name' => 'Wang, Li', 'email' => 'wang@example.com'],
        ];
        $headers = ['name', 'email'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    // Value with comma should be quoted
                    $this->assertSame('"Wang, Li",wang@example.com', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithDoubleQuotes(): void
    {
        $data = [
            ['name' => 'Zhang "Nick" San', 'email' => 'zhang@example.com'],
        ];
        $headers = ['name', 'email'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    // Double quotes should be escaped and value quoted
                    $this->assertSame('"Zhang ""Nick"" San",zhang@example.com', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithMissingFields(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob'],
        ];
        $headers = ['name', 'age', 'city'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(3))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    $this->assertSame('Alice,30,', $line);
                } elseif (3 === $callCount) {
                    $this->assertSame('Bob,,', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithNonScalarValues(): void
    {
        $data = [
            ['name' => 'Alice', 'tags' => ['admin', 'user']],
        ];
        $headers = ['name', 'tags'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    // Non-scalar values should be converted to empty string
                    $this->assertSame('Alice,', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithNumericValues(): void
    {
        $data = [
            ['id' => 123, 'score' => 98.5, 'active' => true],
        ];
        $headers = ['id', 'score', 'active'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    $this->assertSame('123,98.5,1', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithEmptyData(): void
    {
        $data = [];
        $headers = ['name', 'email'];

        $this->mockIo->expects($this->once())
            ->method('writeln')
            ->with('name,email')
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvWithEmptyHeaders(): void
    {
        $data = [
            ['name' => 'Alice'],
        ];
        $headers = [];

        // When headers are empty, it will write empty header row, then try to write data rows
        // Since headers are empty, data rows will also be empty
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->with('')
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    public function testOutputCsvEscapesMultipleSpecialCharacters(): void
    {
        $data = [
            ['description' => 'Product "Premium", high quality'],
        ];
        $headers = ['description'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount) {
                ++$callCount;
                if (2 === $callCount) {
                    // Both comma and quotes should be handled
                    $this->assertSame('"Product ""Premium"", high quality"', $line);
                }
            })
        ;

        $this->helper->outputCsv($this->mockIo, $data, $headers);
    }

    protected function onSetUp(): void
    {
        /** @var CsvOutputHelper $helper */
        $helper = self::getService(CsvOutputHelper::class);
        $this->helper = $helper;
        $this->mockIo = $this->createMock(SymfonyStyle::class);
    }
}
