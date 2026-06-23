<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class PluginService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function listPlugins(): array
    {
        return $this->pdo->query(
            'SELECT id, name, disabled, installed, is_admin, glyph FROM plugins ORDER BY is_admin, name'
        )->fetchAll();
    }

    public function updatePlugin(int $id, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['disabled', 'installed'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = (bool) $data[$field] ? 1 : 0;
            }
        }

        if ($fields === []) {
            return $this->findPlugin($id);
        }

        $sql = 'UPDATE plugins SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->findPlugin($id);
    }

    public function listPages(): array
    {
        return $this->pdo->query(
            'SELECT pm.id, pm.page_name, pm.real_file, pm.plugin_parent, pm.on_navbar, pm.glyph,
                    p.name AS plugin_name, p.is_admin
             FROM page_maps pm
             LEFT JOIN plugins p ON p.id = pm.plugin_parent
             ORDER BY pm.plugin_parent, pm.page_name'
        )->fetchAll();
    }

    public function updatePage(int $id, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('on_navbar', $data)) {
            $fields[] = 'on_navbar = :on_navbar';
            $params['on_navbar'] = (bool) $data['on_navbar'] ? 1 : 0;
        }

        if ($fields === []) {
            return $this->findPage($id);
        }

        $sql = 'UPDATE page_maps SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->findPage($id);
    }

    private function findPlugin(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, disabled, installed, is_admin, glyph FROM plugins WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findPage(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.id, pm.page_name, pm.real_file, pm.plugin_parent, pm.on_navbar, pm.glyph,
                    p.name AS plugin_name
             FROM page_maps pm
             LEFT JOIN plugins p ON p.id = pm.plugin_parent
             WHERE pm.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
