<?php

declare(strict_types=1);

namespace SmartReport\Features\Calls\Models;

use SmartReport\Core\Database;
use SmartReport\Services\CacheService;

class CdrModel
{
    private Database $db;
    private array $columns = [];

    private const BASE_COLUMNS = ['calldate', 'clid', 'src', 'dst', 'dcontext', 'duration', 'billsec', 'disposition', 'accountcode', 'uniqueid'];

    public function __construct()
    {
        $this->db = Database::external('cdr');
        $this->columns = $this->columns();
    }

    public function columns(): array
    {
        $cached = CacheService::get('cdr_columns_v1', null);
        if (is_array($cached)) {
            return $cached;
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll('SHOW COLUMNS FROM cdr');
            foreach ($rows as $row) {
                $cols[] = (string) ($row['Field'] ?? $row[0] ?? '');
            }
        } catch (\Throwable $e) {
            return self::BASE_COLUMNS;
        }
        CacheService::set('cdr_columns_v1', $cols, 3600);
        return $cols;
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns, true);
    }

    public function selectColumns(): string
    {
        $cols = self::BASE_COLUMNS;
        if (in_array('recordingfile', $this->columns, true)) {
            $cols[] = 'recordingfile';
        }
        return implode(', ', $cols);
    }

    public function listings(array $filters, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM cdr WHERE ' . $where, $params);
        $paramsLimit = array_merge($params, [(string) $perPage, (string) $offset]);
        $rows = $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE ' . $where . ' ORDER BY calldate DESC LIMIT ? OFFSET ?',
            $paramsLimit
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function findAll(array $filters, int $maxRows = 50000): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE ' . $where . ' ORDER BY calldate DESC';
        if ($maxRows > 0) {
            $sql .= ' LIMIT ' . (int) $maxRows;
        }
        return $this->db->fetchAll($sql, $params);
    }

    public function findByUniqueId(string $uniqueid): ?array
    {
        return $this->db->fetchRow(
            'SELECT ' . $this->selectColumns() . ' FROM cdr WHERE uniqueid = ? ORDER BY calldate DESC LIMIT 1',
            [$uniqueid]
        );
    }

    public function todayStats(): array
    {
        $from = date('Y-m-d 00:00:00');
        $to = date('Y-m-d 23:59:59');
        $base = 'FROM cdr WHERE calldate BETWEEN ? AND ?';
        $params = [$from, $to];

        $stats = [
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) ' . $base, $params),
            'answered' => 0,
            'notAnswered' => 0,
            'other' => 0,
            'avgTalk' => 0,
        ];

        $stats['answered'] = (int) $this->db->fetchValue(
            'SELECT COUNT(*) ' . $base . ' AND disposition = ?',
            array_merge($params, ['ANSWERED'])
        );
        $stats['notAnswered'] = (int) $this->db->fetchValue(
            'SELECT COUNT(*) ' . $base . ' AND disposition = ?',
            array_merge($params, ['NO ANSWER'])
        );
        $stats['other'] = (int) $this->db->fetchValue(
            'SELECT COUNT(*) ' . $base . ' AND disposition NOT IN (?, ?)',
            array_merge($params, ['ANSWERED', 'NO ANSWER'])
        );
        $stats['avgTalk'] = (int) round((float) $this->db->fetchValue(
            'SELECT COALESCE(AVG(billsec), 0) ' . $base . ' AND disposition = ?',
            array_merge($params, ['ANSWERED'])
        ));

        return $stats;
    }

    public function recent(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT ' . $this->selectColumns() . ' FROM cdr ORDER BY calldate DESC LIMIT ?',
            [(string) $limit]
        );
    }

    private function buildWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'calldate >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'calldate <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        foreach (['src', 'dst', 'clid'] as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $where[] = $col . ' LIKE ?';
                $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters[$col]) . '%';
            }
        }

        if (!empty($filters['disposition']) && $filters['disposition'] !== 'ANY') {
            $where[] = 'disposition = ?';
            $params[] = $filters['disposition'];
        }

        return [implode(' AND ', $where), $params];
    }
}