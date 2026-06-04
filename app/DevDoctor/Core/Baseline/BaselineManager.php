<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Baseline;

use App\DevDoctor\Core\Issue;
use App\DevDoctor\Core\IssueCollection;
use App\DevDoctor\Core\IssueFingerprint;
use App\DevDoctor\Core\ModuleResult;
use App\DevDoctor\Core\Severity;
use JsonException;

final class BaselineManager
{
    public function load(string $path): Baseline
    {
        if (! is_file($path)) {
            throw new InvalidBaseline('Baseline file does not exist: '.$path);
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidBaseline('Baseline file is not valid JSON: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['fingerprints'] ?? null)) {
            throw new InvalidBaseline('Baseline file must contain version 1 and a fingerprints array.');
        }

        $fingerprints = array_values(array_filter(
            $data['fingerprints'],
            static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1,
        ));

        if (count($fingerprints) !== count($data['fingerprints'])) {
            throw new InvalidBaseline('Baseline fingerprints must be SHA-256 strings.');
        }

        return new Baseline(array_values(array_unique($fingerprints)));
    }

    /**
     * @param  list<ModuleResult>  $results
     */
    public function write(string $path, array $results): void
    {
        $fingerprints = [];

        foreach ($results as $result) {
            foreach ($result->issues->all() as $issue) {
                if ($issue->severity === Severity::INFO) {
                    continue;
                }

                $fingerprints[] = IssueFingerprint::for($issue);
            }
        }

        sort($fingerprints);
        file_put_contents($path, json_encode([
            'version' => 1,
            'fingerprints' => array_values(array_unique($fingerprints)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * @param  list<ModuleResult>  $results
     * @return list<ModuleResult>
     */
    public function apply(Baseline $baseline, array $results): array
    {
        return array_map(
            static fn (ModuleResult $result): ModuleResult => new ModuleResult(
                $result->name,
                new IssueCollection(array_map(
                    static fn (Issue $issue): Issue => $baseline->contains(IssueFingerprint::for($issue))
                        ? $issue->withSuppressed()
                        : $issue,
                    $result->issues->all(),
                )),
            ),
            $results,
        );
    }
}
