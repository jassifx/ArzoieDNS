<?php
declare(strict_types=1);
namespace Arzoie\Core;

final class Crypto
{
    private static function key(): string
    {
        $key = base64_decode(Env::get('APP_KEY'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY must contain a base64 encoded 32-byte key.');
        }
        return $key;
    }

    public static function seal(array $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox(json_encode($value, JSON_THROW_ON_ERROR), $nonce, self::key()));
    }

    public static function open(string $value): array
    {
        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('Invalid encrypted credential');
        }
        $clear = sodium_crypto_secretbox_open(substr($data, 24), substr($data, 0, 24), self::key());
        if ($clear === false) { throw new \RuntimeException('Credential authentication failed'); }
        return json_decode($clear, true, flags: JSON_THROW_ON_ERROR);
    }
}
