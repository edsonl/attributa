<?php

namespace App\Services;

use Hashids\Hashids;

class HashidService
{
    protected Hashids $hashids;

    public function __construct()
    {
        $this->hashids = new Hashids(
            (string) config('app.hashids.salt'),
            (int) config('app.hashids.min_length')
        );
    }

    public function encode(int $id): string
    {
        return $this->hashids->encode($id);
    }

    public function decode(string $hash): ?int
    {
        $decoded = $this->hashids->decode($hash);

        return $decoded[0] ?? null;
    }
}
