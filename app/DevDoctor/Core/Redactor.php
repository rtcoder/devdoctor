<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class Redactor
{
    public function redactText(string $value): string
    {
        return preg_replace(
            '/(?i)(secret|token|password|passwd|api[_-]?key|private[_-]?key|credential|auth)(\s*[=:]\s*)([^\s,;]+)/',
            '$1$2********',
            $value,
        ) ?? $value;
    }

    public function redact(?string $value): string
    {
        if ($value === null || $value === '') {
            return '********';
        }

        $length = strlen($value);

        if ($length <= 8) {
            return '********';
        }

        if ($length <= 16) {
            return substr($value, 0, 2).str_repeat('*', max(4, $length - 4)).substr($value, -2);
        }

        return substr($value, 0, 8).str_repeat('*', max(8, $length - 12)).substr($value, -4);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($value) && $this->looksSensitiveKey($key)) {
                $redacted[$key] = $this->redact($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function looksSensitiveKey(string $key): bool
    {
        return preg_match('/(secret|token|password|passwd|api[_-]?key|private[_-]?key|credential|auth)/i', $key) === 1;
    }
}
