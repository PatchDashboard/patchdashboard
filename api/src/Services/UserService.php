<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class UserService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function list(): array
    {
        return $this->pdo->query(
            'SELECT id, user_id, email, admin, display_name, active, last_seen, receive_alerts FROM users ORDER BY user_id'
        )->fetchAll();
    }

    public function create(array $data): array
    {
        $password = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (user_id, email, admin, display_name, password, active, receive_alerts)
             VALUES (:user_id, :email, :admin, :display_name, :password, :active, :receive_alerts)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'email' => $data['email'],
            'admin' => !empty($data['admin']) ? 1 : 0,
            'display_name' => $data['display_name'] ?? $data['user_id'],
            'password' => $password,
            'active' => array_key_exists('active', $data) ? (int) (bool) $data['active'] : 1,
            'receive_alerts' => !empty($data['receive_alerts']) ? 1 : 0,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['email', 'display_name', 'admin', 'active', 'receive_alerts'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = in_array($field, ['admin', 'active', 'receive_alerts'], true)
                    ? ((bool) $data[$field] ? 1 : 0)
                    : $data[$field];
            }
        }

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        if ($fields === []) {
            return $this->findById($id);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, email, admin, display_name, active, last_seen, receive_alerts FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
