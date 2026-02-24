<?php

namespace App\Support;

use App\Models\Pageview;
use Illuminate\Support\Collection;

class TrackingEventDescriptions
{
    public static function buildVisitFlowSteps(Pageview $pageview, Collection $events): array
    {
        $summary = self::summarizeEvents($events);

        $engaged = $summary['page_engaged'];
        $formSubmit = $summary['form_submit'];
        $linkClick = $summary['link_click'];
        $useFormStep = !empty($formSubmit['first']);
        $interaction = $useFormStep ? $formSubmit : $linkClick;

        $pageviewFormatted = (string) ($pageview->created_at_formatted ?? '-');
        $conversionDone = (bool) $pageview->conversion;

        return [
            [
                'name' => 1,
                'title' => 'Page View',
                'caption' => $pageviewFormatted,
                'icon' => 'visibility',
                'done' => true,
                'color' => 'primary',
                'tooltip' => self::buildPageviewTooltip(),
            ],
            [
                'name' => 2,
                'title' => 'Page Engaged',
                'caption' => self::buildEngagedCaption($engaged),
                'icon' => 'insights',
                'done' => !empty($engaged['first']),
                'color' => !empty($engaged['first']) ? 'primary' : 'grey-6',
                'tooltip' => self::buildEngagedTooltip($engaged),
            ],
            [
                'name' => 3,
                'title' => $useFormStep ? 'Form Submit' : 'Link Click',
                'caption' => self::buildInteractionCaption($interaction, $useFormStep),
                'icon' => $useFormStep ? 'fact_check' : 'ads_click',
                'done' => !empty($interaction['first']),
                'color' => !empty($interaction['first']) ? 'primary' : 'grey-6',
                'tooltip' => self::buildInteractionTooltip($interaction, $useFormStep),
            ],
            [
                'name' => 4,
                'title' => 'Conversão',
                'caption' => $conversionDone
                    ? $pageviewFormatted . ' • Convertido'
                    : 'Não convertido',
                'icon' => $conversionDone ? 'task_alt' : 'radio_button_unchecked',
                'done' => $conversionDone,
                'color' => $conversionDone ? 'positive' : 'grey-6',
                'tooltip' => self::buildConversionTooltip($conversionDone),
            ],
        ];
    }

    protected static function summarizeEvents(Collection $events): array
    {
        $summary = [
            'page_engaged' => ['first' => null, 'count' => 0, 'reasons' => []],
            'form_submit' => ['first' => null, 'count' => 0, 'reasons' => []],
            'link_click' => ['first' => null, 'count' => 0, 'reasons' => []],
        ];

        foreach ($events as $event) {
            $type = strtolower((string) ($event['event_type'] ?? ''));
            if (!array_key_exists($type, $summary)) {
                continue;
            }

            if ($summary[$type]['first'] === null) {
                $summary[$type]['first'] = $event;
            }

            $summary[$type]['count'] += 1;
            $reason = self::extractReasonLabel($type, $event);
            if ($reason !== '') {
                $summary[$type]['reasons'][] = $reason;
            }
        }

        return $summary;
    }

    protected static function buildPageviewTooltip(): string
    {
        return 'Início da visita nesta página.';
    }

    protected static function buildEngagedCaption(array $engaged): string
    {
        $first = $engaged['first'];
        if (!$first) {
            return 'Sem engajamento detectado';
        }

        $createdAt = (string) ($first['created_at_formatted'] ?? '-');
        $suffix = $engaged['count'] > 1 ? ' (+' . ($engaged['count'] - 1) . ')' : '';
        $reason = self::extractReasonLabel('page_engaged', $first);

        return $reason !== ''
            ? "{$createdAt}{$suffix} • {$reason}"
            : "{$createdAt}{$suffix}";
    }

    protected static function buildEngagedTooltip(array $engaged): string
    {
        if (!$engaged['first']) {
            return 'Nenhum evento de engajamento foi detectado nesta visita.';
        }

        $firstReason = self::extractReasonLabel('page_engaged', $engaged['first']);
        $base = 'Visitante demonstrou interesse na página.';
        if ($firstReason !== '') {
            $base .= "\nMotivo: {$firstReason}.";
        }

        $extras = self::buildExtraReasonSummary($engaged['reasons'], $firstReason);
        return $extras !== '' ? $base . "\n" . $extras : $base;
    }

    protected static function buildInteractionCaption(array $interaction, bool $useFormStep): string
    {
        if (!$interaction['first']) {
            return $useFormStep ? 'Sem envio de formulário' : 'Sem clique em link';
        }

        $createdAt = (string) ($interaction['first']['created_at_formatted'] ?? '-');
        $suffix = $interaction['count'] > 1 ? ' (+' . ($interaction['count'] - 1) . ')' : '';
        $reason = self::extractReasonLabel($useFormStep ? 'form_submit' : 'link_click', $interaction['first']);

        return $reason !== ''
            ? "{$createdAt}{$suffix} • {$reason}"
            : "{$createdAt}{$suffix}";
    }

    protected static function buildInteractionTooltip(array $interaction, bool $useFormStep): string
    {
        if (!$interaction['first']) {
            return $useFormStep
                ? 'Nenhum envio de formulário foi detectado nesta visita.'
                : 'Nenhum clique em link rastreado foi detectado nesta visita.';
        }

        $base = $useFormStep
            ? 'Visitante enviou um formulário.'
            : 'Visitante clicou em um link monitorado.';

        $reason = self::extractReasonLabel($useFormStep ? 'form_submit' : 'link_click', $interaction['first']);
        if ($reason !== '') {
            $base .= "\nDetalhe: {$reason}.";
        }

        $extras = self::buildExtraReasonSummary($interaction['reasons'], $reason);
        return $extras !== '' ? $base . "\n" . $extras : $base;
    }

    protected static function buildConversionTooltip(bool $conversionDone): string
    {
        if ($conversionDone) {
            return 'Esta visita gerou conversão.';
        }

        return 'Esta visita ainda não gerou conversão.';
    }

    protected static function extractReasonLabel(string $eventType, array $event): string
    {
        if ($eventType === 'page_engaged') {
            $rawElementId = strtolower(trim((string) ($event['element_id'] ?? '')));
            $reasonKey = '';

            if (str_starts_with($rawElementId, 'engagement_reason:')) {
                $reasonKey = substr($rawElementId, strlen('engagement_reason:'));
            } else {
                $rawName = trim((string) ($event['element_name'] ?? ''));
                if (preg_match('/^page engaged\s*\((.+)\)$/i', $rawName, $matches) === 1) {
                    $reasonKey = strtolower(trim((string) ($matches[1] ?? '')));
                }
            }

            return match ($reasonKey) {
                'scroll_30' => 'Scroll 30%',
                'time_10s' => 'Tempo 10s',
                'link_click' => 'Clique em link',
                'form_submit' => 'Envio de formulário',
                'interactions' => '2+ interações',
                default => $reasonKey !== '' ? 'Engajamento: ' . $reasonKey : '',
            };
        }

        if ($eventType === 'form_submit') {
            return !empty($event['form_has_user_data']) ? 'Dados informados' : 'Sem dados informados';
        }

        if ($eventType === 'link_click') {
            return 'Clique em link';
        }

        return '';
    }

    protected static function buildExtraReasonSummary(array $reasons, string $primaryReason = ''): string
    {
        if (count($reasons) <= 1) {
            return '';
        }

        $extraReasons = array_slice($reasons, 1);
        if (count($extraReasons) === 0) {
            return '';
        }

        $counts = [];
        $sameAsPrimary = 0;
        foreach ($extraReasons as $reason) {
            if ($reason === '') {
                continue;
            }
            if ($primaryReason !== '' && mb_strtolower($reason) === mb_strtolower($primaryReason)) {
                $sameAsPrimary += 1;
                continue;
            }
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        $parts = [];
        foreach ($counts as $label => $count) {
            $parts[] = $count > 1 ? "{$label} ({$count}x)" : $label;
        }
        if ($sameAsPrimary > 0) {
            $parts[] = $sameAsPrimary . ' ocorrência(s) do mesmo motivo';
        }

        if (count($parts) === 0) {
            return '';
        }

        return 'Eventos adicionais: ' . implode(', ', $parts) . '.';
    }
}
