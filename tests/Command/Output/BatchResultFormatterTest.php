<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Output\BatchResultFormatter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BatchResultFormatter::class)]
#[RunTestsInSeparateProcesses]
final class BatchResultFormatterTest extends AbstractIntegrationTestCase
{
    private BatchResultFormatter $formatter;

    private SymfonyStyle&MockObject $mockIo;

    /** @var array<int, array<string, mixed>> */
    private array $sampleResults;

    public function testOutputBatchResultsAsTable(): void
    {
        $this->mockIo->expects($this->once())
            ->method('title')
            ->with('批量查询结果')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                ['Open ID', '姓名', '邮箱', '手机', '状态'],
                self::callback(function ($rows): bool {
                    if (!\is_array($rows) || 2 !== \count($rows)) {
                        return false;
                    }
                    $firstRow = $rows[0] ?? null;
                    if (!\is_array($firstRow)) {
                        return false;
                    }

                    return ($firstRow[0] ?? null) === 'ou_123'
                        && ($firstRow[1] ?? null) === 'Zhang San'
                        && ($firstRow[4] ?? null) === '已激活';
                })
            )
        ;

        $this->formatter->outputBatchResults($this->mockIo, $this->sampleResults, 'table', []);
    }

    public function testOutputBatchResultsAsJson(): void
    {
        $this->mockIo->expects($this->once())
            ->method('writeln')
            ->with(self::callback(function ($output) {
                if (!\is_string($output)) {
                    return false;
                }
                $decoded = json_decode($output, true);

                return \is_array($decoded) && 2 === \count($decoded);
            }))
        ;

        $this->formatter->outputBatchResults($this->mockIo, $this->sampleResults, 'json', []);
    }

    public function testOutputBatchResultsAsJsonWithFields(): void
    {
        $fields = ['open_id', 'name'];

        $this->mockIo->expects($this->once())
            ->method('writeln')
            ->with(self::callback(function ($output): bool {
                if (!\is_string($output)) {
                    return false;
                }
                $decoded = json_decode($output, true);
                if (!\is_array($decoded)) {
                    return false;
                }
                $firstItem = $decoded[0] ?? null;
                if (!\is_array($firstItem)) {
                    return false;
                }

                return isset($firstItem['open_id'], $firstItem['name'])
                    && !isset($firstItem['email']);
            }))
        ;

        $this->formatter->outputBatchResults($this->mockIo, $this->sampleResults, 'json', $fields);
    }

    public function testOutputBatchResultsAsCsv(): void
    {
        $this->mockIo->expects($this->exactly(3))
            ->method('writeln')
            ->with(self::callback(function ($line) {
                return \is_string($line);
            }))
        ;

        $this->formatter->outputBatchResults($this->mockIo, $this->sampleResults, 'csv', []);
    }

    public function testOutputBatchResultsAsCsvWithFields(): void
    {
        $fields = ['open_id', 'name', 'email'];

        $callCount = 0;
        $this->mockIo->expects($this->exactly(3))
            ->method('writeln')
            ->willReturnCallback(function ($line) use (&$callCount): void {
                ++$callCount;
                if (1 === $callCount) {
                    // Header row
                    $this->assertSame('open_id,name,email', $line);
                } elseif (2 === $callCount) {
                    // First data row
                    $this->assertIsString($line);
                    $this->assertStringContainsString('ou_123', $line);
                    $this->assertStringContainsString('Zhang San', $line);
                }
            })
        ;

        $this->formatter->outputBatchResults($this->mockIo, $this->sampleResults, 'csv', $fields);
    }

    public function testOutputBatchResultsEscapesCsvValues(): void
    {
        $resultsWithComma = [
            [
                'open_id' => 'ou_789',
                'name' => 'Li, Ming',
                'email' => 'test@example.com',
                'mobile' => '13800138000',
                'status' => 1,
            ],
        ];

        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) {
                if (\is_string($line) && str_contains($line, 'Li')) {
                    // Should be quoted because of comma
                    $this->assertStringContainsString('"Li, Ming"', $line);
                }
            })
        ;

        $this->formatter->outputBatchResults($this->mockIo, $resultsWithComma, 'csv', []);
    }

    public function testOutputBatchResultsHandlesDoubleQuotes(): void
    {
        $resultsWithQuotes = [
            [
                'open_id' => 'ou_999',
                'name' => 'Wang "Nick" Fang',
                'email' => 'test@example.com',
                'mobile' => '13800138000',
                'status' => 1,
            ],
        ];

        $this->mockIo->expects($this->exactly(2))
            ->method('writeln')
            ->willReturnCallback(function ($line) {
                if (\is_string($line) && str_contains($line, 'Wang')) {
                    // Double quotes should be escaped
                    $this->assertStringContainsString('""Nick""', $line);
                }
            })
        ;

        $this->formatter->outputBatchResults($this->mockIo, $resultsWithQuotes, 'csv', []);
    }

    public function testOutputBatchResultsFormatsStatus(): void
    {
        $resultsWithVariousStatuses = [
            ['open_id' => 'ou_1', 'name' => 'User 1', 'email' => 'u1@test.com', 'mobile' => '111', 'status' => 1],
            ['open_id' => 'ou_2', 'name' => 'User 2', 'email' => 'u2@test.com', 'mobile' => '222', 'status' => 2],
            ['open_id' => 'ou_3', 'name' => 'User 3', 'email' => 'u3@test.com', 'mobile' => '333', 'status' => 4],
            ['open_id' => 'ou_4', 'name' => 'User 4', 'email' => 'u4@test.com', 'mobile' => '444', 'status' => 5],
            ['open_id' => 'ou_5', 'name' => 'User 5', 'email' => 'u5@test.com', 'mobile' => '555', 'status' => 99],
        ];

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                self::anything(),
                self::callback(function ($rows): bool {
                    if (!\is_array($rows) || 5 !== \count($rows)) {
                        return false;
                    }
                    $row0 = $rows[0] ?? null;
                    $row1 = $rows[1] ?? null;
                    $row2 = $rows[2] ?? null;
                    $row3 = $rows[3] ?? null;
                    $row4 = $rows[4] ?? null;
                    if (!\is_array($row0) || !\is_array($row1) || !\is_array($row2) || !\is_array($row3) || !\is_array($row4)) {
                        return false;
                    }
                    $status4 = $row4[4] ?? null;

                    return ($row0[4] ?? null) === '已激活'
                        && ($row1[4] ?? null) === '已停用'
                        && ($row2[4] ?? null) === '未激活'
                        && ($row3[4] ?? null) === '已退出'
                        && \is_string($status4) && str_contains($status4, '未知');
                })
            )
        ;

        $this->formatter->outputBatchResults($this->mockIo, $resultsWithVariousStatuses, 'table', []);
    }

    public function testOutputBatchResultsHandlesEmptyResults(): void
    {
        $this->mockIo->expects($this->once())
            ->method('title')
            ->with('批量查询结果')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                ['Open ID', '姓名', '邮箱', '手机', '状态'],
                []
            )
        ;

        $this->formatter->outputBatchResults($this->mockIo, [], 'table', []);
    }

    protected function onSetUp(): void
    {
        $this->formatter = self::getService(BatchResultFormatter::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);

        $this->sampleResults = [
            [
                'open_id' => 'ou_123',
                'name' => 'Zhang San',
                'email' => 'zhangsan@example.com',
                'mobile' => '13800138000',
                'status' => 1,
            ],
            [
                'open_id' => 'ou_456',
                'name' => 'Li Si',
                'email' => 'lisi@example.com',
                'mobile' => '13900139000',
                'status' => 2,
            ],
        ];
    }
}
