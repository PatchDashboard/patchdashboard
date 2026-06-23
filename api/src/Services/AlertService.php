<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class AlertService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function getRules(): array
    {
        if (!$this->tableExists('alert_rules')) {
            return $this->defaultRules();
        }
        $row = $this->pdo->query('SELECT * FROM alert_rules ORDER BY id LIMIT 1')->fetch();
        return $row ?: $this->defaultRules();
    }

    public function updateRules(array $data): array
    {
        if (!$this->tableExists('alert_rules')) {
            return $this->defaultRules();
        }

        $existing = $this->pdo->query('SELECT id FROM alert_rules ORDER BY id LIMIT 1')->fetch();
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE alert_rules SET min_severity = :min_severity, alert_on = :alert_on, cooldown_minutes = :cooldown_minutes WHERE id = :id'
            );
            $stmt->execute([
                'id' => $existing['id'],
                'min_severity' => strtoupper((string) ($data['min_severity'] ?? 'HIGH')),
                'alert_on' => (string) ($data['alert_on'] ?? 'new'),
                'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 60),
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO alert_rules (min_severity, alert_on, cooldown_minutes) VALUES (:min_severity, :alert_on, :cooldown_minutes)'
            );
            $stmt->execute([
                'min_severity' => strtoupper((string) ($data['min_severity'] ?? 'HIGH')),
                'alert_on' => (string) ($data['alert_on'] ?? 'new'),
                'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 60),
            ]);
        }

        return $this->getRules();
    }

    public function listWebhooks(): array
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return [];
        }
        return $this->pdo->query('SELECT id, name, url, enabled, slack_format, created_at FROM webhook_endpoints ORDER BY name')->fetchAll();
    }

    public function createWebhook(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_endpoints (name, url, secret, enabled, slack_format)
             VALUES (:name, :url, :secret, :enabled, :slack_format)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $data['secret'] ?? null,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'slack_format' => !empty($data['slack_format']) ? 1 : 0,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        return ['id' => $id, 'name' => $data['name'], 'url' => $data['url'], 'enabled' => !empty($data['enabled'])];
    }

    public function deleteWebhook(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhook_endpoints WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function testWebhook(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_endpoints WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $webhook = $stmt->fetch();
        if (!$webhook) {
            return ['success' => false, 'message' => 'Webhook not found'];
        }

        $payload = [
            'text' => 'PatchDashboard test alert: container vulnerability webhook is configured.',
            'event' => 'test',
            'timestamp' => date('c'),
        ];

        $ok = $this->dispatchWebhook($webhook, $payload);
        $this->logEvent('webhook', $id, $ok ? 'sent' : 'failed', json_encode($payload));

        return ['success' => $ok, 'message' => $ok ? 'Test webhook delivered' : 'Webhook delivery failed'];
    }

    public function listEvents(int $limit = 50): array
    {
        if (!$this->tableExists('alert_events')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM alert_events ORDER BY sent_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function dispatchContainerAlerts(int $serverId, array $newVulnerabilities): void
    {
        if ($newVulnerabilities === [] || !$this->tableExists('alert_rules')) {
            return;
        }

        $rules = $this->getRules();
        $minSeverity = $this->severityRank((string) $rules['min_severity']);

        $filtered = array_filter($newVulnerabilities, function (array $vuln) use ($minSeverity): bool {
            return $this->severityRank((string) $vuln['severity']) >= $minSeverity;
        });

        if ($filtered === []) {
            return;
        }

        $summary = sprintf(
            '%d new container vulnerabilities on server id %d',
            count($filtered),
            $serverId
        );

        $this->sendEmailAlert($summary, $filtered);
        $this->sendWebhookAlerts($summary, $filtered, $serverId);
    }

    private function sendEmailAlert(string $summary, array $vulnerabilities): void
    {
        $users = $this->pdo->query('SELECT email FROM users WHERE receive_alerts = 1 AND active = 1')->fetchAll();
        if ($users === []) {
            return;
        }

        $from = $_ENV['MAIL_FROM'] ?? 'alerts@patchdashboard.local';
        $body = $summary . "\n\n" . json_encode($vulnerabilities, JSON_PRETTY_PRINT);
        foreach ($users as $user) {
            @mail((string) $user['email'], '[PatchDashboard] Container vulnerability alert', $body, "From: {$from}\r\n");
            $this->logEvent('email', null, 'sent', $summary);
        }
    }

    private function sendWebhookAlerts(string $summary, array $vulnerabilities, int $serverId): void
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return;
        }

        $webhooks = $this->pdo->query('SELECT * FROM webhook_endpoints WHERE enabled = 1')->fetchAll();
        foreach ($webhooks as $webhook) {
            $payload = !empty($webhook['slack_format'])
                ? ['text' => "*PatchDashboard Alert*\n{$summary}"]
                : [
                    'summary' => $summary,
                    'server_id' => $serverId,
                    'vulnerabilities' => $vulnerabilities,
                    'timestamp' => date('c'),
                ];

            $ok = $this->dispatchWebhook($webhook, $payload);
            $this->logEvent('webhook', (int) $webhook['id'], $ok ? 'sent' : 'failed', $summary);
        }
    }

    private function dispatchWebhook(array $webhook, array $payload): bool
    {
        $body = json_encode($payload);
        $headers = "Content-Type: application/json\r\n";
        if (!empty($webhook['secret'])) {
            $headers .= 'X-PatchDashboard-Secret: ' . $webhook['secret'] . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents((string) $webhook['url'], false, $context);
        return $result !== false;
    }

    private function logEvent(string $channel, ?int $referenceId, string $status, string $summary): void
    {
        if (!$this->tableExists('alert_events')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_events (channel, reference_id, status, summary) VALUES (:channel, :reference_id, :status, :summary)'
        );
        $stmt->execute([
            'channel' => $channel,
            'reference_id' => $referenceId,
            'status' => $status,
            'summary' => $summary,
        ]);
    }

    private function defaultRules(): array
    {
        return [
            'min_severity' => 'HIGH',
            'alert_on' => 'new',
            'cooldown_minutes' => 60,
        ];
    }

    private function severityRank(string $severity): int
    {
        return match (strtoupper($severity)) {
            'CRITICAL' => 4,
            'HIGH' => 3,
            'MEDIUM' => 2,
            'LOW' => 1,
            default => 0,
        };
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
        return (bool) $stmt->fetch();
    }
}
