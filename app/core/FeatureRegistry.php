<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class FeatureRegistry
{
    private static ?array $all = null;

    public static function manifestPath(string $id): string
    {
        return SMR_APP . '/features/' . $id . '/manifest.php';
    }

    public static function scan(): array
    {
        $dir = SMR_APP . '/features';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $manifestFile = $dir . '/' . $entry . '/manifest.php';
            if (is_file($manifestFile)) {
                $manifest = require $manifestFile;
                if (is_array($manifest) && !empty($manifest['id'])) {
                    $out[$manifest['id']] = $manifest;
                }
            }
        }
        return $out;
    }

    public static function sync(): array
    {
        $db = Database::main();
        $scanned = self::scan();
        $changed = ['created' => 0, 'updated' => 0];
        foreach ($scanned as $id => $manifest) {
            $locked = !empty($manifest['always_enabled']) ? 1 : 0;
            $exists = $db->fetchRow('SELECT id, version, locked FROM smr_feature_registry WHERE id = ?', [$id]);
            if ($exists === null) {
                $db->insert('smr_feature_registry', [
                    'id' => $id,
                    'name' => $manifest['name'] ?? $id,
                    'version' => $manifest['version'] ?? '0.0.0',
                    'enabled' => 1,
                    'locked' => $locked,
                    'installed_at' => date('Y-m-d H:i:s'),
                ]);
                $changed['created']++;
            } else {
                $update = [
                    'name' => $manifest['name'] ?? $id,
                    'version' => $manifest['version'] ?? $exists['version'],
                    'locked' => $locked,
                ];
                $db->update('smr_feature_registry', $update, 'id = ?', [$id]);
                $changed['updated']++;
            }
        }
        self::$all = null;
        return $changed;
    }

    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        self::$all = [];
        if (!App::ready()) {
            return self::$all;
        }
        try {
            $rows = Database::main()->fetchAll('SELECT * FROM smr_feature_registry');
            $scanned = self::scan();
        } catch (\Throwable $e) {
            return self::$all;
        }
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $manifest = $scanned[$id] ?? [];
            self::$all[$id] = array_merge($manifest, [
                'id' => $id,
                'name' => $row['name'],
                'version' => $row['version'],
                'enabled' => (bool) (int) $row['enabled'],
                'locked' => (bool) (int) $row['locked'],
            ]);
        }
        foreach ($scanned as $id => $manifest) {
            if (!isset(self::$all[$id])) {
                self::$all[$id] = array_merge($manifest, [
                    'id' => $id,
                    'enabled' => false,
                    'locked' => false,
                ]);
            }
        }
        return self::$all;
    }

    public static function enabled(): array
    {
        $out = [];
        foreach (self::all() as $id => $feat) {
            if (!empty($feat['enabled'])) {
                $out[$id] = $feat;
            }
        }
        return $out;
    }

    public static function isEnabled(string $id): bool
    {
        $all = self::all();
        return isset($all[$id]) && !empty($all[$id]['enabled']);
    }

    public static function routes(): array
    {
        $routes = [];
        foreach (self::enabled() as $feat) {
            foreach ($feat['routes'] ?? [] as $pattern => $handler) {
                $routes[$pattern] = $handler;
            }
        }
        return $routes;
    }

    public static function menu(): array
    {
        $items = [];
        foreach (self::enabled() as $feat) {
            if (empty($feat['menu']) || !is_array($feat['menu'])) {
                continue;
            }
            $route = $feat['menu']['route'] ?? null;
            $roles = $feat['roles'] ?? ['root', 'admin', 'viewer'];
            if ($route === null || !Acl::userHasRole($roles)) {
                continue;
            }
            $items[] = [
                'label' => $feat['menu']['label'] ?? $feat['id'],
                'icon' => $feat['menu']['icon'] ?? 'dot',
                'route' => $route,
                'order' => (int) ($feat['menu']['order'] ?? 999),
            ];
        }
        usort($items, function (array $a, array $b) {
            return $a['order'] <=> $b['order'];
        });
        return $items;
    }
}