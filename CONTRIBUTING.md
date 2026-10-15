# Contributing

Thanks for contributing to this Pharaonic package.

## Branches

Each supported PHP minor has its own release line: `8.0.x`, `8.1.x`, and so on. A line only uses syntax and APIs available on its PHP version. `develop` follows the newest line.

Open fixes against the oldest affected line; maintainers port them to the other lines.

## Before coding

- Target the correct supported runtime branch.
- Keep changes focused.
- Prefer native PHP capabilities over new abstractions/dependencies.
- Preserve public compatibility unless the change explicitly targets an approved breaking line.

## Local quality gate

Install dependencies and run:

```bash
composer check
```

The package expects formatting, static analysis, tests, Composer validation, and security audit to pass.

## Pull requests

Keep the PR focused and explain what changed and why. Public API additions should be deliberate and small.

Security vulnerabilities must follow `SECURITY.md`, not a public PR or issue.
