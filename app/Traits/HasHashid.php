<?php

namespace App\Traits;

use App\Services\HashidService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasHashid
{
    public function getHashidAttribute(): string
    {
        $key = $this->getKey();
        if ($key === null) {
            return '';
        }

        return app(HashidService::class)->encode((int) $key);
    }

    public function getRouteKey(): string
    {
        return $this->hashid;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $id = app(HashidService::class)->decode((string) $value);
        if ($id === null) {
            return null;
        }

        return $this->resolveHashidRouteBindingQuery($this->newQuery(), $id)->first();
    }

    protected function resolveHashidRouteBindingQuery(Builder $query, int $id): Builder
    {
        return $query->whereKey($id);
    }
}
