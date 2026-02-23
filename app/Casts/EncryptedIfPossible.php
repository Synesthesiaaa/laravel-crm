<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\GetCastable;
use Illuminate\Contracts\Database\Eloquent\SetCastable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts on set; on get, decrypts if encrypted, otherwise returns raw (for backward compatibility with existing plaintext).
 */
class EncryptedIfPossible implements GetCastable, SetCastable
{
    public static function castUsing(array $arguments): object
    {
        return new class implements \Illuminate\Contracts\Database\Eloquent\CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes)
            {
                if ($value === null || $value === '') {
                    return $value;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            }

            public function set($model, string $key, $value, array $attributes)
            {
                if ($value === null || $value === '') {
                    return [$key => $value];
                }
                return [$key => Crypt::encryptString($value)];
            }
        };
    }
}
