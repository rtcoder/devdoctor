<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Env;

final class SecretScanner
{
    /** @var list<string> */
    private array $sensitiveKeyParts = [
        'SECRET',
        'TOKEN',
        'PRIVATE_KEY',
        'ACCESS_KEY',
        'API_KEY',
        'PASSWORD',
        'PASS',
        'CREDENTIAL',
        'AUTH',
        'WEBHOOK_SECRET',
        'CLIENT_SECRET',
    ];

    /** @var list<string> */
    private array $placeholders = [
        'changeme',
        'change_me',
        'example',
        'example-secret',
        'dummy',
        'null',
        'false',
        'true',
        'your-key-here',
        '<secret>',
        'xxx',
        'xxxx',
        '****',
    ];

    public function isSuspicious(EnvEntry $entry): bool
    {
        if (! $this->hasSensitiveKey($entry->key)) {
            return false;
        }

        $value = trim($entry->value);

        if ($value === '' || $this->isPlaceholder($value)) {
            return false;
        }

        return strlen($value) >= 20
            || $this->looksLikeJwt($value)
            || $this->looksLikePrivateKey($value)
            || $this->looksLikeToken($value);
    }

    private function hasSensitiveKey(string $key): bool
    {
        return array_any($this->sensitiveKeyParts, fn ($part) => str_contains(strtoupper($key), $part));
    }

    private function isPlaceholder(string $value): bool
    {
        return in_array(strtolower($value), $this->placeholders, true)
            || preg_match('/^(x+|\*+)$/i', $value) === 1;
    }

    private function looksLikeJwt(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $value) === 1;
    }

    private function looksLikePrivateKey(string $value): bool
    {
        return str_contains($value, 'BEGIN') && str_contains($value, 'PRIVATE KEY');
    }

    private function looksLikeToken(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_+\-\/=]{20,}$/', $value) === 1;
    }
}
