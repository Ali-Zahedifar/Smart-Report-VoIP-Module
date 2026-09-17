<?php

declare(strict_types=1);

namespace SmartReport\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?Database $main = null;

    private PDO $pdo;

    private function __construct(array $cfg)
    {
        $host = $cfg['host'] ?? 'localhost';
        $port = $cfg['port'] ?? 3306;
        $name = $cfg['name'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, (int) $port, $name, $charset);
        $user = $cfg['user'] ?? '';
        $pass = $cfg['pass'] ?? '';
        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function fromConfig(array $cfg): Database
    {
        return new self($cfg);
    }

    public static function main(): Database
    {
        if (self::$main === null) {
            if (!Config::fileExists('database')) {
                throw new RuntimeException('Module database is not configured');
            }
            self::$main = new self(Config::file('database'));
        }
        return self::$main;
    }

    public static function external(string $key): Database
    {
        $cfg = Config::get('external.' . $key, []);
        if (empty($cfg)) {
            throw new RuntimeException('External connection not configured: ' . $key);
        }
        return new self($cfg);
    }

    public static function reset(): void
    {
        self::$main = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue(string $sql, array $params = [], $default = null)
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    public function count(string $sql, array $params = []): int
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? 0 : (int) $row[0];
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $value) {
            $sets[] = sprintf('%s = ?', $col);
            $params[] = $value;
        }
        foreach ($whereParams as $value) {
            $params[] = $value;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function tableExists(string $table): bool
    {
        try {
            $exists = $this->fetchValue(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            return ((int) $exists) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}