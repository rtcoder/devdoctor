# DevDoctor Issue Codes

Issue codes are stable identifiers intended for CI parsing, baselines, and integrations. Messages may improve over time; automation should match codes.

The human-readable catalog is available at [`docs/issue-codes.html`](issue-codes.html). The machine-readable v1 catalog is available at [`schemas/v1/issue-codes.json`](../schemas/v1/issue-codes.json).

## Cache

- `DD_CACHE_DIRECTORY_LARGE` - A supported cache directory exceeds the configured size threshold. Introduced in `1.7.0`; status `active`.
- `DD_CACHE_DIRECTORY_NOT_WRITABLE` - A supported cache directory is not writable. Introduced in `1.7.0`; status `active`.
- `DD_CACHE_LARAVEL_ARTIFACT` - A Laravel cache artifact exists. Introduced in `1.7.0`; status `active`.
- `DD_CACHE_NOT_DETECTED` - No supported cache directories or artifacts were detected. Introduced in `1.7.0`; status `active`.
- `DD_CACHE_READY` - Cache diagnostics found no actionable issues. Introduced in `1.7.0`; status `active`.

## Ci

- `DD_CI_BASELINE_EXISTS` - The baseline output file already exists. Introduced in `0.11.1`; status `active`.
- `DD_CI_BASELINE_INVALID` - The baseline file is invalid. Introduced in `0.11.1`; status `active`.
- `DD_CI_BASELINE_MISSING` - The requested baseline file is missing. Introduced in `0.11.1`; status `active`.
- `DD_CI_BASELINE_REPORT` - A baseline report summarizes active, suppressed, and resolved fingerprints. Introduced in `1.35.0`; status `active`.
- `DD_CI_UNKNOWN_MODULE` - An unknown CI module was requested. Introduced in `0.8.0`; status `active`.
- `DD_CI_UNKNOWN_PROFILE` - An unknown CI policy profile was requested. Introduced in `1.34.0`; status `active`.

## Composer

- `DD_COMPOSER_BINARY_MISSING` - The Composer binary is unavailable. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_EXTENSION_MISSING` - A required PHP extension is missing. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_JSON_INVALID` - composer.json is invalid. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_LOCK_MISSING` - composer.lock is missing. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_LOCK_OUTDATED` - composer.lock may be older than composer.json. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_NOT_PROJECT` - The path is not a Composer project. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_PACKAGE_ABANDONED` - An installed package is marked abandoned. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_PHP_VERSION_MISMATCH` - The current PHP version does not satisfy composer.json. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_READY` - Composer diagnostics found no actionable issues. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_SCRIPT_RISKY` - A Composer install or update script may be risky. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_VALIDATE_FAILED` - Composer validation failed. Introduced in `0.8.0`; status `active`.
- `DD_COMPOSER_VENDOR_MISSING` - The vendor directory is missing. Introduced in `0.8.0`; status `active`.

## Cpp

- `DD_CPP_COMPILE_COMMANDS_MISSING` - A CMake project does not expose compile_commands.json. Introduced in `1.20.0`; status `active`.
- `DD_CPP_GENERATOR_ASSUMPTION` - A CMake file appears to hard-code a generator assumption. Introduced in `1.20.0`; status `active`.
- `DD_CPP_IN_SOURCE_BUILD` - CMake cache appears in the source root. Introduced in `1.20.0`; status `active`.
- `DD_CPP_MIXED_DEPENDENCY_MANAGERS` - Multiple C/C++ dependency managers were detected. Introduced in `1.20.0`; status `active`.
- `DD_CPP_NOT_PROJECT` - The path is not a C/C++ project. Introduced in `1.20.0`; status `active`.
- `DD_CPP_READY` - C/C++ diagnostics found no actionable issues. Introduced in `1.20.0`; status `active`.
- `DD_CPP_RISKY_COMPILER_FLAGS` - A C/C++ build file contains compiler flags that can hide important diagnostics. Introduced in `1.20.0`; status `active`.
- `DD_CPP_SHELL_ASSUMPTION` - A C/C++ build file contains a Unix shell assumption. Introduced in `1.20.0`; status `active`.

## Db

- `DD_DB_CONNECT_FAILED` - The optional database connection check failed. Introduced in `1.6.0`; status `active`.
- `DD_DB_CONNECT_OK` - The optional database connection check succeeded. Introduced in `1.6.0`; status `active`.
- `DD_DB_CONNECTION_MISSING` - The database connection name is missing. Introduced in `1.6.0`; status `active`.
- `DD_DB_CONNECTION_UNKNOWN` - The database connection name is not recognized. Introduced in `1.6.0`; status `active`.
- `DD_DB_DRIVER_MISSING` - The matching PDO database driver is unavailable. Introduced in `1.6.0`; status `active`.
- `DD_DB_PORT_INVALID` - The configured database port is invalid. Introduced in `1.6.0`; status `active`.
- `DD_DB_READY` - Database diagnostics found no actionable issues. Introduced in `1.6.0`; status `active`.
- `DD_DB_REQUIRED_KEY_MISSING` - A required database environment key is missing. Introduced in `1.6.0`; status `active`.
- `DD_DB_SQLITE_FILE_MISSING` - The configured SQLite database file is missing. Introduced in `1.6.0`; status `active`.

## Docker

- `DD_DOCKER_BINARY_MISSING` - The Docker binary is unavailable. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_COMPOSE_CONFIG_INVALID` - Docker Compose config validation failed. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_COMPOSE_FILE_MISSING` - The requested Compose file is missing. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_COMPOSE_INVALID` - The Compose file cannot be parsed. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_CONTAINER_UNHEALTHY` - A container is unhealthy or restarting. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_DAEMON_UNAVAILABLE` - The Docker daemon is unavailable. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_ENV_REFERENCE_MISSING` - A Compose environment reference is unresolved. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_HOST_PORT_CONFLICT` - A Compose host port is already in use. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_NO_COMPOSE_PROJECT` - No Compose project was detected. Introduced in `0.8.0`; status `active`.
- `DD_DOCKER_READY` - Docker diagnostics found no actionable issues. Introduced in `0.8.0`; status `active`.

## Dotnet

- `DD_DOTNET_LOCK_MISSING` - .NET restore lock mode is enabled but packages.lock.json is missing. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_MIXED_SOLUTION_STATE` - Multiple .NET solution files were detected. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_NOT_PROJECT` - The path is not a .NET project. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_READY` - .NET diagnostics found no actionable issues. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_RISKY_NUGET_SOURCE` - NuGet config uses an insecure HTTP package source. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_SDK_NOT_PINNED` - .NET SDK version is not pinned with global.json. Introduced in `1.19.0`; status `active`.
- `DD_DOTNET_TARGET_FRAMEWORK_MISMATCH` - .NET target frameworks span multiple major platform versions. Introduced in `1.19.0`; status `active`.

## Env

- `DD_ENV_DUPLICATE_KEY` - An environment key is declared more than once. Introduced in `0.8.0`; status `active`.
- `DD_ENV_EMPTY_VALUE` - An environment key has an empty value. Introduced in `0.8.0`; status `active`.
- `DD_ENV_EXAMPLE_MISSING` - The environment example file is missing. Introduced in `0.8.0`; status `active`.
- `DD_ENV_FILE_MISSING` - The environment file is missing. Introduced in `0.8.0`; status `active`.
- `DD_ENV_FORBIDDEN_WHEN_PRESENT` - A forbidden conditional environment value is present. Introduced in `0.8.0`; status `active`.
- `DD_ENV_INVALID_ALLOWED_VALUE` - An environment value is not in the allowed set. Introduced in `0.8.0`; status `active`.
- `DD_ENV_INVALID_CONFIG` - The DevDoctor environment configuration is invalid. Introduced in `0.8.0`; status `active`.
- `DD_ENV_INVALID_KEY_NAME` - An environment key name is invalid. Introduced in `0.8.0`; status `active`.
- `DD_ENV_INVALID_TYPE` - An environment value does not match its configured type. Introduced in `0.8.0`; status `active`.
- `DD_ENV_MISSING_IN_ENV` - A key from the example file is missing in the environment file. Introduced in `0.8.0`; status `active`.
- `DD_ENV_MISSING_IN_EXAMPLE` - A key from the environment file is missing in the example file. Introduced in `0.8.0`; status `active`.
- `DD_ENV_PROD_DEBUG` - Debug mode is enabled for a production environment. Introduced in `0.8.0`; status `active`.
- `DD_ENV_READY` - Environment diagnostics found no actionable issues. Introduced in `0.8.0`; status `active`.
- `DD_ENV_REQUIRED_MISSING` - A required environment key is missing. Introduced in `0.8.0`; status `active`.
- `DD_ENV_REQUIRED_WHEN_MISSING` - A conditionally required environment key is missing. Introduced in `0.8.0`; status `active`.
- `DD_ENV_SECRET_IN_EXAMPLE` - A likely secret appears in an example environment file. Introduced in `0.8.0`; status `active`.

## Flutter

- `DD_FLUTTER_DEPENDENCY_SOURCE` - pubspec.yaml uses a local path or Git dependency source. Introduced in `1.26.0`; status `active`.
- `DD_FLUTTER_LOCK_MISSING` - pubspec.yaml declares dependencies but pubspec.lock is missing. Introduced in `1.26.0`; status `active`.
- `DD_FLUTTER_NOT_PROJECT` - The path is not a Flutter or Dart project. Introduced in `1.26.0`; status `active`.
- `DD_FLUTTER_PLATFORM_MARKERS_MISSING` - Flutter project metadata exists but platform markers are missing. Introduced in `1.26.0`; status `active`.
- `DD_FLUTTER_READY` - Flutter and Dart diagnostics found no actionable issues. Introduced in `1.26.0`; status `active`.
- `DD_FLUTTER_SDK_CONSTRAINT_MISSING` - pubspec.yaml does not declare a Dart SDK constraint. Introduced in `1.26.0`; status `active`.

## Frontend

- `DD_FRONTEND_BUILD_SCRIPT_MISSING` - A frontend project has no package.json build script. Introduced in `1.14.0`; status `active`.
- `DD_FRONTEND_NOT_PROJECT` - The path is not a frontend project. Introduced in `1.14.0`; status `active`.
- `DD_FRONTEND_PRESET_DETECTED` - A frontend project preset was detected. Introduced in `1.14.0`; status `active`.
- `DD_FRONTEND_READY` - Frontend diagnostics found no actionable issues. Introduced in `1.14.0`; status `active`.

## Git

- `DD_GIT_AHEAD_BEHIND` - The branch is ahead of or behind its upstream. Introduced in `0.8.0`; status `active`.
- `DD_GIT_BINARY_MISSING` - The Git binary is unavailable. Introduced in `0.8.0`; status `active`.
- `DD_GIT_CONFLICTS` - The repository contains unresolved conflicts. Introduced in `0.8.0`; status `active`.
- `DD_GIT_DETACHED_HEAD` - The repository is in detached HEAD state. Introduced in `0.8.0`; status `active`.
- `DD_GIT_DIRTY_WORKTREE` - The worktree contains changes. Introduced in `0.8.0`; status `active`.
- `DD_GIT_ENV_NOT_IGNORED` - An environment file is not ignored by Git. Introduced in `0.8.0`; status `active`.
- `DD_GIT_LARGE_UNTRACKED_FILE` - A large untracked file was found. Introduced in `0.8.0`; status `active`.
- `DD_GIT_NOT_REPOSITORY` - The path is not a Git repository. Introduced in `0.8.0`; status `active`.
- `DD_GIT_NO_UPSTREAM` - The current branch has no upstream. Introduced in `0.8.0`; status `active`.
- `DD_GIT_READY` - Git diagnostics found no actionable issues. Introduced in `0.8.0`; status `active`.
- `DD_GIT_TRACKED_SENSITIVE_FILE` - A sensitive file is tracked by Git. Introduced in `0.8.0`; status `active`.
- `DD_GIT_UNTRACKED_SENSITIVE_FILE` - A sensitive file is untracked. Introduced in `0.8.0`; status `active`.

## Go

- `DD_GO_MODULE_PATH_INVALID` - The Go module path looks invalid or is missing. Introduced in `1.16.0`; status `active`.
- `DD_GO_NOT_PROJECT` - No Go module or workspace was detected. Introduced in `1.16.0`; status `active`.
- `DD_GO_READY` - Go diagnostics found no actionable issues. Introduced in `1.16.0`; status `active`.
- `DD_GO_REPLACE_DIRECTIVE` - A Go replace directive points to a local path. Introduced in `1.16.0`; status `active`.
- `DD_GO_SUM_MISSING` - go.mod declares dependencies but go.sum is missing. Introduced in `1.16.0`; status `active`.
- `DD_GO_TOOLCHAIN_DECLARED` - A Go toolchain directive is declared. Introduced in `1.16.0`; status `active`.
- `DD_GO_VENDOR_PRESENT` - Go vendor directory metadata is present. Introduced in `1.16.0`; status `active`.
- `DD_GO_WORKSPACE_MODULE_MISSING` - go.work references a directory without go.mod. Introduced in `1.16.0`; status `active`.

## Health

- `DD_HEALTH_UNKNOWN_MODULE` - An unknown health module was requested. Introduced in `1.5.0`; status `active`.

## Http

- `DD_HTTP_INSECURE_PRODUCTION_URL` - A production HTTP URL does not use HTTPS. Introduced in `1.9.0`; status `active`.
- `DD_HTTP_LOCALHOST_PRODUCTION_URL` - A production HTTP URL points at a local host. Introduced in `1.9.0`; status `active`.
- `DD_HTTP_READY` - HTTP URL diagnostics found no actionable issues. Introduced in `1.9.0`; status `active`.
- `DD_HTTP_URL_INVALID` - An HTTP URL is invalid. Introduced in `1.9.0`; status `active`.
- `DD_HTTP_URL_MISSING` - No HTTP URL targets were found. Introduced in `1.9.0`; status `active`.

## Iac

- `DD_IAC_BACKEND_SECRET` - Terraform backend or provider config appears to contain a literal secret. Introduced in `1.24.0`; status `active`.
- `DD_IAC_LOCK_MISSING` - Provider requirements are declared but no Terraform/OpenTofu lockfile was found. Introduced in `1.24.0`; status `active`.
- `DD_IAC_NOT_PROJECT` - The path is not a Terraform, OpenTofu, or Terragrunt project. Introduced in `1.24.0`; status `active`.
- `DD_IAC_READY` - IaC diagnostics found no actionable issues. Introduced in `1.24.0`; status `active`.
- `DD_IAC_REMOTE_MODULE_UNPINNED` - A Terraform or Terragrunt remote module source appears to be unpinned. Introduced in `1.24.0`; status `active`.
- `DD_IAC_SECRET_DEFAULT` - A Terraform variable default appears to contain a secret-like value. Introduced in `1.24.0`; status `active`.
- `DD_IAC_WILDCARD_PROVIDER_VERSION` - A Terraform provider version constraint is too broad. Introduced in `1.24.0`; status `active`.

## Java

- `DD_JAVA_MIXED_BUILD_SYSTEMS` - Multiple Java build systems were detected. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_NOT_PROJECT` - The path is not a Java/JVM project. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_READY` - Java diagnostics found no actionable issues. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_RISKY_BUILD_SCRIPT` - A Java build file contains shell execution that should be reviewed. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_SPRING_PROD_DEBUG` - A Spring production profile appears to enable debug logging. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_VERSION_MISMATCH` - Java version declarations disagree across build files. Introduced in `1.18.0`; status `active`.
- `DD_JAVA_WRAPPER_MISSING` - A Maven or Gradle wrapper is missing. Introduced in `1.18.0`; status `active`.

## Kube

- `DD_KUBE_HELM_LOCK_MISSING` - A Helm chart declares dependencies but Chart.lock is missing. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_HOST_PATH_MOUNT` - A Kubernetes manifest mounts a hostPath volume. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_MUTABLE_IMAGE_TAG` - A Kubernetes manifest uses a mutable or implicit image tag. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_NODEPORT_SERVICE` - A Kubernetes service exposes a NodePort. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_NOT_PROJECT` - The path is not a Kubernetes or Helm project. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_PRIVILEGED_CONTAINER` - A Kubernetes manifest enables a privileged container. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_READY` - Kubernetes and Helm diagnostics found no actionable issues. Introduced in `1.25.0`; status `active`.
- `DD_KUBE_VALUES_SECRET` - A Helm values file appears to contain a literal secret. Introduced in `1.25.0`; status `active`.

## Laravel

- `DD_LARAVEL_APP_KEY_MISSING` - APP_KEY is missing or empty. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_APP_URL_DEFAULT` - APP_URL is missing or still set to a default localhost value. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_CONFIG_CACHED` - Laravel config cache exists and may need rebuilding after environment changes. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_DIRECTORY_MISSING` - An expected Laravel runtime directory is missing. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_DIRECTORY_NOT_WRITABLE` - An expected Laravel runtime directory is not writable. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_ENV_MISSING` - The Laravel .env file is missing. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_NOT_PROJECT` - The path is not a Laravel project. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_PROD_DEBUG` - APP_DEBUG is enabled while APP_ENV is production. Introduced in `1.3.0`; status `active`.
- `DD_LARAVEL_READY` - Laravel diagnostics found no actionable issues. Introduced in `1.3.0`; status `active`.

## Mcp

- `DD_MCP_COMMAND_RISKY` - An MCP stdio command appears to use shell evaluation or remote script execution. Introduced in `1.43.0`; status `active`.
- `DD_MCP_CONFIG_INVALID` - An MCP configuration file contains invalid JSON. Introduced in `1.42.0`; status `active`.
- `DD_MCP_DOCKER_IMAGE_MUTABLE` - An MCP stdio server uses a Docker image without an immutable or stable tag. Introduced in `1.45.0`; status `active`.
- `DD_MCP_ENV_REFERENCE_MISSING` - An MCP server references an environment key not declared in local env files. Introduced in `1.43.0`; status `active`.
- `DD_MCP_ENV_SECRET_INLINE` - An MCP server configuration appears to contain an inline secret. Introduced in `1.43.0`; status `active`.
- `DD_MCP_NOT_CONFIGURED` - No MCP configuration file was detected. Introduced in `1.42.0`; status `active`.
- `DD_MCP_PACKAGE_UNPINNED` - An MCP stdio server uses a package runner without an explicit package version. Introduced in `1.45.0`; status `active`.
- `DD_MCP_READY` - MCP diagnostics found no actionable issues. Introduced in `1.42.0`; status `active`.
- `DD_MCP_REMOTE_URL_INSECURE` - An MCP remote server uses an insecure HTTP URL outside localhost. Introduced in `1.43.0`; status `active`.
- `DD_MCP_REMOTE_URL_MISSING` - An MCP remote server is missing a url. Introduced in `1.42.0`; status `active`.
- `DD_MCP_SERVER_INVALID` - An MCP server entry has an unsupported shape. Introduced in `1.42.0`; status `active`.
- `DD_MCP_SERVERS_MISSING` - An MCP configuration file does not define mcpServers or servers. Introduced in `1.42.0`; status `active`.
- `DD_MCP_STDIO_COMMAND_MISSING` - An MCP stdio server is missing a command. Introduced in `1.42.0`; status `active`.
- `DD_MCP_TRANSPORT_UNKNOWN` - An MCP server uses an unsupported transport. Introduced in `1.42.0`; status `active`.

## Mobile

- `DD_MOBILE_ANDROID_DEBUGGABLE` - An Android manifest enables debuggable mode. Introduced in `1.27.0`; status `active`.
- `DD_MOBILE_ANDROID_WRAPPER_MISSING` - An Android project does not include a Gradle wrapper. Introduced in `1.27.0`; status `active`.
- `DD_MOBILE_IOS_DEBUG_ENTITLEMENT` - iOS entitlements appear to allow debug task access. Introduced in `1.27.0`; status `active`.
- `DD_MOBILE_IOS_POD_LOCK_MISSING` - Podfile exists but Podfile.lock is missing. Introduced in `1.27.0`; status `active`.
- `DD_MOBILE_NOT_PROJECT` - The path is not a native Android or iOS project. Introduced in `1.27.0`; status `active`.
- `DD_MOBILE_READY` - Mobile diagnostics found no actionable issues. Introduced in `1.27.0`; status `active`.

## Monorepo

- `DD_MONOREPO_LOCK_MISSING` - Workspace metadata exists but no JavaScript package manager lockfile was found. Introduced in `1.28.0`; status `active`.
- `DD_MONOREPO_MIXED_TOOLS` - Multiple monorepo orchestration tools were detected. Introduced in `1.28.0`; status `active`.
- `DD_MONOREPO_NOT_PROJECT` - The path is not a supported monorepo project. Introduced in `1.28.0`; status `active`.
- `DD_MONOREPO_READY` - Monorepo diagnostics found no actionable issues. Introduced in `1.28.0`; status `active`.
- `DD_MONOREPO_RISKY_ROOT_SCRIPT` - A root package script downloads or executes remote shell content. Introduced in `1.28.0`; status `active`.

## Node

- `DD_NODE_BINARY_MISSING` - The Node.js binary is unavailable. Introduced in `1.2.0`; status `active`.
- `DD_NODE_LOCK_MISSING` - package.json declares dependencies but no supported lockfile is present. Introduced in `1.2.0`; status `active`.
- `DD_NODE_LOCK_OUTDATED` - A Node.js package manager lockfile is older than package.json. Introduced in `1.14.0`; status `active`.
- `DD_NODE_MODULES_MISSING` - The node_modules directory is missing. Introduced in `1.2.0`; status `active`.
- `DD_NODE_MULTIPLE_LOCKFILES` - Multiple Node.js package manager lockfiles are present. Introduced in `1.2.0`; status `active`.
- `DD_NODE_NOT_PROJECT` - The path is not a Node.js project. Introduced in `1.2.0`; status `active`.
- `DD_NODE_PACKAGE_JSON_INVALID` - package.json is invalid. Introduced in `1.2.0`; status `active`.
- `DD_NODE_PACKAGE_MANAGER_MISMATCH` - The declared package manager does not match committed lockfiles. Introduced in `1.2.0`; status `active`.
- `DD_NODE_READY` - Node.js diagnostics found no actionable issues. Introduced in `1.2.0`; status `active`.
- `DD_NODE_SCRIPT_RISKY` - A package.json script may be risky. Introduced in `1.2.0`; status `active`.
- `DD_NODE_VERSION_FILE_CONFLICT` - Node.js version requirements disagree across project files. Introduced in `1.2.0`; status `active`.
- `DD_NODE_VERSION_MISMATCH` - The active Node.js version does not satisfy the project requirement. Introduced in `1.2.0`; status `active`.

## Php

- `DD_PHP_BINARY_MISSING` - The PHP binary is unavailable. Introduced in `1.1.0`; status `active`.
- `DD_PHP_COMPOSER_JSON_INVALID` - composer.json cannot be parsed for PHP diagnostics. Introduced in `1.1.0`; status `active`.
- `DD_PHP_EXTENSION_MISSING` - A required PHP extension is missing from the active runtime. Introduced in `1.1.0`; status `active`.
- `DD_PHP_INI_MISSING` - The active PHP runtime has no loaded php.ini file. Introduced in `1.1.0`; status `active`.
- `DD_PHP_MEMORY_LIMIT_LOW` - The active PHP memory_limit is below the configured threshold. Introduced in `1.1.0`; status `active`.
- `DD_PHP_READY` - PHP diagnostics found no actionable issues. Introduced in `1.1.0`; status `active`.
- `DD_PHP_VERSION_MISMATCH` - The active PHP version does not satisfy composer.json. Introduced in `1.1.0`; status `active`.
- `DD_PHP_XDEBUG_ENABLED_IN_CI` - Xdebug is enabled while running in CI mode. Introduced in `1.1.0`; status `active`.

## Ports

- `DD_PORTS_READY` - Port diagnostics found no actionable issues. Introduced in `0.8.0`; status `active`.
- `DD_PORT_INVALID_PORT` - A requested port is invalid. Introduced in `0.8.0`; status `active`.
- `DD_PORT_IN_USE` - A requested port is in use. Introduced in `0.8.0`; status `active`.
- `DD_PORT_MULTIPLE_LISTENERS` - A port has multiple listeners. Introduced in `0.8.0`; status `active`.
- `DD_PORT_PRIVILEGED` - A requested port is privileged. Introduced in `0.8.0`; status `active`.
- `DD_PORT_PROVIDER_UNAVAILABLE` - No supported port inspection provider is available. Introduced in `0.8.0`; status `active`.

## Presets

- `DD_PRESET_DETECTED` - A supported project preset was detected. Introduced in `0.9.0`; status `active`.
- `DD_PRESET_NONE_DETECTED` - No supported project preset was detected. Introduced in `0.9.0`; status `active`.

## Python

- `DD_PYTHON_LOCK_MISSING` - A Python dependency manager lockfile is missing. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_MIXED_MANAGERS` - Multiple Python dependency managers were detected. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_NOT_PROJECT` - The path is not a Python project. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_READY` - Python diagnostics found no actionable issues. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_SUSPICIOUS_SOURCE` - A Python dependency source should be reviewed before install. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_VERSION_CONFLICT` - Python version constraints disagree across project files. Introduced in `1.15.0`; status `active`.
- `DD_PYTHON_VENV_MISSING` - No local Python virtual environment marker was detected. Introduced in `1.15.0`; status `active`.

## Queue

- `DD_QUEUE_CONNECTION_MISSING` - Queue connection configuration is missing. Introduced in `1.8.0`; status `active`.
- `DD_QUEUE_CONNECTION_UNKNOWN` - Queue connection configuration uses an unknown driver. Introduced in `1.8.0`; status `active`.
- `DD_QUEUE_DATABASE_REQUIRES_DB` - Database queue configuration is missing database settings. Introduced in `1.8.0`; status `active`.
- `DD_QUEUE_READY` - Queue diagnostics found no actionable issues. Introduced in `1.8.0`; status `active`.
- `DD_QUEUE_REQUIRED_KEY_MISSING` - A required queue environment key is missing. Introduced in `1.8.0`; status `active`.
- `DD_QUEUE_SYNC_IN_PRODUCTION` - The sync queue driver is configured for production. Introduced in `1.8.0`; status `active`.

## Ruby

- `DD_RUBY_DATABASE_SECRET` - Rails database config appears to contain a literal credential. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_LOCK_MISSING` - A Ruby application Gemfile is missing Gemfile.lock. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_NOT_PROJECT` - The path is not a Ruby or Rails project. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_RAILS_MASTER_KEY_MISSING` - Rails encrypted credentials are present without a local master key marker. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_READY` - Ruby diagnostics found no actionable issues. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_RISKY_GEM_SOURCE` - A Ruby dependency source should be reviewed before install. Introduced in `1.23.0`; status `active`.
- `DD_RUBY_VERSION_CONFLICT` - Ruby version declarations disagree across project files. Introduced in `1.23.0`; status `active`.

## Rust

- `DD_RUST_EDITION_MISSING` - Cargo package does not declare an edition. Introduced in `1.17.0`; status `active`.
- `DD_RUST_GIT_DEPENDENCY` - A Rust dependency uses a Git source. Introduced in `1.17.0`; status `active`.
- `DD_RUST_LOCK_MISSING` - A Rust application appears to be missing Cargo.lock. Introduced in `1.17.0`; status `active`.
- `DD_RUST_NOT_PROJECT` - The path is not a Rust Cargo project. Introduced in `1.17.0`; status `active`.
- `DD_RUST_PATH_DEPENDENCY` - A Rust dependency uses a local path source. Introduced in `1.17.0`; status `active`.
- `DD_RUST_READY` - Rust diagnostics found no actionable issues. Introduced in `1.17.0`; status `active`.
- `DD_RUST_RELEASE_PROFILE_DEBUG` - Rust release profile keeps debug-like settings. Introduced in `1.17.0`; status `active`.
- `DD_RUST_TOOLCHAIN_DECLARED` - A Rust toolchain file is declared. Introduced in `1.17.0`; status `active`.
- `DD_RUST_WORKSPACE_MEMBER_MISSING` - A Cargo workspace member is missing Cargo.toml. Introduced in `1.17.0`; status `active`.

## Security

- `DD_SECURITY_DOCKER_PRIVILEGED` - A Compose service enables privileged mode. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_DOCKER_SOCKET_MOUNT` - A Compose service mounts the Docker socket. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_ENV_NOT_IGNORED` - .gitignore does not explicitly ignore .env files. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_READY` - Security diagnostics found no actionable issues. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_RISKY_COMPOSER_SCRIPT` - A Composer script may execute risky shell code. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_RISKY_PACKAGE_SCRIPT` - A package.json script may execute risky shell code. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_SECRET_IN_EXAMPLE` - A likely secret appears in an example environment file. Introduced in `1.4.0`; status `active`.
- `DD_SECURITY_SECRET_PATTERN` - A file contains a likely hard-coded secret pattern. Introduced in `1.4.0`; status `active`.

## Symfony

- `DD_SYMFONY_ENV_MISSING` - The Symfony environment file is missing. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_NOT_PROJECT` - The path is not a Symfony project. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_PROD_DEBUG` - Symfony debug mode is enabled for a production environment. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_READY` - Symfony diagnostics found no actionable issues. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_RECIPE_DRIFT` - Symfony Flex recipe metadata appears to be missing expected config files. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_RISKY_COMPOSER_SCRIPT` - A Symfony Composer script may execute risky shell code. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_RUNTIME_DIR_MISSING` - An expected Symfony runtime directory is missing. Introduced in `1.22.0`; status `active`.
- `DD_SYMFONY_SECRET_MISSING` - APP_SECRET is missing, empty, or still uses a default placeholder. Introduced in `1.22.0`; status `active`.

## Web

- `DD_WEB_ASSET_REFERENCE_MISSING` - A web entry file references a local asset that is missing. Introduced in `1.21.0`; status `active`.
- `DD_WEB_BUILD_OUTPUT_MISSING` - A web project has a build script but no common build output entry file. Introduced in `1.21.0`; status `active`.
- `DD_WEB_INSECURE_DEFAULT_CONFIG` - A web server config contains an insecure default that should be reviewed. Introduced in `1.21.0`; status `active`.
- `DD_WEB_NOT_PROJECT` - The path is not a generic web project. Introduced in `1.21.0`; status `active`.
- `DD_WEB_PORT_CONFIG_CONFLICT` - Web port configuration disagrees across project files. Introduced in `1.21.0`; status `active`.
- `DD_WEB_PUBLIC_SECRET` - A public web config file appears to contain secret-like keys. Introduced in `1.21.0`; status `active`.
- `DD_WEB_READY` - Generic web diagnostics found no actionable issues. Introduced in `1.21.0`; status `active`.
