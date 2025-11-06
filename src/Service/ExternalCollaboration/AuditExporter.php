<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Tourze\LarkAppBotBundle\Exception\InvalidAuditFormatException;

final class AuditExporter
{
    /**
     * @param array<int, array<string, mixed>> $logs
     */
    public function export(array $logs, string $format = 'json'): string
    {
        if ('csv' === $format) {
            return $this->exportToCsv($logs);
        }

        $result = json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        if (false === $result) {
            throw new InvalidAuditFormatException('Failed to encode logs to JSON');
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     */
    private function exportToCsv(array $logs): string
    {
        if ([] === $logs) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        if (false === $output) {
            throw new InvalidAuditFormatException('Failed to create temporary file for CSV export');
        }

        try {
            $firstLog = $logs[0] ?? null;
            if (!is_array($firstLog)) {
                throw new InvalidAuditFormatException('Invalid log data structure');
            }
            $headers = array_keys($firstLog);
            if (false === fputcsv($output, $headers)) {
                throw new InvalidAuditFormatException('Failed to write CSV headers');
            }

            foreach ($logs as $log) {
                $row = $this->prepareCsvRow($log, $headers);
                if (false === fputcsv($output, $row)) {
                    throw new InvalidAuditFormatException('Failed to write CSV row');
                }
            }

            rewind($output);
            $csv = stream_get_contents($output);
            if (false === $csv) {
                throw new InvalidAuditFormatException('Failed to read CSV content from stream');
            }

            return $csv;
        } finally {
            fclose($output);
        }
    }

    /**
     * @param array<string, mixed> $log
     * @param array<string>        $headers
     * @return array<string>
     */
    private function prepareCsvRow(array $log, array $headers): array
    {
        $row = [];
        foreach ($headers as $header) {
            $value = $log[$header] ?? '';
            $row[] = $this->formatCsvValue($value);
        }

        return $row;
    }

    private function formatCsvValue(mixed $value): string
    {
        if (\is_array($value)) {
            $jsonValue = json_encode($value, \JSON_UNESCAPED_UNICODE);
            if (false === $jsonValue) {
                throw new InvalidAuditFormatException('Failed to encode array value to JSON');
            }

            return $jsonValue;
        }

        return is_scalar($value) ? (string) $value : (is_object($value) ? (method_exists($value, '__toString') ? (string) $value : '[object]') : '[mixed]');
    }
}
