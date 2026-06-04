# DevDoctor

Developer diagnostics for humans.

DevDoctor is a read-only CLI for catching common local, repository, environment, Docker, Composer, Git, and CI problems before they turn into manual debugging sessions.

Current version: `0.8.0`

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

Release builds are standalone PHAR executables:

```bash
php devdoctor app:build devdoctor --build-version=0.8.0 --no-interaction
php builds/devdoctor --version
```

## Commands

```text
env        Check dotenv files and DevDoctor env rules
ports      Check local development port conflicts
composer   Check Composer project health
git        Check Git repository hygiene
docker     Check Docker and Docker Compose project health
ci         Run CI-safe DevDoctor diagnostics
```

All public commands support the shared options:

```bash
--path=. --format=table --ci --strict
```

Laravel Zero already defines a global `--env` option, so DevDoctor exposes the env-file selector as `--env-file`:

```bash
php devdoctor env --env-file=.env.local --example=.env.example
```

## Examples

```bash
php devdoctor env
php devdoctor env --format=json --strict
php devdoctor ports --common
php devdoctor ports --port=3000 --port=5173
php devdoctor composer
php devdoctor git --require-clean --scan-large-files
php devdoctor docker --compose-file=docker-compose.yml
php devdoctor ci --modules=env,composer,git,docker --no-fail-on-warnings
```

`ports` uses platform-specific read-only providers: `lsof` on macOS/Linux, `ss` as a Linux fallback, and `netstat -ano` on Windows. If no supported provider is available, DevDoctor reports `DD_PORT_PROVIDER_UNAVAILABLE` instead of failing unexpectedly.

## Platform Support

DevDoctor targets Linux, macOS, and Windows:

| Capability | Linux | macOS | Windows |
| --- | --- | --- | --- |
| Command discovery | Native executable lookup | Native executable lookup | Native executable lookup |
| Port listeners | `lsof`, then `ss` | `lsof` | `netstat -ano` |
| Process suggestion | `kill -TERM <pid>` | `kill -TERM <pid>` | `taskkill /PID <pid>` |
| Composer, Git, Docker | Supported when their executables are installed | Supported when their executables are installed | Supported when their executables are installed |

Platform-specific commands are only suggested. DevDoctor never terminates a process automatically.

## Diagnostic Details

- Compose interpolation understands required references such as `${VAR}` and `${VAR?message}`.
- Compose references with defaults such as `${VAR:-default}` and `${VAR-default}` do not produce missing-variable warnings.
- Git reports `DD_GIT_BINARY_MISSING` when Git is unavailable instead of treating the path as a non-repository.
- Windows port diagnostics use `tasklist` when available to resolve a PID to a process name.
- Composer reports `DD_COMPOSER_LOCK_OUTDATED` when `composer.lock` is older than `composer.json`.
- Process execution uses argument arrays and supports project paths containing spaces.

## Table Output

```text
DevDoctor

Module     Status   Errors Warnings  Info
env        warning       0        1     0

Warnings
  [DD_ENV_MISSING_IN_ENV] .env.example:2 QUEUE_CONNECTION QUEUE_CONNECTION exists in .env.example but is missing in .env
```

## JSON Output

```json
{
    "tool": "devdoctor",
    "status": "passed",
    "summary": {
        "errors": 0,
        "warnings": 0,
        "info": 1
    },
    "modules": [
        {
            "name": "env",
            "status": "passed",
            "summary": {
                "errors": 0,
                "warnings": 0,
                "info": 1
            },
            "issues": [
                {
                    "code": "DD_ENV_READY",
                    "severity": "info",
                    "message": "Env diagnostics found no issues.",
                    "module": "env"
                }
            ]
        }
    ]
}
```

## CI

The CI aggregator runs `env`, `composer`, `git`, and `docker` by default. `ports` is excluded by default because port state depends on the runner machine.

```bash
php devdoctor ci --format=json
php devdoctor ci --modules=env,composer --exclude=composer
php devdoctor ci --no-fail-on-warnings
```

Unknown modules return exit code `3`. Selected modules are always included in JSON output.

The repository CI workflow runs tests on Linux, macOS, and Windows with PHP 8.5. It also builds and smoke-tests the PHAR executable.

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

- JSON output follows [schemas/devdoctor-output.schema.json](schemas/devdoctor-output.schema.json).
- Stable issue identifiers are listed in [docs/issue-codes.md](docs/issue-codes.md).
- Automation should match issue codes rather than human-readable messages.
- The version recorded in `composer.json` under `extra.devdoctor.version` matches the Git release tag.

## Safety

DevDoctor is read-only by default:

- It does not rewrite `.env`, Compose, Composer, Git, or project files.
- It does not run `composer install`, `composer update`, Composer scripts, or internet-dependent audits.
- It does not run `docker compose up`, `start`, `stop`, `rm`, or `prune`.
- Port diagnostics may suggest `kill -TERM <pid>`, but never execute it.
- Basic diagnostics do not require telemetry or internet access.

## Development

```bash
composer validate --strict
php devdoctor test
./vendor/bin/pint --test
php devdoctor app:build devdoctor --build-version=0.8.0 --no-interaction
php builds/devdoctor --version
```

## Roadmap

See [ROADMAP.md](ROADMAP.md) for the implementation roadmap and later distribution work.
