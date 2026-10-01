# Contributing

1. `composer install && vendor/bin/phpunit` must pass before and after your change.
2. Add tests for new behaviour — especially for anything security-relevant (include the attack, not just the happy path).
3. Follow PSR-12, use `declare(strict_types=1)`, and type everything. Keep dependencies minimal.
4. Report vulnerabilities privately (see `docs/security.md`).
