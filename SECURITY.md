# Security policy

## Reporting a vulnerability

Please **do not** open a public issue. Email the maintainer at anthony.naluz15@gmail.com with:

- a description and the affected version/commit,
- steps or a proof of concept to reproduce,
- your assessment of the impact.

You'll get an acknowledgement within a few days. Fixes are released with a CHANGELOG entry and credit (if you want it).

## Supported versions

Only the latest release receives security fixes while the framework is pre-1.x stable.

## Scope

In scope: the framework code in `src/`, the default configuration, and the starter app.
Out of scope: vulnerabilities in applications built on NaluzPHP, third-party packages, or misconfiguration that
contradicts `docs/security.md` (for example running with `APP_DEBUG=true` in production).
