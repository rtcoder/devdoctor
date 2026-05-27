# DevDoctor

Developer diagnostics for humans.

DevDoctor is a public CLI tool for catching common local, repository, environment, Docker, Composer, Git, and CI problems before they turn into manual debugging sessions.

## Status

This repository is now initialized with Laravel Zero 12.x and PHP 8.3+.

The public command surface is visible:

```bash
php devdoctor env
php devdoctor ports
php devdoctor docker
php devdoctor composer
php devdoctor git
php devdoctor ci
```

`env` currently performs the first real file-presence checks for `.env` and `.env.example`. The other modules are explicit early stubs with stable `*_NOT_IMPLEMENTED` issue codes.

## Development

```bash
composer install
php devdoctor list
php devdoctor env --format=json
php devdoctor test
```

Laravel Zero already defines a global `--env` option, so DevDoctor's env-file selector is currently exposed as:

```bash
php devdoctor env --env-file=.env.local --example=.env.example
```

## Output

All public commands support table and JSON output:

```bash
php devdoctor env --format=table
php devdoctor env --format=json
```

JSON output follows the shared module shape:

```json
{
  "tool": "devdoctor",
  "status": "failed",
  "summary": {
    "errors": 1,
    "warnings": 1,
    "info": 0
  },
  "modules": []
}
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

## Safety

DevDoctor is read-only by default. It must not rewrite `.env`, execute destructive commands, send telemetry, or require internet access for basic diagnostics.
