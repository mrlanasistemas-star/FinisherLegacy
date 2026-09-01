# Running the test suite locally

## `php artisan test` can crash on this machine's default PHP config

`php artisan test` shells out to a child `pest`/`phpunit` process that reads
this machine's **default** `php.ini` — not whatever `-d` flags you passed to
the outer `php artisan test` command, and not `phpunit.xml`'s `<ini>` block
either. On this box that default is `memory_limit=128M` with Xdebug's
`develop` mode always on.

Xdebug roughly doubles the memory a stack trace costs. Combine that with one
test in the suite that intentionally exercises a 422 response while
`APP_DEBUG=true` (so Laravel renders the full Symfony debug HTML error page,
which is large), and the child process can hit `Allowed memory size of
134217728 bytes exhausted` while rendering that one page — which looks like
the whole suite crashed, but isn't a code bug.

**Don't "fix" this by lowering test coverage or changing app behavior.**
Run Pest directly instead, bypassing the `artisan test` wrapper so your `-d`
flags actually apply to the process running the tests:

```sh
php -d memory_limit=1G -d xdebug.mode=off vendor/bin/pest --colors=never
```

This is a local `php.ini` quirk, not something to change in the app or in
CI (CI doesn't have Xdebug enabled, so it doesn't hit this).

## Running the suite against real MySQL

The default suite (`composer test` / `php artisan test`) runs against SQLite
via `phpunit.xml` — fast, but SQLite has no identifier-length limit and no
native JSON column type, so it silently accepts things MySQL rejects (see
the `mysql-schema-test` job in `.github/workflows/tests.yml`, and its
comment, for the incident that job exists to catch).

To reproduce that job locally, point the suite at a real, disposable MySQL
database via environment overrides (`phpunit.xml`'s `<env>` block only
applies when nothing else set the variable first, so real env vars win):

```sh
# one-time
mysql -h127.0.0.1 -uroot -e "CREATE DATABASE IF NOT EXISTS finisher_test;"

export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
       DB_DATABASE=finisher_test DB_USERNAME=root DB_PASSWORD=
php artisan migrate:fresh --seed --force
php -d memory_limit=1G -d xdebug.mode=off vendor/bin/pest --colors=never
```

If a test passes on SQLite but fails only here, look first at whether it's
comparing a JSON/array column with `->toBe()` (strict, order-sensitive)
instead of `->toEqual()` — MySQL's native JSON column type re-serializes
object key order on write, SQLite's text-based storage doesn't, so a
key-order-sensitive assertion is comparing storage engine behavior, not
your code. `PlateStudioTest.php`, `ProductionDeviceStatusTest.php`, and
`ProductionJobClaimTest.php` all carry a comment about this — copy the
pattern rather than re-discovering it.
