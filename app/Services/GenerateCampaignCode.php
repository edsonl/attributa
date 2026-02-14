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
            // ULID em Base32 (26 chars): os 10 primeiros sao timestamp e os 16 finais sao randomicos.
            $entropy = strtoupper(Str::ulid()->toBase32());

            // Usa somente o bloco randomico para reduzir previsibilidade e colisao em alta concorrencia.
            $body = substr($entropy, 10, 8);
            if (strlen($body) < 8) {
                $body = strtoupper(Str::random(8));
            }

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
