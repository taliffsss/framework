# Contributing

Thanks for helping! Please read the [code of conduct](CODE_OF_CONDUCT.md) first.

1. `composer install && vendor/bin/phpunit` must pass before and after your change
   (Redis tests run when `redis-server` is installed and are skipped otherwise).
2. Add tests for new behaviour — especially for anything security-relevant (include the attack, not just the happy path).
3. Follow PSR-12, use `declare(strict_types=1)`, type everything, and keep dependencies minimal.
4. Update `CHANGELOG.md`, and the documentation in the [`naluz-framework-docs`](https://github.com/taliffsss/naluz-framework-docs) repository (the single source of truth for docs).
5. Report vulnerabilities privately ([SECURITY.md](SECURITY.md)).

Building a package for NaluzPHP? See [Packages and extending](https://taliffsss.github.io/naluz-framework-docs/advanced/packages/).
