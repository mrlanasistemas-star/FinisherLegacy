<?php

/**
 * Lightweight static guard against MySQL's 64-character identifier limit
 * (brief: "Fix migrations MySQL" incident, 2026-08-31) — SQLite has no such
 * limit, so a migration can pass every SQLite-backed test and still fail
 * the instant it runs against real MySQL. This is not a full SQL parser:
 * it only mirrors Laravel's own default-naming convention
 * (Illuminate\Database\Schema\Grammars\Grammar::createIndexName():
 * `strtolower("{table}_{col1}_{col2}_{type}")`) for the handful of
 * schema-builder calls that can generate a name that long — index(),
 * unique(), and foreignId(...)->constrained() — plus any explicit name
 * string, wherever it appears.
 */
test('no migration produces a MySQL index/unique/foreign-key identifier over 64 characters', function () {
    // Plain __DIR__-relative path, not database_path() — this file lives
    // in tests/Unit, which (see tests/Pest.php) isn't bound to Tests\TestCase
    // and has no booted app container; that's deliberate here, this check
    // is a pure static file scan and needs neither the app nor a database.
    $files = glob(__DIR__.'/../../database/migrations/*.php');

    $violations = [];

    foreach ($files as $path) {
        $src = file_get_contents($path);
        $file = basename($path);

        preg_match_all('/Schema::(?:create|table)\(\s*[\'"]([a-z_]+)[\'"]/', $src, $tableMatches, PREG_OFFSET_CAPTURE);

        $tableAtOffset = function (int $offset) use ($tableMatches): ?string {
            $found = null;
            foreach ($tableMatches[1] as $m) {
                if ($m[1] <= $offset) {
                    $found = $m[0];
                } else {
                    break;
                }
            }

            return $found;
        };

        $indexName = fn (string $table, array $columns, string $type): string => str_replace(
            ['-', '.'],
            '_',
            strtolower($table.'_'.implode('_', $columns).'_'.$type),
        );

        // ->index([...]) / ->unique([...]), with or without an explicit name.
        foreach (['index', 'unique'] as $type) {
            if (! preg_match_all(
                '/->'.$type.'\(\s*(\[[^\]]*\]|[\'"][a-zA-Z0-9_]+[\'"])\s*(?:,\s*[\'"]([a-zA-Z0-9_]+)[\'"])?\s*\)/',
                $src,
                $m,
                PREG_OFFSET_CAPTURE,
            )) {
                continue;
            }

            foreach ($m[0] as $i => $whole) {
                $explicit = $m[2][$i][0] ?? '';

                if ($explicit !== '') {
                    if (strlen($explicit) > 64) {
                        $violations[] = "{$file}: explicit {$type} name `{$explicit}` (".strlen($explicit).' chars)';
                    }

                    continue;
                }

                $table = $tableAtOffset($whole[1]);
                preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $m[1][$i][0], $cols);
                $columns = $cols[1];

                if (! $table || ! $columns) {
                    continue;
                }

                $name = $indexName($table, $columns, $type);

                if (strlen($name) > 64) {
                    $violations[] = "{$file}: {$type}(".implode(',', $columns).") on `{$table}` => `{$name}` (".strlen($name).' chars)';
                }
            }
        }

        // ->foreignId('col')->constrained() with no explicit ->name(), default FK name.
        if (preg_match_all(
            '/->foreignId\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\)((?:->\w+\([^)]*\))*)/',
            $src,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            foreach ($m[0] as $i => $whole) {
                if (! str_contains($m[2][$i][0], '->constrained')) {
                    continue;
                }

                $table = $tableAtOffset($whole[1]);
                $column = $m[1][$i][0];

                if (! $table) {
                    continue;
                }

                $name = $indexName($table, [$column], 'foreign');

                if (strlen($name) > 64) {
                    $violations[] = "{$file}: foreignId('{$column}')->constrained() on `{$table}` => `{$name}` (".strlen($name).' chars)';
                }
            }
        }
    }

    expect($violations)->toBe([]);
})->group('mysql-schema');
