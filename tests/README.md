# Tests

Static analysis of the REST controllers — **no WordPress install, nothing
redefined**. PHPStan resolves the code against the official
`php-stubs/wordpress-stubs` package.

## Run

```bash
cd tests/phpstan
composer install
vendor/bin/phpstan analyse --memory-limit=1G
```

## What it checks

- The forked controllers extend the real core controllers
  (`WP_REST_Posts_Controller` / `WP_REST_Terms_Controller`).
- Every `$this->...()` call resolves (no undefined methods — e.g. it would flag
  a missing `check_update_permission`).
- Undefined functions/classes and type errors in the controller logic.

## Note on the namespace filter

`AnyPostsRestController` / `AnyTermsRestController` are faithful copies of core's
controllers (which live in the global namespace) placed under `namespace helper`.
Their copied PHPDoc references unqualified `WP_*` types, which resolve to
`helper\WP_*`. We deliberately do **not** edit the controllers to satisfy the
analyser, so that pure name-resolution noise is filtered in `phpstan.neon.dist`
(`ignoreErrors: '#helper\\WP_#'`) — tooling config, not source changes.

The handful of remaining findings (e.g. `header()` passed an `int`, core-style
dead branches) are faithful to WordPress core and intentionally left as-is.
