<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignDisplayNameFormatter
{
    /**
     * Limite atual usado nas tabelas que permitem expandir o nome da campanha.
     */
    protected int $tablePreviewLimit = 30;

    /**
     * Resolve os codigos ISO2 dos paises vinculados a cada campanha.
     *
     * O retorno e um mapa no formato:
     * [
     *   campaign_id => ['AR', 'BR', 'ES'],
     * ]
     *
     * Isso permite montar o label final sem N+1 nas listagens.
     *
     * @param array<int> $campaignIds
     * @return array<int, array<int, string>>
     */
    public function countryCodesByCampaignIds(array $campaignIds): array
    {
        $ids = collect($campaignIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('campaign_country as cc')
            ->join('countries as c', 'c.id', '=', 'cc.country_id')
            ->whereIn('cc.campaign_id', $ids->all())
            ->orderBy('c.iso2')
            ->get(['cc.campaign_id', 'c.iso2'])
            ->groupBy('campaign_id')
            ->map(function (Collection $rows): array {
                return $rows
                    ->pluck('iso2')
                    ->map(static fn ($iso2) => strtoupper(trim((string) $iso2)))
                    ->filter(static fn ($iso2) => $iso2 !== '')
                    ->unique()
                    ->values()
                    ->all();
            })
            ->all();
    }

    /**
     * Gera o nome completo e a versao resumida para tabela.
     *
     * @param array<int, string> $countryCodes
     * @return array{full:string,display:string,display_name:string,suffix:string}
     */
    public function make(string $campaignName, array $countryCodes = []): array
    {
        $baseName = trim($campaignName);
        $suffix = $this->buildCountrySuffix($countryCodes);
        $full = trim($baseName . ($suffix === '' ? '' : ' ' . $suffix));
        $displayName = $this->truncateNameOnly($baseName, $this->tablePreviewLimit);
        $display = trim($displayName . ($suffix === '' ? '' : ' ' . $suffix));

        return [
            'full' => $full,
            'display' => $display,
            'display_name' => $displayName,
            'suffix' => $suffix,
        ];
    }

    /**
     * @param array<int, string> $countryCodes
     */
    protected function buildCountrySuffix(array $countryCodes): string
    {
        $codes = collect($countryCodes)
            ->map(static fn ($iso2) => strtoupper(trim((string) $iso2)))
            ->filter(static fn ($iso2) => $iso2 !== '')
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return '';
        }

        if ($codes->count() <= 3) {
            return '[' . $codes->implode(',') . ']';
        }

        return '[' . $codes->take(3)->implode(',') . '+]';
    }

    protected function truncateNameOnly(string $campaignName, int $limit): string
    {
        $baseName = trim($campaignName);
        if ($baseName === '') {
            return '';
        }

        if (mb_strlen($baseName) <= $limit) {
            return $baseName;
        }

        return mb_substr($baseName, 0, max($limit - 3, 0)) . '...';
    }
}
