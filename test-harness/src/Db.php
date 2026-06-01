<?php

declare(strict_types=1);

namespace EptTestHarness;

use PDO;
use PDOStatement;

/** Thin PDO wrapper. Only file in the harness that talks to MySQL. */
final class Db
{
    public PDO $pdo;

    public function __construct(Config $c)
    {
        $charset = $c->dbCharset === 'utf8' ? 'utf8mb4' : $c->dbCharset;
        $dsn = "mysql:host={$c->dbHost};dbname={$c->dbName};charset={$charset}";
        $this->pdo = new PDO($dsn, $c->dbUser, $c->dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function exec(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->exec($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function col(string $sql, array $params = []): array
    {
        return $this->exec($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->exec($sql, $params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->exec($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        $placeholders = array_map(fn ($c) => ':' . $c, $cols);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $this->exec($sql, $row);
        return (int) $this->pdo->lastInsertId();
    }

    public function tx(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
