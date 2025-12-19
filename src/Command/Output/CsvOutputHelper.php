<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Output;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CSV输出辅助类.
 */
final class CsvOutputHelper
{
    /**
     * 输出CSV格式.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string>                    $headers
     */
    public function outputCsv(SymfonyStyle $io, array $data, array $headers): void
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
}
