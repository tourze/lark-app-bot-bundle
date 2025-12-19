<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Output;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 批量结果格式化器.
 */
final class BatchResultFormatter
{
    /**
     * 输出批量结果.
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string>                    $fields
     */
    public function outputBatchResults(SymfonyStyle $io, array $results, string $format, array $fields): void
    {
        match ($format) {
            'json' => $this->outputBatchJson($io, $results, $fields),
            'csv' => $this->outputBatchCsv($io, $results, $fields),
            default => $this->outputBatchTable($io, $results),
        };
    }

    /**
     * 输出批量JSON.
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string>                    $fields
     */
    private function outputBatchJson(SymfonyStyle $io, array $results, array $fields): void
    {
        $data = [] === $fields ? $results : array_map(
            fn ($user) => $this->filterFields($user, $fields),
            $results
        );
        $jsonOutput = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        $io->writeln(false !== $jsonOutput ? $jsonOutput : '{}');
    }

    /**
     * 输出批量CSV.
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string>                    $fields
     */
    private function outputBatchCsv(SymfonyStyle $io, array $results, array $fields): void
    {
        $headers = [] === $fields ? (isset($results[0]) ? array_keys($results[0]) : []) : $fields;
        $data = [] === $fields ? $results : array_map(
            fn ($user) => $this->filterFields($user, $fields),
            $results
        );
        $this->outputCsv($io, $data, array_values($headers));
    }

    /**
     * 输出批量表格.
     *
     * @param array<int, array<string, mixed>> $results
     */
    private function outputBatchTable(SymfonyStyle $io, array $results): void
    {
        $io->title('批量查询结果');
        $rows = [];
        foreach ($results as $user) {
            $status = isset($user['status']) && \is_int($user['status']) ? $user['status'] : null;
            $rows[] = [
                $user['open_id'] ?? 'N/A',
                $user['name'] ?? 'N/A',
                $user['email'] ?? 'N/A',
                $user['mobile'] ?? 'N/A',
                $this->formatStatus($status),
            ];
        }
        $io->table(['Open ID', '姓名', '邮箱', '手机', '状态'], $rows);
    }

    /**
     * 输出CSV格式.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string>                    $headers
     */
    private function outputCsv(SymfonyStyle $io, array $data, array $headers): void
    {
        $io->writeln(implode(',', $headers));
        $this->outputCsvRows($io, $data, $headers);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @param array<string>                    $headers
     */
    private function outputCsvRows(SymfonyStyle $io, array $data, array $headers): void
    {
        foreach ($data as $row) {
            $values = $this->formatCsvRow($row, $headers);
            $io->writeln(implode(',', $values));
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string>        $headers
     *
     * @return array<string>
     */
    private function formatCsvRow(array $row, array $headers): array
    {
        $values = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            $valueStr = \is_scalar($value) ? (string) $value : '';
            $values[] = $this->escapeCsvValue($valueStr);
        }

        return $values;
    }

    private function escapeCsvValue(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * 过滤字段.
     *
     * @param array<string, mixed> $data
     * @param array<string>        $fields
     *
     * @return array<string, mixed>
     */
    private function filterFields(array $data, array $fields): array
    {
        $filtered = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $filtered[$field] = $data[$field];
            }
        }

        return $filtered;
    }

    /**
     * 格式化用户状态
     */
    private function formatStatus(?int $status): string
    {
        return match ($status) {
            1 => '已激活',
            2 => '已停用',
            4 => '未激活',
            5 => '已退出',
            null => 'N/A',
            default => \sprintf('未知(%d)', $status),
        };
    }
}
