<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A link that is shown to users: only web links (http/https) and site paths
 * are read back, never javascript:, data: or protocol-relative URLs.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SafeLink implements CastsAttributes
{
    public static function clean(mixed $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return $url;
        }

        return null;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::clean($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
