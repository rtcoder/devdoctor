# DevDoctor Roadmap

This roadmap describes the implementation path from the Laravel Zero scaffold to the first complete DevDoctor release and later public distribution work.

Current state:

- Laravel Zero 12.x is initialized.
- The `devdoctor` binary exists.
- Public commands are implemented: `env`, `ports`, `docker`, `composer`, `git`, `ci`, and `presets`.
- Core diagnostics, rendering, redaction, path normalization, config loading, and process execution are in place.
- `env`, `ports`, `composer`, `git`, `docker`, and `ci` have focused tests and read-only behavior.
- Cross-platform CI, PHAR smoke tests, a JSON schema, a stable issue code catalog, and project preset detection are included.

## Milestone 0.1.1 - Foundation Cleanup

Goal: stabilize the base after scaffolding before module behavior grows.

- Standardize shared command options: `--path`, `--format`, `--ci`, and `--strict`.
- Document the Laravel Zero global `--env` collision and keep `--env-file` as the practical env-file selector.
- Finish the core diagnostics model:
  - `Issue`
  - `IssueCollection`
  - `ModuleResult`
  - `ExitCode`
  - deterministic issue sorting
  - relative path display.
- Add a central `Redactor`.
- Add a central `ProcessRunner`.
- Expand core tests for redaction, table rendering, path normalization, and process runner success/failure/timeout behavior.

## Milestone 0.2.0 - `devdoctor env`

Goal: ship the first fully useful diagnostics module.

- Implement a dotenv parser that supports:
  - simple values
  - quoted values
  - empty values
  - comments
  - `export KEY=value`
  - line numbers
  - duplicate key detection.
- Compare `.env` and `.env.example`.
- Implement built-in checks:
  - missing files
  - missing keys
  - duplicate keys
  - invalid key names
  - empty values
  - `APP_ENV=production` with `APP_DEBUG=true`
  - `NODE_ENV=production` with `DEBUG=true`.
- Implement secret scanning for `.env.example`, `.env.dist`, and `.env.sample`.
- Implement `devdoctor.yml` loading and validation.
- Implement configured rules:
  - `required`
  - `required_when`
  - `forbidden_when`
  - `type`
  - `allowed`
  - `ignore`.
- Add full env parser, analyzer, and command tests.
- Completion criteria: `php devdoctor env`, `--format=json`, `--ci`, and `--strict` follow the exit code contract.

## Milestone 0.3.0 - `devdoctor ports`

Goal: diagnose local port conflicts safely.

- Implement ports DTOs:
  - `PortsOptions`
  - `PortUsage`
  - `ProcessInfo`.
- Implement a provider abstraction:
  - macOS/Linux via `lsof`
  - Linux fallback via `ss`
  - graceful Windows fallback.
- Support options:
  - `--port`
  - `--ports`
  - `--common`
  - `--include-docker`.
- Add documented common development ports.
- Detect:
  - port in use
  - invalid port
  - privileged port
  - multiple listeners
  - provider unavailable.
- Show safe suggestions such as `kill -TERM <pid>` without executing them.
- Test with fake providers instead of depending on the local machine state.

## Milestone 0.4.0 - `devdoctor composer`

Goal: provide read-only Composer project diagnostics.

- Detect missing `composer.json` as info with exit code `0`.
- Validate JSON locally.
- When Composer exists, run safe diagnostics through `ProcessRunner`.
- Check:
  - missing or outdated `composer.lock`
  - missing `vendor`
  - PHP version mismatch
  - missing `ext-*` requirements
  - abandoned packages from `vendor/composer/installed.json`
  - risky scripts in install/update events.
- Never run `composer install`, `composer update`, Composer scripts, or internet-dependent audit checks by default.
- Add fixture-based Composer tests.

## Milestone 0.5.0 - `devdoctor git`

Goal: diagnose repository hygiene and secret safety.

- Add a Git command wrapper through `ProcessRunner`.
- Detect:
  - not a repository
  - dirty worktree
  - conflicts
  - detached HEAD
  - no upstream
  - ahead/behind remote
  - tracked sensitive files
  - untracked sensitive files
  - `.env` not ignored
  - large untracked files.
- Support options:
  - `--require-clean`
  - `--require-upstream`
  - `--scan-sensitive`
  - `--scan-large-files`
  - `--large-file-threshold=10M`.
- Prefer fake Git runners for tests.

## Milestone 0.6.0 - `devdoctor docker`

Goal: diagnose Docker and Compose without requiring Docker for projects that do not use it.

- Detect Compose files:
  - `docker-compose.yml`
  - `docker-compose.yaml`
  - `compose.yml`
  - `compose.yaml`.
- Support `--compose-file`, `--no-daemon`, and `--no-containers`.
- Detect:
  - Docker missing
  - daemon unavailable
  - invalid Compose config
  - missing `${ENV}` references
  - Compose host port conflicts
  - unhealthy or restarting containers.
- Never run `docker compose up`, `start`, `stop`, `rm`, or `prune`.
- Test with a fake process runner and Compose fixtures.

## Milestone 0.7.0 - `devdoctor ci`

Goal: provide a deterministic CI aggregator.

- Default modules: `env`, `composer`, `git`, and `docker`.
- Exclude `ports` by default.
- Support:
  - `--modules`
  - `--exclude`
  - `--fail-on-warnings`
  - `--no-fail-on-warnings`
  - `--config`.
- Never run prompts or interactive checks.
- Merge selected module results and return the highest-severity exit code.
- Ensure JSON output contains every selected module.
- Add tests for module selection, unknown modules, exclusions, warnings, and errors.

## Milestone 0.8.0 - Documentation & First Complete Release

Goal: reach the first complete version that satisfies the acceptance criteria.

- Expand README with:
  - installation
  - command overview
  - one example per command
  - sample table output
  - JSON output example
  - CI example
  - `devdoctor.yml` example
  - exit codes
  - safety guarantees
  - roadmap link.
- Clean up issue code names and output consistency.
- Run the full test suite.
- Prepare a release tag once acceptance criteria are met.

Release status: complete.

## Later Roadmap

After the first complete version:

- `v0.9`: framework presets for Laravel, Symfony, Node, Vite, Next.js, and Docker Compose. Complete.
- `v0.10.0`: actionable hints and read-only fix suggestions. Complete.
- `v0.10.1`: interactive config wizard. Complete.
- `v0.11.0`: SARIF output. Complete.
- `v0.11.1`: baseline file support. Complete.
- `v0.12.0`: release automation, signed PHAR builds, and GitHub Action. Complete.
- `v0.12.1`: Homebrew tap and cross-platform CI dependency hardening. Complete.
- `v0.13.0`: versioned JSON schema and machine-readable issue code catalog. Complete.
- `v0.14.0`: static documentation site and pinned CI examples. Complete.
- `v0.15.0`: Composer package identity hardening and DevDoctor namespace migration. Complete.
- `v1.0.0`: stable JSON schema, stable issue code catalog, documentation site, public Composer package identity, and CI examples for GitHub Actions, GitLab CI, and Bitbucket Pipelines. Complete.
