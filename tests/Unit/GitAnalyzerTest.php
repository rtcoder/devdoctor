<?php

use DevDoctor\Core\CommandAvailabilityInterface;
use DevDoctor\Core\IssueCollection;
use DevDoctor\Core\ProcessResult;
use DevDoctor\Modules\Git\GitAnalyzer;
use DevDoctor\Modules\Git\GitOptions;
use DevDoctor\Modules\Git\GitRunnerInterface;
use Tests\Support\FakeCommandAvailability;

it('reports a missing git binary', function () {
    $issues = analyzeGit(new FakeGitRunner([]), commands: new FakeCommandAvailability);

    expect(gitCodes($issues))->toBe(['DD_GIT_BINARY_MISSING']);
});

it('reports paths outside git repositories as info', function () {
    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => new ProcessResult(128, '', 'not a repository'),
    ]));

    expect(gitCodes($issues))->toBe(['DD_GIT_NOT_REPOSITORY']);
});

it('reports dirty worktree and conflicts', function () {
    $path = gitTempPath();
    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk("UU composer.json\n M README.md\n"),
        'rev-parse --abbrev-ref HEAD' => gitOk("main\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => gitOk("origin/main\n"),
        'rev-list --left-right --count HEAD...@{u}' => gitOk("0\t0\n"),
        'check-ignore .env' => gitOk(".env\n"),
        'ls-files -z' => gitOk(''),
        'ls-files --others --exclude-standard -z' => gitOk(''),
    ], $path), new GitOptions($path, requireClean: true));

    expect(gitCodes($issues))->toBe([
        'DD_GIT_CONFLICTS',
        'DD_GIT_DIRTY_WORKTREE',
    ]);
    expect($issues->all()[1]->severity->value)->toBe('error');
});

it('reports detached head missing upstream and ahead behind state', function () {
    $path = gitTempPath();
    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk(''),
        'rev-parse --abbrev-ref HEAD' => gitOk("HEAD\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => new ProcessResult(128, '', 'no upstream'),
        'check-ignore .env' => gitOk(".env\n"),
        'ls-files -z' => gitOk(''),
        'ls-files --others --exclude-standard -z' => gitOk(''),
    ], $path), new GitOptions($path, requireUpstream: true));

    expect(gitCodes($issues))->toBe([
        'DD_GIT_NO_UPSTREAM',
        'DD_GIT_DETACHED_HEAD',
    ]);
    expect($issues->all()[0]->severity->value)->toBe('error');

    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk(''),
        'rev-parse --abbrev-ref HEAD' => gitOk("main\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => gitOk("origin/main\n"),
        'rev-list --left-right --count HEAD...@{u}' => gitOk("2 3\n"),
        'check-ignore .env' => gitOk(".env\n"),
        'ls-files -z' => gitOk(''),
        'ls-files --others --exclude-standard -z' => gitOk(''),
    ], $path), new GitOptions($path));

    expect(gitCodes($issues))->toBe(['DD_GIT_AHEAD_BEHIND']);
    expect($issues->all()[0]->context)->toBe(['ahead' => 2, 'behind' => 3]);
});

it('reports sensitive files and env ignore gaps', function () {
    $path = gitTempPath();
    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk(''),
        'rev-parse --abbrev-ref HEAD' => gitOk("main\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => gitOk("origin/main\n"),
        'rev-list --left-right --count HEAD...@{u}' => gitOk("0 0\n"),
        'check-ignore .env' => new ProcessResult(1, '', ''),
        'ls-files -z' => gitOk(".env\0config/private.pem\0.env.example\0"),
        'ls-files --others --exclude-standard -z' => gitOk(".env.local\0id_ed25519\0"),
    ], $path), new GitOptions($path));

    expect(gitCodes($issues))->toBe([
        'DD_GIT_TRACKED_SENSITIVE_FILE',
        'DD_GIT_TRACKED_SENSITIVE_FILE',
        'DD_GIT_ENV_NOT_IGNORED',
        'DD_GIT_UNTRACKED_SENSITIVE_FILE',
        'DD_GIT_UNTRACKED_SENSITIVE_FILE',
    ]);
});

it('reports large untracked files when requested', function () {
    $path = gitTempPath();
    file_put_contents($path.'/dump.sql', str_repeat('x', 2048));

    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk(''),
        'rev-parse --abbrev-ref HEAD' => gitOk("main\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => gitOk("origin/main\n"),
        'rev-list --left-right --count HEAD...@{u}' => gitOk("0 0\n"),
        'check-ignore .env' => gitOk(".env\n"),
        'ls-files -z' => gitOk(''),
        'ls-files --others --exclude-standard -z' => gitOk("dump.sql\0"),
    ], $path), new GitOptions($path, scanLargeFiles: true, largeFileThreshold: '1K'));

    expect(gitCodes($issues))->toBe(['DD_GIT_LARGE_UNTRACKED_FILE']);
});

it('reports ready git repositories', function () {
    $path = gitTempPath();
    $issues = analyzeGit(new FakeGitRunner([
        'rev-parse --is-inside-work-tree' => gitOk("true\n"),
        'status --porcelain=v1' => gitOk(''),
        'rev-parse --abbrev-ref HEAD' => gitOk("main\n"),
        'rev-parse --abbrev-ref --symbolic-full-name @{u}' => gitOk("origin/main\n"),
        'rev-list --left-right --count HEAD...@{u}' => gitOk("0 0\n"),
        'check-ignore .env' => gitOk(".env\n"),
        'ls-files -z' => gitOk(''),
        'ls-files --others --exclude-standard -z' => gitOk(''),
    ], $path), new GitOptions($path));

    expect(gitCodes($issues))->toBe(['DD_GIT_READY']);
});

function analyzeGit(
    FakeGitRunner $runner,
    ?GitOptions $options = null,
    ?CommandAvailabilityInterface $commands = null,
): IssueCollection {
    $path = $options?->path ?? gitTempPath();

    return (new GitAnalyzer($runner, $commands ?? new FakeCommandAvailability(['git'])))->analyze($options ?? new GitOptions($path));
}

function gitOk(string $stdout): ProcessResult
{
    return new ProcessResult(0, $stdout, '');
}

/**
 * @return list<string>
 */
function gitCodes(IssueCollection $issues): array
{
    return array_map(static fn ($issue): string => $issue->code->value, $issues->all());
}

function gitTempPath(): string
{
    $path = sys_get_temp_dir().'/devdoctor-git-'.bin2hex(random_bytes(4));
    mkdir($path);

    return $path;
}

final class FakeGitRunner implements GitRunnerInterface
{
    /**
     * @param  array<string, ProcessResult>  $responses
     */
    public function __construct(
        private array $responses,
        private readonly string $path = '',
    ) {}

    public function run(array $arguments, string $workingDirectory): ProcessResult
    {
        expect($workingDirectory)->toBe($this->path !== '' ? $this->path : $workingDirectory);

        $key = implode(' ', $arguments);

        return $this->responses[$key] ?? new ProcessResult(1, '', 'missing fake response: '.$key);
    }
}
