<?php
declare(strict_types=1);

namespace Wiz\Core;

use Wiz\Database\Migration;

/**
 * Migration runner.
 *
 * Applied migrations are recorded by filename in the `migrations` table, so the
 * runner is idempotent: running it twice is a no-op. A migration is recorded
 * only after it returns without throwing. DDL is auto-committed by MariaDB, so
 * a migration that fails midway leaves its earlier statements in place — the
 * runner reports which file failed rather than pretending otherwise.
 */
final class Migrations
{
    private const TABLE = 'migrations';

    public function __construct(
        private readonly Database $db,
        private readonly string $path,
    ) {
    }

    /**
     * Create the bookkeeping table if it is missing. Safe to call repeatedly.
     */
    public function ensureTable(): void
    {
        $this->db->execute(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                migration  VARCHAR(190) NOT NULL,
                batch      INT UNSIGNED NOT NULL DEFAULT 1,
                applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    /**
     * Migration filenames that have already run, oldest first.
     *
     * @return list<string>
     */
    public function applied(): array
    {
        $this->ensureTable();

        $rows = $this->db->all('SELECT migration FROM ' . self::TABLE . ' ORDER BY migration ASC');

        return array_values(array_map(
            static fn (array $row): string => (string) $row['migration'],
            $rows,
        ));
    }

    /**
     * Migration files on disk, sorted by name. The timestamp prefix is what
     * defines the order, so the filesystem cannot reorder them.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $files = glob($this->path . '/*.php');

        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);

        return array_values(array_map(static fn (string $file): string => basename($file), $files));
    }

    /**
     * Files present on disk but not yet recorded.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $applied = $this->applied();

        return array_values(array_filter(
            $this->available(),
            static fn (string $file): bool => !in_array($file, $applied, true),
        ));
    }

    /**
     * Apply every pending migration in one batch.
     *
     * @param callable(string): void $onApply
     * @return list<string> the filenames that were applied
     */
    public function migrate(?callable $onApply = null): array
    {
        $pending = $this->pending();

        if ($pending === []) {
            return [];
        }

        $this->ensureTable();

        $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM ' . self::TABLE) + 1;
        $done = [];

        foreach ($pending as $file) {
            $migration = $this->load($file);

            // Deliberately not wrapped in a transaction: MariaDB commits DDL
            // implicitly, so a transaction here would end before the bookkeeping
            // insert and make commit() fail. The record is written only after the
            // migration itself has returned without throwing.
            $migration->up($this->db);

            $this->db->execute(
                'INSERT INTO ' . self::TABLE . ' (migration, batch) VALUES (:migration, :batch)',
                [':migration' => $file, ':batch' => $batch],
            );

            $done[] = $file;

            if ($onApply !== null) {
                $onApply($file);
            }
        }

        return $done;
    }

    /**
     * Undo the most recent batch. Used when a release has to be backed out.
     *
     * @return list<string> the filenames that were rolled back
     */
    public function rollbackLastBatch(): array
    {
        $this->ensureTable();

        $batch = $this->db->scalar('SELECT MAX(batch) FROM ' . self::TABLE);

        if ($batch === null) {
            return [];
        }

        $rows = $this->db->all(
            'SELECT migration FROM ' . self::TABLE . ' WHERE batch = :batch ORDER BY migration DESC',
            [':batch' => (int) $batch],
        );

        $undone = [];

        foreach ($rows as $row) {
            $file = (string) $row['migration'];
            $migration = $this->load($file);

            $migration->down($this->db);

            $this->db->execute(
                'DELETE FROM ' . self::TABLE . ' WHERE migration = :migration',
                [':migration' => $file],
            );

            $undone[] = $file;
        }

        return $undone;
    }

    /**
     * @return list<array{file: string, status: string, batch: int|null, applied_at: string|null}>
     */
    public function status(): array
    {
        $this->ensureTable();

        $applied = [];

        foreach ($this->db->all('SELECT migration, batch, applied_at FROM ' . self::TABLE) as $row) {
            $applied[(string) $row['migration']] = $row;
        }

        $rows = [];

        foreach ($this->available() as $file) {
            $record = $applied[$file] ?? null;

            $rows[] = [
                'file' => $file,
                'status' => $record === null ? 'pending' : 'applied',
                'batch' => $record === null ? null : (int) $record['batch'],
                'applied_at' => $record === null ? null : (string) $record['applied_at'],
            ];
        }

        return $rows;
    }

    private function load(string $file): Migration
    {
        $path = $this->path . '/' . $file;

        if (!is_file($path)) {
            throw new \RuntimeException('Migration file missing: ' . $file);
        }

        /** @var mixed $migration */
        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException('Migration does not implement Migration: ' . $file);
        }

        return $migration;
    }
}
