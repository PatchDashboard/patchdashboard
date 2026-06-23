#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create initial company row and admin user for a fresh PatchDashboard install.
 *
 * Usage:
 *   php database/bootstrap.php \
 *     --admin-user=admin \
 *     --admin-email=admin@example.com \
 *     --admin-pass='secret' \
 *     --company='Acme Inc' \
 *     [--install-key=random-key]
 */

$options = getopt('', [
    'admin-user:',
    'admin-email:',
    'admin-pass:',
    'company:',
    'install-key::',
]);

foreach (['admin-user', 'admin-email', 'admin-pass', 'company'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing required option --{$required}\n");
        exit(1);
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'patchdashboard';
$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$user = getenv('DB_USER') ?: 'patchdashboard';
$pass = getenv('DB_PASS') ?: 'patchdashboard';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$installKey = $options['install-key'] ?? bin2hex(random_bytes(16));
$companySlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($options['company'])));
$companySlug = trim($companySlug, '-') ?: 'patchdashboard';

$companyCount = (int) $pdo->query('SELECT COUNT(*) FROM company')->fetchColumn();
if ($companyCount === 0) {
    $pdo->prepare(
        'INSERT INTO company (name, display_name, install_key) VALUES (:name, :display_name, :install_key)'
    )->execute([
        'name' => $companySlug,
        'display_name' => $options['company'],
        'install_key' => $installKey,
    ]);
    echo "Created company record (install_key: {$installKey})\n";
} else {
    echo "Company record already exists; leaving install_key unchanged.\n";
}

$adminUser = $options['admin-user'];
$stmt = $pdo->prepare('SELECT id FROM users WHERE user_id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $adminUser]);
$existing = $stmt->fetchColumn();

$passwordHash = password_hash($options['admin-pass'], PASSWORD_BCRYPT);

if ($existing) {
    $pdo->prepare(
        'UPDATE users SET email = :email, password = :password, admin = 1, active = 1, display_name = :display_name WHERE id = :id'
    )->execute([
        'email' => $options['admin-email'],
        'password' => $passwordHash,
        'display_name' => 'Administrator',
        'id' => $existing,
    ]);
    echo "Updated admin user {$adminUser}\n";
} else {
    $pdo->prepare(
        'INSERT INTO users (user_id, email, admin, display_name, password, active, receive_alerts, last_seen)
         VALUES (:user_id, :email, 1, :display_name, :password, 1, 1, NOW())'
    )->execute([
        'user_id' => $adminUser,
        'email' => $options['admin-email'],
        'display_name' => 'Administrator',
        'password' => $passwordHash,
    ]);
    echo "Created admin user {$adminUser}\n";
}
