<?php

declare(strict_types=1);

namespace DevDoctor\Core\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    public function load(string $path): DevDoctorConfig
    {
        if (! is_file($path)) {
            return new DevDoctorConfig;
        }

        try {
            $data = Yaml::parseFile($path) ?? [];
        } catch (ParseException $exception) {
            throw new InvalidDevDoctorConfig($exception->getMessage(), previous: $exception);
        }

        if (! is_array($data)) {
            throw new InvalidDevDoctorConfig('devdoctor.yml must contain a YAML mapping.');
        }

        $envConfig = $data['modules']['env'] ?? $data;

        if (! is_array($envConfig)) {
            throw new InvalidDevDoctorConfig('modules.env must contain a YAML mapping.');
        }

        $files = $envConfig['files'] ?? $data['files'] ?? [];
        $rules = $envConfig['rules'] ?? $data['rules'] ?? [];
        $ignore = $envConfig['ignore'] ?? $data['ignore'] ?? [];

        if (! is_array($files) || ! is_array($rules) || ! is_array($ignore)) {
            throw new InvalidDevDoctorConfig('files, rules, and ignore must be YAML mappings when present.');
        }

        return new DevDoctorConfig(
            envFile: is_string($files['env'] ?? null) ? $files['env'] : '.env',
            exampleFile: is_string($files['example'] ?? null) ? $files['example'] : '.env.example',
            envRules: $this->normalizeRules($rules),
            ignoreMissingInEnv: $this->stringList($ignore['missing_in_env'] ?? []),
            ignoreMissingInExample: $this->stringList($ignore['missing_in_example'] ?? []),
        );
    }

    /**
     * @param  array<mixed>  $rules
     * @return array<string, array<string, mixed>>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $key => $rule) {
            if (! is_string($key) || ! is_array($rule)) {
                continue;
            }

            $normalized[$key] = $rule;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
