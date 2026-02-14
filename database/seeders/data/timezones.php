<?php

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$identifiers = DateTimeZone::listIdentifiers();
$timezones = [];

foreach ($identifiers as $identifier) {
    $tz = new DateTimeZone($identifier);
    $offsetSeconds = $tz->getOffset($now);
    $sign = $offsetSeconds >= 0 ? '+' : '-';
    $absOffset = abs($offsetSeconds);
    $hours = str_pad((string) intdiv($absOffset, 3600), 2, '0', STR_PAD_LEFT);
    $minutes = str_pad((string) intdiv($absOffset % 3600, 60), 2, '0', STR_PAD_LEFT);
    $utcOffset = "{$sign}{$hours}:{$minutes}";

    $timezones[] = [
        'identifier' => $identifier,
        'label' => "(UTC{$utcOffset}) {$identifier}",
        'utc_offset' => $utcOffset,
    ];
}

usort($timezones, static function (array $a, array $b): int {
    return [$a['utc_offset'], $a['identifier']] <=> [$b['utc_offset'], $b['identifier']];
});

return $timezones;
