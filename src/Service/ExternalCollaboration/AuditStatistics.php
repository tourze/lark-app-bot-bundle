<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

/**
 * 审计统计计算器（降低 AuditLogger 复杂度）
 */
final class AuditStatistics
{
    /** @var array<int, array<string,mixed>> */
    private array $logs;

    /**
     * @param array<int, array<string,mixed>> $logs
     */
    public function __construct(array $logs)
    {
        $this->logs = $logs;
    }

    /**
     * @return array<string,mixed>
     */
    public function get(int $days = 7): array
    {
        $since = time() - ($days * 24 * 60 * 60);
        $recentLogs = array_filter($this->logs, fn (array $log) => $log['timestamp'] >= $since);

        $stats = [
            'total_events' => \count($recentLogs),
            'external_user_events' => 0,
            'security_violations' => 0,
            'permission_changes' => 0,
            'events_by_type' => [],
            'events_by_level' => [],
        ];

        foreach ($recentLogs as $log) {
            $stats = $this->accumulateCoreCounters($stats, $log);
            $stats = $this->accumulateTypeAndLevel($stats, $log);
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $log
     * @return array<string,mixed>
     */
    private function accumulateCoreCounters(array $stats, array $log): array
    {
        \assert(\is_int($stats['external_user_events']));
        \assert(\is_int($stats['security_violations']));
        \assert(\is_int($stats['permission_changes']));

        if (isset($log['is_external']) && true === $log['is_external']) {
            ++$stats['external_user_events'];
        }

        $eventType = isset($log['event_type']) && \is_scalar($log['event_type']) ? (string) $log['event_type'] : '';
        if (AuditLogger::EVENT_SECURITY_VIOLATION === $eventType) {
            ++$stats['security_violations'];
        }
        if (AuditLogger::EVENT_PERMISSION_CHANGED === $eventType) {
            ++$stats['permission_changes'];
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $log
     * @return array<string,mixed>
     */
    private function accumulateTypeAndLevel(array $stats, array $log): array
    {
        \assert(\is_array($stats['events_by_type']));
        \assert(\is_array($stats['events_by_level']));

        $eventType = isset($log['event_type']) && is_scalar($log['event_type']) ? (string) $log['event_type'] : 'unknown';
        $level = isset($log['level']) && is_scalar($log['level']) ? (string) $log['level'] : 'unknown';

        if (!isset($stats['events_by_type'][$eventType])) {
            $stats['events_by_type'][$eventType] = 0;
        }
        if (!isset($stats['events_by_level'][$level])) {
            $stats['events_by_level'][$level] = 0;
        }

        \assert(\is_int($stats['events_by_type'][$eventType]));
        \assert(\is_int($stats['events_by_level'][$level]));

        ++$stats['events_by_type'][$eventType];
        ++$stats['events_by_level'][$level];

        return $stats;
    }
}
