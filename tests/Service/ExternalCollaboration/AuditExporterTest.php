<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AuditExporter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

#[CoversClass(AuditExporter::class)]
#[RunTestsInSeparateProcesses]
final class AuditExporterTest extends AbstractIntegrationTestCase
{
    public function testExportJson(): void
    {
        $exp = self::getService(AuditExporter::class);
        $json = $exp->export([['a' => 1, 'b' => 'x']], 'json');
        $this->assertJson($json);
    }

    public function testExportCsvEmpty(): void
    {
        $exp = self::getService(AuditExporter::class);
        $csv = $exp->export([], 'csv');
        $this->assertSame('', $csv);
    }

    protected function onSetUp(): void {}
}
