<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Docker;

final class ComposeEnvReferenceScanner
{
    /**
     * Return only variables that must exist for Compose interpolation.
     *
     * @return list<string>
     */
    public function requiredReferences(string $content): array
    {
        if (preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)([^}]*)}/', $content, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $required = [];

        foreach ($matches as $match) {
            $operator = $match[2] ?? '';

            if ($operator !== '' && preg_match('/^:?[+-]/', $operator) === 1) {
                continue;
            }

            $required[] = $match[1];
        }

        return array_values(array_unique($required));
    }
}
