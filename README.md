# DevDoctor

Developer diagnostics for humans.

DevDoctor is a read-only CLI for catching common local, repository, environment, cache, HTTP URL, database, queue, Docker, Composer, Git, and CI problems before they turn into manual debugging sessions.

Current version: `1.10.0`

## Installation

DevDoctor requires PHP 8.5 or newer.

```bash
composer install
php devdoctor list
```

DevDoctor currently runs from the project checkout:

```bash
php devdoctor <command>
```

Build a local PHAR:

```bash
php devdoctor app:build devdoctor.phar --build-version=1.10.0 --no-interaction
php builds/devdoctor.phar --version
```

Tagged releases publish both the PHAR and PHPacker standalone binaries for Linux, macOS, and Windows. The standalone binaries bundle a PHP runtime, so users do not need PHP installed just to run DevDoctor.

Install as a Composer package:

```bash
composer global require rtcoder/devdoctor
devdoctor --version
```

Install locally in a project:

```bash
composer require --dev rtcoder/devdoctor
vendor/bin/devdoctor ci
```

## Commands

```text
env        Check dotenv files and DevDoctor env rules
cache     Check framework and tool cache health
http      Check HTTP URL configuration
ports      Check local development port conflicts
composer   Check Composer project health
db         Check database environment configuration
queue      Check queue environment configuration
php        Check PHP runtime and platform health
node       Check Node.js project and package manager health
laravel    Check Laravel application health
security   Check project security posture
git        Check Git repository hygiene
docker     Check Docker and Docker Compose project health
health     Run a broad local project health check
ci         Run CI-safe DevDoctor diagnostics
presets    Detect supported project framework and tooling presets
init       Generate an initial devdoctor.yml configuration
```

All public commands support the shared options:

```bash
--path=. --format=table --ci --strict
```

Diagnostic commands also support output shaping without changing the underlying exit code:

```bash
--only=error,warning
--summary-only
--no-hints
```

Supported diagnostic output formats are `table`, `json`, and `sarif`.

Laravel Zero already defines a global `--env` option, so DevDoctor exposes the env-file selector as `--env-file`:

```bash
php devdoctor env --env-file=.env.local --example=.env.example
```

## Examples

```bash
php devdoctor env
php devdoctor env --format=json --strict
php devdoctor env --only=error,warning --no-hints
php devdoctor health --summary-only
php devdoctor cache
php devdoctor cache --max-size=1024
php devdoctor http
php devdoctor http --url=https://example.test
php devdoctor ports --common
php devdoctor ports --port=3000 --port=5173
php devdoctor php
php devdoctor php --ci --minimum-memory=256
php devdoctor node
php devdoctor laravel
php devdoctor security
php devdoctor composer
php devdoctor db
php devdoctor db --connect
php devdoctor queue
php devdoctor git --require-clean --scan-large-files
php devdoctor docker --compose-file=docker-compose.yml
php devdoctor health
php devdoctor health --include-ports
php devdoctor ci --modules=env,php,node,laravel,composer,db,git,docker --no-fail-on-warnings
php devdoctor presets --format=json
php devdoctor init --dry-run
```

`ports` uses platform-specific read-only providers: `lsof` on macOS/Linux, `ss` as a Linux fallback, and `netstat -ano` on Windows. If no supported provider is available, DevDoctor reports `DD_PORT_PROVIDER_UNAVAILABLE` instead of failing unexpectedly.

## Platform Support

DevDoctor targets Linux, macOS, and Windows:

| Capability | Linux | macOS | Windows |
| --- | --- | --- | --- |
| Command discovery | Native executable lookup | Native executable lookup | Native executable lookup |
| Port listeners | `lsof`, then `ss` | `lsof` | `netstat -ano` |
| Process suggestion | `kill -TERM <pid>` | `kill -TERM <pid>` | `taskkill /PID <pid>` |
| Cache diagnostics | Supported | Supported | Supported |
| HTTP URL diagnostics | Supported | Supported | Supported |
| PHP runtime diagnostics | Supported | Supported | Supported |
| Node.js project diagnostics | Supported | Supported | Supported |
| Laravel application diagnostics | Supported | Supported | Supported |
| Security posture diagnostics | Supported | Supported | Supported |
| Database configuration diagnostics | Supported | Supported | Supported |
| Database connection diagnostics | Supported when the matching PDO driver is installed | Supported when the matching PDO driver is installed | Supported when the matching PDO driver is installed |
| Queue configuration diagnostics | Supported | Supported | Supported |
| Composer, Git, Docker | Supported when their executables are installed | Supported when their executables are installed | Supported when their executables are installed |

Platform-specific commands are only suggested. DevDoctor never terminates a process automatically.

## Diagnostic Details

- Compose interpolation understands required references such as `${VAR}` and `${VAR?message}`.
- Compose references with defaults such as `${VAR:-default}` and `${VAR-default}` do not produce missing-variable warnings.
- Git reports `DD_GIT_BINARY_MISSING` when Git is unavailable instead of treating the path as a non-repository.
- Windows port diagnostics use `tasklist` when available to resolve a PID to a process name.
- Cache diagnostics inspect known Laravel, Symfony, and Node cache directories, size thresholds, writability, and Laravel cache artifacts without deleting anything.
- HTTP diagnostics validate configured `APP_URL`, `FRONTEND_URL`, `API_URL`, and explicit `--url` values without making network requests.
- PHP diagnostics compare the active CLI runtime with `composer.json`, required `ext-*` packages, `memory_limit`, loaded `php.ini`, and Xdebug state in CI.
- Node.js diagnostics inspect `package.json`, package manager lockfiles, `node_modules`, `engines.node`, `.nvmrc`, `.node-version`, and risky package scripts.
- Laravel diagnostics inspect `.env`, `APP_KEY`, production debug mode, `APP_URL`, runtime directories, and config cache state.
- Database diagnostics inspect `DB_CONNECTION`, required database keys, valid ports, SQLite file paths, and optional read-only PDO connectivity with `--connect`.
- Queue diagnostics inspect `QUEUE_CONNECTION`, common async driver requirements, and production environments that still use the synchronous queue driver.
- Security diagnostics inspect env example secrets, hard-coded secret patterns, risky Composer and package scripts, Docker privileged mode, Docker socket mounts, and `.env` ignore gaps.
- Health aggregates local project diagnostics across `presets`, `env`, `cache`, `http`, `php`, `node`, `laravel`, `composer`, `db`, `queue`, `git`, `docker`, and `security`; add `--include-ports` to include common local port checks.
- Composer reports `DD_COMPOSER_LOCK_OUTDATED` when `composer.lock` is older than `composer.json`.
- Process execution uses argument arrays and supports project paths containing spaces.

## Project Presets

The `presets` command detects supported project stacks from files and declared dependencies without running project tools:

| Preset | Detection evidence |
| --- | --- |
| Laravel | `laravel/framework` or `artisan` |
| Symfony | `symfony/framework-bundle` or `bin/console` |
| Node.js | `package.json` |
| Vite | `vite` dependency or a `vite.config.*` file |
| Next.js | `next` dependency |
| Docker Compose | A supported Compose file |

Preset detection is informational and can be included in CI explicitly:

```bash
php devdoctor ci --modules=presets,env,php,node,laravel,composer,git,docker
```

## Table Output

```text
DevDoctor

Module     Status   Errors Warnings  Info
env        warning       0        1     0

Warnings
  [DD_ENV_MISSING_IN_ENV] .env.example:2 QUEUE_CONNECTION QUEUE_CONNECTION exists in .env.example but is missing in .env
    Hint: Add the key to the environment file or ignore it explicitly when it is optional.
```

Actionable findings may include a hint and a suggested command. Suggested commands are never executed by DevDoctor. Commands that can change system state, such as terminating a process, are marked as destructive in JSON output.

## JSON Output

```json
{
    "tool": "devdoctor",
    "schema_version": "1.0",
    "status": "warning",
    "summary": {
        "errors": 0,
        "warnings": 1,
        "info": 0,
        "suppressed": 0
    },
    "modules": [
        {
            "name": "env",
            "status": "warning",
            "summary": {
                "errors": 0,
                "warnings": 1,
                "info": 0,
                "suppressed": 0
            },
            "issues": [
                {
                    "code": "DD_ENV_MISSING_IN_ENV",
                    "severity": "warning",
                    "message": "QUEUE_CONNECTION exists in .env.example but is missing in .env",
                    "module": "env",
                    "file": ".env.example",
                    "key": "QUEUE_CONNECTION",
                    "hint": "Add the key to the environment file or ignore it explicitly when it is optional.",
                    "suppressed": false
                }
            ]
        }
    ]
}
```

## SARIF Output

Use SARIF 2.1.0 for code scanning integrations:

```bash
php devdoctor ci --format=sarif > devdoctor.sarif
```

Each result maps the issue code to a SARIF rule id, includes relative file locations when available, and carries a stable `devdoctorFingerprint/v1` based on code, module, file, and key. Hints and fix descriptions are included as metadata; suggested commands are never executed.

## Health

The health command is a local, broad project overview. It uses the same output formats as other diagnostics and keeps `ports` opt-in to avoid machine-specific noise:

```bash
php devdoctor health --format=json
php devdoctor health --include-ports
php devdoctor health --modules=env,security --exclude=security
```

Unknown health modules return exit code `3`.

## CI

The CI aggregator runs `env`, `php`, `node`, `laravel`, `composer`, `git`, and `docker` by default. `ports` and `security` are excluded by default because they can depend on local machine state or intentionally present local files.

```bash
php devdoctor ci --format=json
php devdoctor ci --modules=env,php,node,laravel,composer --exclude=composer
php devdoctor ci --modules=security --no-fail-on-warnings
php devdoctor ci --no-fail-on-warnings
```

Unknown modules return exit code `3`. Selected modules are always included in JSON output.

The repository CI workflow runs tests on Linux, macOS, and Windows with PHP 8.5. It also builds and smoke-tests the PHAR and a PHPacker standalone Linux binary.

### GitHub Action

The composite GitHub Action downloads a pinned release PHAR, verifies its SHA-256 checksum, and runs CI diagnostics:

```yaml
- uses: rtcoder/devdoctor@v1.10.0
  with:
    version: v1.10.0
    format: sarif
```

Always pin both the Action ref and the `version` input. The Action does not use `latest`.

## Baselines

Baselines let an existing project acknowledge current warnings and errors while continuing to fail CI for new findings:

```bash
php devdoctor ci --write-baseline=devdoctor-baseline.json
php devdoctor ci --baseline=devdoctor-baseline.json
```

Baseline fingerprints use issue code, module, normalized file path, and key. They do not depend on messages or line numbers. Suppressed findings remain visible in table, JSON, and SARIF output, but they do not affect status or exit code. Only warnings and errors are written. Use `--force` to intentionally replace an existing baseline.

## Configuration

DevDoctor reads `devdoctor.yml` for env rules:

```yaml
modules:
  env:
    files:
      env: .env
      example: .env.example
    ignore:
      missing_in_env:
        - OPTIONAL_TOKEN
      missing_in_example:
        - LOCAL_ONLY_KEY
    rules:
      APP_KEY:
        required: true
      APP_DEBUG:
        type: bool
        forbidden_when:
          APP_ENV: production
      CACHE_DRIVER:
        allowed:
          - file
          - redis
      REDIS_URL:
        required_when:
          CACHE_DRIVER: redis
```

Use a different config file with:

```bash
php devdoctor env --config=devdoctor.yml
php devdoctor ci --config=devdoctor.yml
```

Generate an initial configuration with the interactive wizard:

```bash
php devdoctor init
php devdoctor init --dry-run
php devdoctor init --config=config/devdoctor.yml
```

The wizard detects supported env files and project presets, previews the YAML, and writes only after confirmation. It never copies environment values into the generated file. Existing files require `--force` and a second confirmation. In CI or `--no-interaction` mode, use `--dry-run`.

## Exit Codes

```text
0 = no issues
1 = warnings only
2 = errors found
3 = invalid DevDoctor config
4 = required input / dependency missing
5 = internal error / unexpected exception
```

## Stable Contracts

- JSON output includes `schema_version` and follows the stable v1 schema at [schemas/v1/devdoctor-output.schema.json](schemas/v1/devdoctor-output.schema.json).
- [schemas/devdoctor-output.schema.json](schemas/devdoctor-output.schema.json) remains an alias for the latest schema.
- Issue identifiers are listed in [docs/issue-codes.md](docs/issue-codes.md) and the machine-readable [schemas/v1/issue-codes.json](schemas/v1/issue-codes.json).
- Automation should match issue codes rather than human-readable messages.
- Schema v1 will not receive breaking changes during `v1.x`. Existing issue codes will not be removed or repurposed without deprecation, and new codes may be added.
- The version recorded in `composer.json` under `extra.devdoctor.version` matches the Git release tag.

## Documentation

Full static documentation lives in [docs/](docs/index.html), including installation, command reference, config, output formats, baseline, safety, contracts, release verification, and pinned CI examples for GitHub Actions, GitLab CI, and Bitbucket Pipelines.

## Safety

DevDoctor is read-only by default:

- It does not rewrite `.env`, Compose, Composer, Git, or project files.
- It does not run `composer install`, `composer update`, Composer scripts, or internet-dependent audits.
- It does not run `docker compose up`, `start`, `stop`, `rm`, or `prune`.
- Hints and suggested commands are informational only and are never executed.
- Port diagnostics may suggest `kill -TERM <pid>`, but never execute it.
- Basic diagnostics do not require telemetry or internet access.

## Release Verification

Tagged releases publish `devdoctor.phar`, PHPacker standalone binaries, SHA-256 checksums, Cosign signatures, and Sigstore certificates.

Standalone release assets:

```text
devdoctor-linux-x64
devdoctor-linux-arm64
devdoctor-macos-x64
devdoctor-macos-arm64
devdoctor-windows-x64.exe
```

Verify the PHAR checksum before running a downloaded PHAR:

```bash
sha256sum --check devdoctor.phar.sha256
```

Verify every executable release asset with the combined checksum file:

```bash
sha256sum --check devdoctor.sha256
```

Verify the keyless signature with Cosign:

```bash
cosign verify-blob \
  --certificate devdoctor.phar.pem \
  --signature devdoctor.phar.sig \
  --certificate-identity-regexp 'https://github.com/rtcoder/devdoctor/' \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com \
  devdoctor.phar
```

## Homebrew

DevDoctor is available from the `rtcoder/tap` Homebrew tap:

```bash
brew tap rtcoder/tap
brew install devdoctor
```

The release workflow can update `rtcoder/homebrew-tap` after each tag when the repository secret `HOMEBREW_TAP_TOKEN` is configured with write access to the tap.

## Development

```bash
composer validate --strict
php devdoctor test
./vendor/bin/pint --test
php devdoctor app:build devdoctor.phar --build-version=1.10.0 --no-interaction
php builds/devdoctor.phar --version
./vendor/bin/phpacker build --src=./builds/devdoctor.phar --dest=./builds/standalone --php=8.5 linux x64
./builds/standalone/linux/linux-x64 --version
```

## Roadmap

See [ROADMAP.md](ROADMAP.md) for the implementation roadmap and later distribution work.
