# DevDoctor Roadmap

This roadmap describes the implementation path from the Laravel Zero scaffold to the first complete DevDoctor release and later public distribution work.

Current state:

- Laravel Zero 12.x is initialized.
- The `devdoctor` binary exists.
- Public commands are implemented: `env`, `ports`, `php`, `node`, `laravel`, `security`, `docker`, `composer`, `git`, `health`, `doctor`, `ci`, and `presets`.
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
- `v1.1.0`: PHP runtime diagnostics for composer platform requirements, extensions, memory limits, php.ini loading, and Xdebug in CI. Complete.
- `v1.2.0`: Node.js diagnostics for package.json, lockfiles, package manager alignment, node_modules, runtime version files, and risky scripts. Complete.
- `v1.3.0`: Laravel diagnostics for .env, APP_KEY, production debug mode, APP_URL, runtime directories, and config cache state. Complete.
- `v1.4.0`: Security diagnostics for env example secrets, hard-coded secret patterns, risky scripts, Docker privileged mode, Docker socket mounts, and .env ignore gaps. Complete.
- `v1.5.0`: Health aggregator for broad local project diagnostics with opt-in port checks. Complete.
- `v1.6.0`: Database configuration diagnostics with optional read-only PDO connection checks. Complete.
- `v1.7.0`: Cache diagnostics for framework/tool caches, size thresholds, permissions, and Laravel artifacts. Complete.
- `v1.8.0`: Queue configuration diagnostics for common drivers and production sync risks. Complete.
- `v1.9.0`: HTTP URL diagnostics for env and explicit targets without network requests. Complete.
- `v1.10.0`: Global output shaping options for severity filters, summaries, and hidden hints. Complete.
- `v1.11.0`: Dependency diagnostics aggregator for Composer and Node projects. Complete.
- `v1.12.0`: Doctor alias for the broad health diagnostics workflow. Complete.
- `v1.13.0`: Multi-stack preset foundation for frontend, Python, Go, Rust, JVM, C/C++, .NET, generic web, and deeper Symfony planning. Complete.
- `v1.14.0`: Frontend and Node package manager diagnostics for Vite, Next.js, Nuxt, Astro, npm, Yarn, pnpm, and Bun. Complete.
- `v1.15.0`: Python diagnostics for pip, Poetry, Pipenv, uv, and Conda manifests. Complete.
- `v1.16.0`: Go module diagnostics for `go.mod`, `go.sum`, `go.work`, local `replace` directives, toolchain declarations, and vendor metadata. Complete.
- `v1.17.0`: Rust Cargo diagnostics for manifests, lockfiles, workspaces, toolchains, dependency sources, and release profile settings. Complete.
- `v1.18.0`: Java/JVM diagnostics for Maven, Gradle, Ant, wrappers, Java version declarations, risky build scripts, and Spring production debug flags. Complete.
- `v1.19.0`: .NET diagnostics for solutions, projects, SDK pinning, target framework drift, NuGet config, and restore lock mode. Complete.
- `v1.20.0`: C/C++ diagnostics for CMake, Make, Meson, Autotools, vcpkg, Conan, compile command metadata, in-source build artifacts, compiler flags, generator assumptions, and shell portability risks. Complete.
- `v1.21.0`: Generic web diagnostics for static entry files, obvious asset references, public config exposure, web server config hints, and local port declaration conflicts. Complete.
- `v1.22.0`: Symfony diagnostics for `.env`/`.env.local`, `APP_SECRET`, production debug mode, runtime cache/log directories, Symfony Flex recipe drift, and risky Composer scripts. Complete.
- `v1.23.0`: Ruby/Rails diagnostics for `Gemfile`, `Gemfile.lock`, Ruby versions, Rails credentials, database credential hygiene, and risky gem sources. Complete.
- `v1.24.0`: Terraform/IaC diagnostics for Terraform, OpenTofu, and Terragrunt manifests, provider locks, broad provider constraints, unpinned remote modules, and secret-like IaC values. Complete.
- `v1.25.0`: Kubernetes/Helm diagnostics for manifest hygiene, Helm locks, mutable images, privileged containers, hostPath mounts, NodePort exposure, and values secret hygiene. Complete.
- `v1.26.0`: Flutter/Dart diagnostics for pubspec lockfiles, Dart SDK constraints, local path/Git dependencies, and Flutter platform markers. Complete.
- `v1.27.0`: Native mobile diagnostics for Android/iOS markers, Gradle wrappers, Android debug flags, CocoaPods lockfiles, and iOS debug entitlements. Complete.
- `v1.28.0`: Monorepo diagnostics for Nx, Turbo, Lerna, pnpm workspaces, Rush, Bazel, Pants, workspace lockfiles, and risky root scripts. Complete.
- `v1.29.0`: Utility commands for inventory, issue-code explanation, policy display, and redacted support bundles. Complete.
- `v1.29.1`: Documentation layout polish with a left-side issue code category panel. Complete.
- `v1.30.0`: Documentation automation, issue code search/copy controls, and scenario guides. Complete.
- `v1.31.0`: Documentation navigation polish with active nav, breadcrumbs, copy command buttons, and expanded scenario guides. Complete.
- `v1.32.0`: Machine-readable documentation metadata and command catalog for tooling and docs UI integrations. Complete.
- `v1.33.0`: CLI discoverability with `commands` and `explain --module` for terminal-native command and issue-code lookup. Complete.
- `v1.34.0`: CI policy profiles for local, default CI, strict CI, and security-focused runs. Complete.
- `v1.35.0`: Baseline reports for active, suppressed, and resolved fingerprints. Complete.
- `v1.36.0`: Manual Homebrew tap update workflow and command documentation filtering polish. Complete.
- `v1.37.0`: Interactive update notices and `devdoctor self-update` command. Complete.
- `v1.38.0`: GitHub Marketplace metadata polish for the composite Action. Complete.
- `v1.38.1`: Unique GitHub Marketplace Action name for publishing. Complete.
- `v1.38.2`: GitHub Marketplace Action branding color update. Complete.
- `v1.39.0`: Repository `bump-version` helper for release version pin updates. Complete.
- `v1.40.0`: Public `devdoctor version` command with table and JSON output. Complete.
- `v1.41.0`: Homebrew formula installs standalone binaries instead of the PHAR. Complete.
- `v1.41.1`: Windows-safe release artifact test for the `bump-version` helper. Complete.
