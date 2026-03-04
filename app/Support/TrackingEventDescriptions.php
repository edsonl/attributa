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
                'title' => 'Visita',
                'caption' => $pageviewFormatted,
                'icon' => 'visibility',
                'done' => true,
                'color' => 'primary',
                'tooltip' => self::buildPageviewTooltip(),
            ],
            [
                'name' => 2,
                'title' => 'Engajamento',
                'caption' => self::buildEngagedCaption($engaged),
                'icon' => 'insights',
                'done' => !empty($engaged['first']),
                'color' => !empty($engaged['first']) ? 'primary' : 'grey-6',
                'tooltip' => self::buildEngagedTooltip($engaged),
            ],
            [
                'name' => 3,
                'title' => $useFormStep ? 'Envio de formulário' : 'Clique em link',
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
            'page_engaged' => ['first' => null, 'count' => 0, 'reason_details' => []],
            'form_submit' => ['first' => null, 'count' => 0, 'reason_details' => []],
            'link_click' => ['first' => null, 'count' => 0, 'reason_details' => []],
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
                $detail = $summary[$type]['reason_details'][$reason] ?? [
                    'count' => 0,
                    'last_ts' => 0,
                    'last_at' => '-',
                ];

                $detail['count'] += 1;
                $eventTs = self::resolveEventTimestamp($event);
                if ($eventTs >= $detail['last_ts']) {
                    $detail['last_ts'] = $eventTs;
                    $detail['last_at'] = self::resolveEventFormattedTime($event);
                }

                $summary[$type]['reason_details'][$reason] = $detail;
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

        $base = 'Visitante demonstrou interesse na página.';
        $details = self::buildDetailedReasonLines(
            $engaged['reason_details'] ?? [],
            ['Rolou 30% da página'],
            ['Fez duas ou mais interações']
        );

        return count($details) > 0
            ? $base . "\nDetalhado:\n" . implode("\n", $details)
            : $base;
    }

    protected static function buildInteractionCaption(array $interaction, bool $useFormStep): string
    {
        if (!$interaction['first']) {
            return $useFormStep ? 'Sem envio de formulário' : 'Sem clique em link';
        }

        $event = $interaction['first'];
        $createdAt = (string) ($event['created_at_formatted'] ?? '-');
        $suffix = $interaction['count'] > 1 ? ' (+' . ($interaction['count'] - 1) . ')' : '';

        if (!$useFormStep) {
            return "{$createdAt}{$suffix}";
        }

        $hasUserData = (bool) ($event['form_has_user_data'] ?? false);
        $filled = max(0, (int) ($event['form_fields_filled'] ?? 0));
        $status = ($hasUserData || $filled > 0) ? 'Dados informados' : 'Sem dados informados';

        return "{$createdAt}{$suffix}\n• {$status}";
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

        $extra = self::buildInteractionExtraLines($interaction['first'] ?? null, $useFormStep);
        $details = self::buildDetailedReasonLines($interaction['reason_details'] ?? []);

        $lines = array_values(array_filter(array_merge($extra, $details), fn ($line) => $line !== ''));
        return count($lines) > 0
            ? $base . "\nDetalhado:\n" . implode("\n", $lines)
            : $base;
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
            $reasonKey = self::extractEventReasonKey($event);

            return match ($reasonKey) {
                'scroll_30' => 'Rolou 30% da página',
                'time_10s' => 'Permaneceu 10 segundos',
                'link_click' => 'Demonstrou intenção ao clicar em link',
                'form_submit' => 'Demonstrou intenção ao iniciar envio de formulário',
                'interactions' => 'Fez duas ou mais interações',
                'reload', 'navigation_reload' => 'Recarregou a página',
                default => $reasonKey !== '' ? 'Engajamento: ' . $reasonKey : '',
            };
        }

        if ($eventType === 'form_submit') {
            return self::describeInteractionEvent($event, true);
        }

        if ($eventType === 'link_click') {
            return self::describeInteractionEvent($event, false);
        }

        return '';
    }

    protected static function resolveEventTimestamp(array $event): int
    {
        $raw = $event['created_at'] ?? null;
        if ($raw instanceof \DateTimeInterface) {
            return $raw->getTimestamp();
        }

        if ($raw === null) {
            return 0;
        }

        $parsed = strtotime((string) $raw);
        return $parsed !== false ? (int) $parsed : 0;
    }

    protected static function resolveEventFormattedTime(array $event): string
    {
        $formatted = trim((string) ($event['created_at_formatted'] ?? ''));
        if ($formatted !== '') {
            return $formatted;
        }

        $raw = $event['created_at'] ?? null;
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('d/m/Y, H:i:s');
        }

        $parsed = strtotime((string) $raw);
        if ($parsed !== false) {
            return date('d/m/Y, H:i:s', $parsed);
        }

        return '-';
    }

    protected static function buildDetailedReasonLines(
        array $reasonDetails,
        array $labelsWithoutCount = [],
        array $hiddenLabels = []
    ): array
    {
        if (count($reasonDetails) === 0) {
            return [];
        }

        uasort($reasonDetails, static function (array $a, array $b) {
            return ((int) ($b['last_ts'] ?? 0)) <=> ((int) ($a['last_ts'] ?? 0));
        });

        $lines = [];
        foreach ($reasonDetails as $label => $meta) {
            if (in_array($label, $hiddenLabels, true)) {
                continue;
            }

            $count = max(1, (int) ($meta['count'] ?? 1));
            $lastAt = trim((string) ($meta['last_at'] ?? '-'));
            if ($lastAt === '') {
                $lastAt = '-';
            }

            if (in_array($label, $labelsWithoutCount, true)) {
                $lines[] = $label . ' - ' . $lastAt;
                continue;
            }

            $lines[] = '(' . $count . ') ' . $label . ' - ' . $lastAt;
        }

        return $lines;
    }

    protected static function extractEventReasonKey(array $event): string
    {
        $reason = strtolower(trim((string) ($event['event_reason'] ?? '')));
        if ($reason !== '') {
            return $reason;
        }

        $rawElementId = strtolower(trim((string) ($event['element_id'] ?? '')));
        if (str_starts_with($rawElementId, 'engagement_reason:')) {
            return substr($rawElementId, strlen('engagement_reason:'));
        }
        if ($rawElementId === 'navigation_type:reload') {
            return 'reload';
        }

        $rawName = trim((string) ($event['element_name'] ?? ''));
        if (preg_match('/^page engaged\s*\((.+)\)$/i', $rawName, $matches) === 1) {
            return strtolower(trim((string) ($matches[1] ?? '')));
        }

        return '';
    }

    protected static function describeInteractionEvent(?array $event, bool $useFormStep): string
    {
        if (!$event) {
            return '';
        }

        $name = self::cropText(trim((string) ($event['element_name'] ?? '')));
        $classes = self::cropText(trim((string) ($event['element_classes'] ?? '')));

        if ($useFormStep) {
            $filled = max(0, (int) ($event['form_fields_filled'] ?? 0));
            $checked = max(0, (int) ($event['form_fields_checked'] ?? 0));
            $parts = [];

            if ($name !== '') {
                $parts[] = $name;
            } else {
                $parts[] = 'Formulário enviado';
            }

            if ($checked > 0) {
                $parts[] = $filled . ' de ' . $checked . ' campos preenchidos';
            } elseif ($filled > 0) {
                $parts[] = $filled . ' campos preenchidos';
            }

            return implode(' • ', $parts);
        }

        if ($name !== '') {
            return $name;
        }

        if ($classes !== '') {
            return 'Clique em elemento com classe ' . $classes;
        }

        return 'Clique em link';
    }

    protected static function buildInteractionExtraLines(?array $event, bool $useFormStep): array
    {
        if (!$event) {
            return [];
        }

        $lines = [];
        $classes = self::cropText(trim((string) ($event['element_classes'] ?? '')));
        if ($classes !== '') {
            $lines[] = 'Classes: ' . $classes;
        }

        if ($useFormStep) {
            $filled = max(0, (int) ($event['form_fields_filled'] ?? 0));
            $checked = max(0, (int) ($event['form_fields_checked'] ?? 0));
            if ($checked > 0) {
                $lines[] = 'Preenchimento: ' . $filled . ' de ' . $checked . ' campos';
            }
        }

        return $lines;
    }

    protected static function cropText(string $value, int $limit = 50): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit) . '...'
            : $text;
    }
}
