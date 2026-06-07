# Changelog

All notable DevDoctor releases are listed here from newest to oldest.

This changelog is based on the Git tag history and the project roadmap. DevDoctor follows a release discipline where each feature group receives its own version, documentation update, commit, annotated tag, and push to `origin`.

## v1.36.0 - 2026-06-07

- Added a manual `Update Homebrew Tap` workflow for publishing the Homebrew formula for an existing release.
- The workflow resolves `devdoctor.phar.sha256` from the selected GitHub Release and reuses the tap update script.
- Improved the static command reference with a command subtree, copyable command islands, and stricter filtering.

## v1.35.0 - 2026-06-06

- Added `--baseline-report` for CI baseline runs.
- Added `DD_CI_BASELINE_REPORT` with active, suppressed, and resolved fingerprint counts.
- Documented baseline reports in README and docs.

## v1.34.0 - 2026-06-06

- Added CI policy profiles: `local`, `ci`, `strict-ci`, and `security`.
- Added `DD_CI_UNKNOWN_PROFILE` for invalid profile requests.
- Documented profile behavior in README and CI docs.

## v1.33.0 - 2026-06-06

- Added `devdoctor commands` for terminal-native command discovery.
- Added `--module` filtering to `devdoctor explain`.
- Included issue-code module metadata in explain JSON output.

## v1.32.0 - 2026-06-06

- Added machine-readable documentation metadata in `docs/manifest.json`.
- Added a machine-readable command catalog in `docs/commands.json`.
- Documented the command metadata catalog from the static command reference.

## v1.31.0 - 2026-06-06

- Added active documentation navigation and breadcrumbs.
- Added copy buttons for command snippets in the static documentation.
- Expanded scenario guides for Python, Kubernetes/Helm, Terraform/IaC, Symfony, and mobile projects.

## v1.30.0 - 2026-06-06

- Added `scripts/build-docs.php` to generate human-readable issue code documentation from `schemas/v1/issue-codes.json`.
- Added issue code filtering, copy buttons, and active category highlighting.
- Added practical documentation scenarios for Laravel, frontend, monorepo, and CI baseline rollout.

## v1.29.1 - 2026-06-06

- Redesigned the issue codes documentation with a left-side category panel.
- Improved desktop and mobile navigation for the human-readable issue code catalog.

## v1.29.0 - 2026-06-06

- Added utility commands: `inventory`, `explain`, `policy`, and `support-bundle`.
- Added redacted support context output for troubleshooting without writing project files.
- Documented the new utility commands in README and docs.

## v1.28.0 - 2026-06-06

- Added monorepo diagnostics for Nx, Turbo, Lerna, pnpm workspaces, Rush, Bazel, Pants, workspace lockfiles, and risky root package scripts.
- Integrated monorepo evidence with preset detection and automatic module selection.

## v1.27.0 - 2026-06-06

- Added native mobile diagnostics for Android and iOS project markers.
- Added checks for Gradle wrappers, Android debug flags, CocoaPods lockfiles, and iOS debug entitlements.

## v1.26.0 - 2026-06-06

- Added Flutter and Dart diagnostics.
- Added checks for `pubspec.yaml`, `pubspec.lock`, Dart SDK constraints, local path or Git dependency sources, and Flutter platform markers.

## v1.25.0 - 2026-06-06

- Added Kubernetes and Helm diagnostics.
- Added checks for manifest hygiene, Helm locks, mutable images, privileged containers, hostPath mounts, NodePort exposure, and values secret hygiene.

## v1.24.1 - 2026-06-06

- Fixed Windows CI expectations for default CI command exit codes.
- Kept the release documentation and version metadata aligned after the hotfix.

## v1.24.0 - 2026-06-05

- Added Terraform, OpenTofu, and Terragrunt diagnostics.
- Added checks for provider locks, broad provider constraints, unpinned remote modules, and secret-like IaC values.

## v1.23.0 - 2026-06-05

- Added Ruby and Rails diagnostics.
- Added checks for `Gemfile`, `Gemfile.lock`, Ruby versions, Rails credentials, database credential hygiene, and risky gem sources.

## v1.22.0 - 2026-06-05

- Added deeper Symfony diagnostics.
- Added checks for `.env` and `.env.local` hygiene, `APP_SECRET`, production debug mode, cache/log directories, Symfony Flex recipe drift, and risky Composer scripts.

## v1.21.0 - 2026-06-05

- Added generic web diagnostics.
- Added checks for static entry files, obvious asset references, public config exposure, web server config hints, and local port declaration conflicts.

## v1.20.0 - 2026-06-05

- Added C and C++ diagnostics.
- Added checks for CMake, Make, Meson, Autotools, vcpkg, Conan, compile command metadata, in-source build artifacts, compiler flags, generator assumptions, and shell portability risks.

## v1.19.0 - 2026-06-05

- Added .NET diagnostics.
- Added checks for solutions, projects, SDK pinning, target framework drift, NuGet configuration, and restore lock mode.

## v1.18.0 - 2026-06-05

- Added Java and JVM diagnostics.
- Added checks for Maven, Gradle, Ant, wrappers, Java version declarations, risky build scripts, and Spring production debug flags.

## v1.17.0 - 2026-06-05

- Added Rust Cargo diagnostics.
- Added checks for manifests, lockfiles, workspaces, toolchains, dependency sources, and release profile settings.

## v1.16.0 - 2026-06-05

- Added Go module diagnostics.
- Added checks for `go.mod`, `go.sum`, `go.work`, local `replace` directives, toolchain declarations, and vendor metadata.

## v1.15.0 - 2026-06-05

- Added Python diagnostics.
- Added static checks for pip, Poetry, Pipenv, uv, and Conda manifests without running installers.

## v1.14.0 - 2026-06-05

- Expanded Node diagnostics into frontend and package manager diagnostics.
- Added checks for Vite, Next.js, Nuxt, Astro, generic frontend projects, npm, Yarn, pnpm, and Bun.

## v1.13.0 - 2026-06-05

- Added multi-stack preset foundation.
- Extended project detection for frontend, Python, Go, Rust, JVM, C/C++, .NET, generic web, and deeper Symfony planning.

## v1.12.0 - 2026-06-05

- Added `doctor` as an alias for the broad `health` diagnostics workflow.

## v1.11.0 - 2026-06-05

- Added dependency diagnostics aggregation for Composer and Node projects.

## v1.10.0 - 2026-06-05

- Added global diagnostic output controls for severity filtering, summaries, and hidden hints.

## v1.9.0 - 2026-06-05

- Added HTTP URL diagnostics.
- Added static validation for environment-derived and explicit HTTP targets without making network requests.

## v1.8.0 - 2026-06-05

- Added queue configuration diagnostics.
- Added checks for common queue drivers and production risks around synchronous queue execution.

## v1.7.0 - 2026-06-05

- Added cache diagnostics.
- Added checks for framework/tool caches, cache size thresholds, permissions, and Laravel cache artifacts.

## v1.6.0 - 2026-06-05

- Added database configuration diagnostics.
- Added optional read-only PDO connection checks.

## v1.5.1 - 2026-06-05

- Centralized issue hints in the `IssueCode` enum.
- Replaced string issue codes and module names with `IssueCode` and `ModuleName` enums across diagnostics.
- Simplified issue suggestion handling with typed value objects.

## v1.5.0 - 2026-06-05

- Added the `health` diagnostics aggregator for broad local project checks.
- Added opt-in port checks for the health workflow.

## v1.4.0 - 2026-06-05

- Added security posture diagnostics.
- Added checks for env example secrets, hard-coded secret patterns, risky scripts, Docker privileged mode, Docker socket mounts, and `.env` ignore gaps.

## v1.3.0 - 2026-06-05

- Added Laravel application diagnostics.
- Added checks for `.env`, `APP_KEY`, production debug mode, `APP_URL`, runtime directories, and config cache state.

## v1.2.0 - 2026-06-05

- Added Node.js diagnostics.
- Added checks for `package.json`, lockfiles, package manager alignment, `node_modules`, runtime version files, and risky scripts.

## v1.1.0 - 2026-06-05

- Added PHP runtime diagnostics.
- Added checks for Composer platform requirements, extensions, memory limits, loaded `php.ini`, and Xdebug in CI.

## v1.0.0 - 2026-06-04

- Published the first stable DevDoctor release.
- Stabilized JSON schema v1 and the issue code catalog.
- Completed public Composer package identity, documentation site, release verification docs, and CI examples for GitHub Actions, GitLab CI, and Bitbucket Pipelines.

## v0.15.0 - 2026-06-04

- Hardened Composer package identity for `rtcoder/devdoctor`.
- Migrated the public namespace from `App\\` to `DevDoctor\\`.
- Kept the `devdoctor` binary and Laravel Zero application behavior intact.

## v0.14.0 - 2026-06-04

- Added the static documentation site under `docs/`.
- Added pinned CI examples for GitHub Actions, GitLab CI, and Bitbucket Pipelines.

## v0.13.0 - 2026-06-04

- Added versioned JSON schema paths.
- Added a machine-readable issue code catalog.
- Documented compatibility expectations for the public contract candidate.

## v0.12.1 - 2026-06-04

- Added Homebrew tap automation.
- Hardened cross-platform release dependencies and documentation for Homebrew installation.

## v0.12.0 - 2026-06-04

- Added release automation for tagged releases.
- Added signed PHAR release workflow support and a composite GitHub Action.
- Added release verification documentation for checksums and signatures.

## v0.11.1 - 2026-06-04

- Added CI baseline support.
- Added suppression metadata for baseline issues in table, JSON, and SARIF output.

## v0.11.0 - 2026-06-04

- Added SARIF output support for diagnostic commands and `ci`.
- Added SARIF rule mapping, severity mapping, locations, fingerprints, hints, and fix descriptions.

## v0.10.1 - 2026-06-04

- Added the `init` config wizard for safely generating `devdoctor.yml`.
- Added dry-run and non-interactive safeguards so configuration is not written accidentally.

## v0.10.0 - 2026-06-04

- Added actionable hints and read-only fix suggestions.
- Added `FixSuggestion` and extended issue output in table and JSON formats.

## v0.9.0 - 2026-06-04

- Added project preset detection for Laravel, Symfony, Node, Vite, Next.js, and Docker Compose evidence.

## v0.8.0 - 2026-06-04

- Completed the first broad documentation and release tooling pass.
- Expanded README with installation, command overview, output examples, CI usage, `devdoctor.yml`, exit codes, safety guarantees, and roadmap links.

## v0.3.0 - 2026-06-04

- Improved diagnostic correctness across early modules.
- Strengthened read-only module behavior and output consistency.

## v0.2.0 - 2026-06-04

- Added cross-platform command support.
- Added platform-aware port provider behavior, including macOS/Linux providers and graceful Windows handling.
- Migrated the project requirement to PHP 8.5.

## v0.1.1 - 2026-05-27

- Added the project roadmap.
- Converted core status concepts to enums, including exit codes, severity, and module status.
- Established the first typed core diagnostic model.

## v0.1.0 - 2026-05-27

- Initialized the Laravel Zero DevDoctor CLI.
- Added the first public binary scaffold and initial project structure.
