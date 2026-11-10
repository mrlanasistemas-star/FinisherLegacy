<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Probes the media storage (the Synology SMB mount in production, see
 * docs/renovacion/synology-smb.md): the root exists and is writable, a
 * probe file can be written, read back and deleted, and there is enough
 * free space. Exits non-zero on any failure so cron/monitoring notices.
 *
 * The local root is checked BEFORE the disk is instantiated: Flysystem's
 * local adapter creates a missing root, which would hide a missing mount.
 */
class StorageHealth extends Command
{
    protected $signature = 'storage:health
        {--disk=* : Disks to probe (default: config filesystems.health.disks, or the media + temp disks)}
        {--json : Print the result as JSON}';

    protected $description = 'Check that the media disks (Synology mount) are mounted, writable and have free space';

    public function handle(): int
    {
        $results = array_map(fn (string $disk) => $this->probe($disk), $this->disks());
        $healthy = collect($results)->every(fn (array $r) => $r['ok']);

        if ($this->option('json')) {
            $this->line((string) json_encode(['ok' => $healthy, 'disks' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Disco', 'Driver', 'Estado', 'Libre (MB)', 'Detalle'],
                array_map(fn (array $r) => [
                    $r['disk'], $r['driver'], $r['ok'] ? 'OK' : 'FALLA',
                    $r['free_mb'] ?? '—', $r['error'] ?? '',
                ], $results),
            );
        }

        if (! $healthy) {
            Log::error('storage.health_failed', ['disks' => array_values(array_filter($results, fn (array $r) => ! $r['ok']))]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function disks(): array
    {
        $disks = array_values(array_filter((array) $this->option('disk'), 'is_string'));

        if ($disks === []) {
            /** @var list<string> $disks */
            $disks = (array) config('filesystems.health.disks', []);
        }

        if ($disks === []) {
            $disks = [(string) config('finisher.media_disk', 'local'), (string) config('finisher.media.tmp_disk', 'media_tmp')];
        }

        return array_values(array_unique($disks));
    }

    /**
     * @return array{disk: string, driver: string, ok: bool, free_mb: int|null, total_mb: int|null, error: string|null}
     */
    private function probe(string $disk): array
    {
        $config = config("filesystems.disks.{$disk}");
        $result = ['disk' => $disk, 'driver' => '?', 'ok' => false, 'free_mb' => null, 'total_mb' => null, 'error' => null];

        if (! is_array($config)) {
            return [...$result, 'error' => 'Disco no configurado'];
        }

        $driver = (string) ($config['driver'] ?? '?');
        $result['driver'] = $driver;

        try {
            if ($driver === 'local') {
                $root = rtrim((string) ($config['root'] ?? ''), '/\\');

                if ($root === '' || ! is_dir($root)) {
                    return [...$result, 'error' => 'La raíz no existe (¿montaje SMB caído o carpeta sin crear?)'];
                }

                if ((bool) config('filesystems.health.require_mount_point') && $disk === config('finisher.media_disk') && ! $this->isMountPoint($root)) {
                    return [...$result, 'error' => 'La raíz no es un punto de montaje'];
                }

                $sentinel = (string) config('filesystems.health.media_sentinel', '');
                if ($sentinel !== '' && $disk === config('finisher.media_disk') && ! is_file($root.DIRECTORY_SEPARATOR.$sentinel)) {
                    return [...$result, 'error' => "Falta el archivo centinela [{$sentinel}] (¿el NAS no está montado?)"];
                }

                if (! is_writable($root)) {
                    return [...$result, 'error' => 'La raíz es de solo lectura'];
                }

                $free = @disk_free_space($root);
                $total = @disk_total_space($root);
                $result['free_mb'] = is_float($free) ? (int) floor($free / 1048576) : null;
                $result['total_mb'] = is_float($total) ? (int) floor($total / 1048576) : null;
            }

            $this->writeReadDelete($disk);
        } catch (Throwable $e) {
            return [...$result, 'error' => 'Prueba de escritura fallida: '.Str::limit($e->getMessage(), 200)];
        }

        $minFree = (int) config('filesystems.health.min_free_mb', 0);
        if ($result['free_mb'] !== null && $minFree > 0 && $result['free_mb'] < $minFree) {
            return [...$result, 'error' => "Espacio libre bajo ({$result['free_mb']} MB < {$minFree} MB)"];
        }

        return [...$result, 'ok' => true];
    }

    private function writeReadDelete(string $disk): void
    {
        $storage = Storage::disk($disk);
        $path = '.health/'.Str::uuid()->toString().'.probe';
        $payload = Str::random(64);

        try {
            if ($storage->put($path, $payload) !== true) {
                throw new \RuntimeException('no se pudo escribir el archivo de prueba');
            }

            if ($storage->get($path) !== $payload) {
                throw new \RuntimeException('el contenido leído no coincide');
            }
        } finally {
            $storage->delete($path);
        }

        if ($storage->exists($path)) {
            throw new \RuntimeException('no se pudo borrar el archivo de prueba');
        }
    }

    /** Linux: a mount point lives on a different device than its parent. */
    private function isMountPoint(string $root): bool
    {
        $self = @stat($root);
        $parent = @stat(dirname($root));

        if ($self === false || $parent === false) {
            return false;
        }

        return $self['dev'] !== $parent['dev'] || $self['ino'] === $parent['ino'];
    }
}
