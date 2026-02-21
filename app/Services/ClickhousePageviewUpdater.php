<?php

namespace App\Services;

use RuntimeException;

class ClickhousePageviewUpdater
{
    public function __construct(
        protected ClickhouseHttpService $clickhouse
    ) {
    }

    public function updateById(int $pageviewId, array $fields): void
    {
        if ($pageviewId <= 0 || $fields === []) {
            return;
        }

        $allowed = [
            'ip_category_id',
            'country_code',
            'country_name',
            'region_name',
            'city',
            'latitude',
            'longitude',
            'timezone',
            'device_category_id',
            'browser_id',
            'device_type',
            'device_brand',
            'device_model',
            'os_name',
            'os_version',
            'browser_name',
            'browser_version',
            'conversion',
        ];

        $assignments = [];
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                throw new RuntimeException("Campo não permitido para UPDATE no ClickHouse: {$column}");
            }

            $assignments[] = $this->clickhouse->quoteIdentifier($column) . ' = ' . $this->toLiteral($value);
        }

        if ($assignments === []) {
            return;
        }

        $db = $this->clickhouse->quoteIdentifier($this->clickhouse->databaseName());
        $table = $this->clickhouse->quoteIdentifier('pageviews');
        $setSql = implode(', ', $assignments);

        $sql = "ALTER TABLE {$db}.{$table} UPDATE {$setSql} WHERE id = {$pageviewId};";
        $this->clickhouse->execute($sql);
    }

    public function markConversion(int $pageviewId): void
    {
        $this->updateById($pageviewId, [
            'conversion' => 1,
        ]);
    }

    protected function toLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $text = (string) $value;
        $escaped = str_replace(['\\', '\''], ['\\\\', '\'\''], $text);

        return "'{$escaped}'";
    }
}

