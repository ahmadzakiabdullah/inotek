<?php

namespace App\Services\Reports;

/**
 * A tabular report that can be written as CSV or rendered as a PDF table.
 */
final class Report
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function __construct(
        public readonly string $title,
        public readonly string $sessionName,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?string $note = null,
    ) {}
}
