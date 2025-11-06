<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AuditLogger;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AuditStatistics;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

#[CoversClass(AuditStatistics::class)]
#[RunTestsInSeparateProcesses]
final class AuditStatisticsTest extends AbstractIntegrationTestCase
{
    public function testGetStatistics(): void
    {
        $service = self::getContainer()->get('MockAuditStatistics');
        $this->assertInstanceOf(AuditStatistics::class, $service);
        // 选择足够大的天数窗口，避免固定时间戳样本因时间推移而被过滤
        $stats = $service->get(36500);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_events', $stats);
        $this->assertSame(2, $stats['total_events']);
        $this->assertSame(1, $stats['external_user_events']);
        $this->assertSame(1, $stats['security_violations']);
        $this->assertSame(1, $stats['permission_changes']);
    }

    protected function onSetUp(): void
    {
    }
}
