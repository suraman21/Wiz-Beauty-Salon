<?php
declare(strict_types=1);

namespace Wiz\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO wrapper.
 *
 * Only prepared statements are exposed — there is no method that accepts a
 * query with values already interpolated into it. Identifiers (table and column
 * names) are the one place a string reaches SQL, and they must come from code,
 * never from request input; `quoteIdentifier()` exists for that and rejects
 * anything that is not a plain identifier.
 */
final class Database
{
    private ?PDO $pdo = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($this->config['host'] ?? 'localhost'),
            (int) ($this->config['port'] ?? 3306),
            (string) ($this->config['name'] ?? ''),
            (string) ($this->config['charset'] ?? 'utf8mb4'),
        );

        $this->pdo = new PDO(
            $dsn,
            (string) ($this->config['user'] ?? ''),
            (string) ($this->config['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );

        return $this->pdo;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        // Native prepares send everything as a string, and a PHP `false` becomes
        // '' — which strict mode rejects for an integer column. Booleans are the
        // only type PHP will not coerce on its own, so they are mapped here once.
        $params = array_map(
            static fn (mixed $value): mixed => is_bool($value) ? (int) $value : $value,
            $params,
        );

        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $e) {
            // Never surface SQL or credentials in the message shown to a user.
            throw new PDOException(
                'Database error: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn (string $c): string => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders),
        );

        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        $this->run($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);

            // MySQL/MariaDB ends the transaction implicitly when DDL runs.
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }

        return '`' . $identifier . '`';
    }
}
