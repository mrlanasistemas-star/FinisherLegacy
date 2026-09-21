# Running the test suite locally

## `php artisan test` used to crash on this machine's default PHP config

`php artisan test` shells out to a child `pest`/`phpunit` process. On this
box the machine-wide default is `memory_limit=128M` with Xdebug's `develop`
mode always on, and `-d` flags passed to the *outer* `php artisan test`
command never reach that child process — nor does `xdebug.mode`, since
Xdebug reads it once at PHP startup and `ini_set()` can't change it
retroactively.

`memory_limit` is different: it's a normal runtime-adjustable directive, so
`phpunit.xml`'s `<php><ini name="memory_limit" value="512M"/></php>` block
*does* reach the child process (PHPUnit applies its own `<ini>` config via
`ini_set()` while bootstrapping, regardless of which command launched it).
That's now set explicitly, which is what actually fixed the crash this
section used to work around: Xdebug roughly doubles the memory a stack
trace costs, and one test intentionally exercises a 422 response while
`APP_DEBUG=true` (so Laravel renders the full Symfony debug HTML error
page, which is large) — together they used to exhaust the 128M default
while rendering that one page, which looked like the whole suite crashing
but wasn't a code bug. Plain `php artisan test` is fine now; no bypass
command needed for this specific issue.

If you still see an unrelated memory error locally, Xdebug's own overhead
is usually the next thing to try disabling for a run:

```sh
php -d xdebug.mode=off artisan test
```

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
