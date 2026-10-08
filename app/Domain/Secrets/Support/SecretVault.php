<?php

namespace App\Domain\Secrets\Support;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Encrypts secret content (AES-256-GCM) with the secrets key, which is kept apart from
 * APP_KEY. Without one configured, a key derived from APP_KEY is used.
 */
class SecretVault
{
    private ?Encrypter $encrypter = null;

    public function encrypt(string $plaintext): string
    {
        return $this->encrypter()->encryptString($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->encrypter()->decryptString($ciphertext);
    }

    private function encrypter(): Encrypter
    {
        return $this->encrypter ??= new Encrypter($this->key(), 'aes-256-gcm');
    }

    private function key(): string
    {
        $configured = (string) config('kitedesk.secrets.key');

        if ($configured !== '') {
            $key = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : $configured;

            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException('KITEDESK_SECRETS_KEY must be 32 bytes, e.g. "base64:" followed by 32 random bytes in base64.');
            }

            return $key;
        }

        $appKey = (string) config('app.key');
        $appKey = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7)) : $appKey;

        return hash_hmac('sha256', 'kitedesk-secrets', $appKey, true);
    }
}
