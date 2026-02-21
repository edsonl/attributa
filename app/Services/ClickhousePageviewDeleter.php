<?php

namespace App\Services;

class ClickhousePageviewDeleter
{
    public function __construct(
        protected ClickhouseHttpService $clickhouse
    ) {
    }

    public function deleteByPageviewId(int $pageviewId): void
    {
        if ($pageviewId <= 0) {
            return;
        }

        $this->runDelete("id = {$pageviewId}");
    }

    public function deleteByPageviewIds(array $pageviewIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $pageviewIds
        ), static fn ($id) => $id > 0)));

        if ($ids === []) {
            return;
        }

        $idList = implode(', ', $ids);
        $this->runDelete("id IN ({$idList})");
    }

    public function deleteByCampaign(int $userId, int $campaignId): void
    {
        if ($userId <= 0 || $campaignId <= 0) {
            return;
        }

        $this->runDelete("user_id = {$userId} AND campaign_id = {$campaignId}");
    }

    protected function runDelete(string $whereClause): void
    {
        $db = $this->clickhouse->quoteIdentifier($this->clickhouse->databaseName());
        $table = $this->clickhouse->quoteIdentifier('pageviews');

        $sql = "ALTER TABLE {$db}.{$table} DELETE WHERE {$whereClause};";
        $this->clickhouse->execute($sql);
    }
}

