<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/vendor/autoload.php';

use PatchDashboard\Api\Services\SshKeyService;

$keys = (new SshKeyService())->ensureKeyPair();
echo json_encode([
    'public_key' => $keys['public_key'],
    'fingerprint' => $keys['fingerprint'],
    'private_key_path' => $keys['private_key_path'],
], JSON_THROW_ON_ERROR) . PHP_EOL;
