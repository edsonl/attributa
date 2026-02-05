<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Str;

class GenerateCampaignCode
{
    /**
     * Alfabeto Crockford Base32 (sem letras ambíguas)
     */
    protected string $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(string $channelCode): string
    {
        do {
            // Entropia baseada em ULID
            $entropy = strtoupper(Str::ulid()->toBase32());

            // Pega só 8 chars seguros
            $body = substr($entropy, 0, 8);

            $base = "CMP-{$channelCode}-{$body}";

            $checksum = $this->checksum($base);

            $code = "{$base}{$checksum}";
        } while (Campaign::where('code', $code)->exists());

        return $code;
    }

    protected function checksum(string $value): string
    {
        $sum = 0;

        foreach (str_split($value) as $char) {
            $sum += ord($char);
        }

        return str_pad($sum % 97, 2, '0', STR_PAD_LEFT);
    }
}
