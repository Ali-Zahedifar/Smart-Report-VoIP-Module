<?php

namespace SmartReport\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static $main = null;

    private $pdo;

    private function __construct(array $cfg)
    {
        $host = isset($cfg['host']) ? $cfg['host'] : 'localhost';
        $port = isset($cfg['port']) ? $cfg['port'] : 3306;
        $name = isset($cfg['name']) ? $cfg['name'] : '';
        $charset = isset($cfg['charset']) ? $cfg['charset'] : 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, (int) $port, $name, $charset);
        $user = isset($cfg['user']) ? $cfg['user'] : '';
        $pass = isset($cfg['pass']) ? $cfg['pass'] : '';
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

    public static function fromConfig(array $cfg)
    {
        return new self($cfg);
    }

    public static function main()
    {
        if (self::$main === null) {
            if (!Config::fileExists('database')) {
                throw new RuntimeException('Module database is not configured');
            }
            self::$main = new self(Config::file('database'));
        }
        return self::$main;
    }

    public static function external($key)
    {
        $cfg = Config::get('external.' . $key, []);
        if (empty($cfg)) {
            throw new RuntimeException('External connection not configured: ' . $key);
        }
        return new self($cfg);
    }

    public static function reset()
    {
        self::$main = null;
    }

    public function pdo()
    {
        return $this->pdo;
    }

    public function query($sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        return $stmt;
    }

    public function fetchAll($sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchRow($sql, array $params = [])
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue($sql, array $params = [], $default = null)
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    public function count($sql, array $params = [])
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? 0 : (int) $row[0];
    }

    public function insert($table, array $data)
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

    public function update($table, array $data, $where, array $whereParams = [])
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

    public function delete($table, $where, array $params = [])
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function tableExists($table)
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