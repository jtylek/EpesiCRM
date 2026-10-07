<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Laravel's `encrypted` / `encrypted:array` cast, except that a value the
 * current APP_KEY cannot decrypt reads as null instead of throwing. That is
 * what a database copied from another installation holds: secrets sealed with
 * the other key. Pages then load, and the secret is simply entered again.
 *
 * Use `Encrypted::class` for strings, `Encrypted::class.':array'` for arrays.
 */
class Encrypted implements CastsAttributes
{
    public function __construct(private readonly string $type = 'string') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            $plain = Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }

        return $this->type === 'array' ? json_decode($plain, true) : $plain;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString($this->type === 'array' ? json_encode($value) : (string) $value);
    }
}
