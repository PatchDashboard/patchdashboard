<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class AlertService
{
    public function __construct(
        private \PDO $pdo,
        private SlackService $slackService
    ) {
    }

    public function getRules(): array
    {
        $defaults = $this->defaultRules();
        if (!$this->tableExists('alert_rules')) {
            return $defaults;
        }
        $row = $this->pdo->query('SELECT * FROM alert_rules ORDER BY id LIMIT 1')->fetch();
        return $row ? array_merge($defaults, $row) : $defaults;
    }

    public function updateRules(array $data, bool $allowSlackTemplate = false): array
    {
        if (!$this->tableExists('alert_rules')) {
            return $this->defaultRules();
        }

        $fields = [
            'min_severity' => strtoupper((string) ($data['min_severity'] ?? 'HIGH')),
            'alert_on' => (string) ($data['alert_on'] ?? 'new'),
            'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 60),
        ];

        if ($allowSlackTemplate) {
            if (array_key_exists('slack_header', $data)) {
                $fields['slack_header'] = (string) $data['slack_header'];
            }
            if (array_key_exists('slack_body_template', $data)) {
                $fields['slack_body_template'] = (string) $data['slack_body_template'];
            }
            if (array_key_exists('slack_footer_template', $data)) {
                $fields['slack_footer_template'] = (string) $data['slack_footer_template'];
            }
        }

        $existing = $this->pdo->query('SELECT id FROM alert_rules ORDER BY id LIMIT 1')->fetch();
        if ($existing) {
            $setParts = [];
            $params = ['id' => $existing['id']];
            foreach ($fields as $column => $value) {
                if (!$this->columnExists('alert_rules', $column)) {
                    continue;
                }
                $setParts[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            if ($setParts !== []) {
                $sql = 'UPDATE alert_rules SET ' . implode(', ', $setParts) . ' WHERE id = :id';
                $this->pdo->prepare($sql)->execute($params);
            }
        } else {
            $columns = [];
            $placeholders = [];
            $params = [];
            foreach ($fields as $column => $value) {
                if (!$this->columnExists('alert_rules', $column)) {
                    continue;
                }
                $columns[] = $column;
                $placeholders[] = ':' . $column;
                $params[$column] = $value;
            }
            if ($columns !== []) {
                $sql = 'INSERT INTO alert_rules (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $this->pdo->prepare($sql)->execute($params);
            }
        }

        return $this->getRules();
    }

    /**
     * @return array{template: array{header: string, body: string, footer: string}, variables: list<string>}
     */
    public function getSlackTemplateDefaults(): array
    {
        return [
            'template' => $this->slackService->defaultCardTemplate(),
            'variables' => $this->slackService->templateVariables(),
        ];
    }

    /**
     * @param array{header?: string, body?: string, footer?: string} $template
     * @return array{text: string, blocks: array<int, array<string, mixed>>}
     */
    public function previewSlackTemplate(array $template): array
    {
        return $this->slackService->previewCard($template);
    }

    public function listWebhooks(): array
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return [];
        }
        $this->syncSlackFromEnv();
        return $this->pdo->query(
            'SELECT id, name, url, enabled, slack_format, slack_channel, slack_username, slack_icon_url, created_at
             FROM webhook_endpoints ORDER BY slack_format DESC, name'
        )->fetchAll();
    }

    public function createWebhook(array $data): array
    {
        $url = trim((string) ($data['url'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || $url === '') {
            throw new \InvalidArgumentException('name and url are required');
        }

        $slackFormat = !empty($data['slack_format']) || $this->slackService->isSlackWebhookUrl($url);
        if ($slackFormat && !$this->slackService->isSlackWebhookUrl($url)) {
            throw new \InvalidArgumentException('Slack webhooks must use a hooks.slack.com URL');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_endpoints (name, url, secret, enabled, slack_format, slack_channel, slack_username, slack_icon_url)
             VALUES (:name, :url, :secret, :enabled, :slack_format, :slack_channel, :slack_username, :slack_icon_url)'
        );
        $stmt->execute([
            'name' => $name,
            'url' => $url,
            'secret' => $data['secret'] ?? null,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'slack_format' => $slackFormat ? 1 : 0,
            'slack_channel' => $data['slack_channel'] ?? null,
            'slack_username' => $data['slack_username'] ?? 'PatchDashboard',
            'slack_icon_url' => $this->normalizeIconUrl($data['slack_icon_url'] ?? null),
        ]);

        return $this->findWebhook((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function updateWebhook(int $id, array $data): ?array
    {
        $existing = $this->findWebhook($id);
        if ($existing === null) {
            return null;
        }

        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = :name';
            $params['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('url', $data)) {
            $url = trim((string) $data['url']);
            $fields[] = 'url = :url';
            $params['url'] = $url;
            if ($this->slackService->isSlackWebhookUrl($url)) {
                $fields[] = 'slack_format = 1';
            }
        }
        if (array_key_exists('enabled', $data)) {
            $fields[] = 'enabled = :enabled';
            $params['enabled'] = !empty($data['enabled']) ? 1 : 0;
        }
        if (array_key_exists('slack_channel', $data)) {
            $fields[] = 'slack_channel = :slack_channel';
            $params['slack_channel'] = $data['slack_channel'] !== '' ? $data['slack_channel'] : null;
        }
        if (array_key_exists('slack_username', $data)) {
            $fields[] = 'slack_username = :slack_username';
            $params['slack_username'] = $data['slack_username'] !== '' ? $data['slack_username'] : 'PatchDashboard';
        }
        if (array_key_exists('slack_icon_url', $data)) {
            $fields[] = 'slack_icon_url = :slack_icon_url';
            $params['slack_icon_url'] = $this->normalizeIconUrl($data['slack_icon_url']);
        }

        if ($fields === []) {
            return $existing;
        }

        $sql = 'UPDATE webhook_endpoints SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->findWebhook($id);
    }

    public function deleteWebhook(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhook_endpoints WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function testWebhook(int $id): array
    {
        $webhook = $this->findWebhook($id, true);
        if ($webhook === null) {
            return ['success' => false, 'message' => 'Webhook not found'];
        }

        $cardTemplate = !empty($webhook['slack_format'])
            ? $this->slackService->resolveCardTemplate($this->getRules())
            : null;

        $payload = !empty($webhook['slack_format'])
            ? $this->slackService->buildTestPayload(
                $webhook['slack_channel'] ?? null,
                $webhook['slack_username'] ?? null,
                $webhook['slack_icon_url'] ?? null,
                $cardTemplate
            )
            : [
                'text' => 'PatchDashboard test alert: webhook is configured.',
                'event' => 'test',
                'timestamp' => date('c'),
            ];

        $ok = $this->dispatchWebhook($webhook, $payload);
        $channel = !empty($webhook['slack_format']) ? 'slack' : 'webhook';
        $this->logEvent($channel, $id, $ok ? 'sent' : 'failed', 'Test alert');

        return [
            'success' => $ok,
            'message' => $ok
                ? (!empty($webhook['slack_format']) ? 'Test message sent to Slack' : 'Test webhook delivered')
                : 'Delivery failed — check URL and server outbound HTTPS',
        ];
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
        $this->sendWebhookAlerts($summary, $filtered, $serverId, $rules);
    }

    private function syncSlackFromEnv(): void
    {
        $url = trim((string) ($_ENV['SLACK_WEBHOOK_URL'] ?? ''));
        if ($url === '' || !$this->slackService->isSlackWebhookUrl($url)) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM webhook_endpoints WHERE url = :url LIMIT 1');
        $stmt->execute(['url' => $url]);
        if ($stmt->fetch()) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO webhook_endpoints (name, url, enabled, slack_format, slack_username, slack_icon_url)
             VALUES (:name, :url, 1, 1, :slack_username, :slack_icon_url)'
        )->execute([
            'name' => 'Slack (environment)',
            'url' => $url,
            'slack_username' => $_ENV['SLACK_USERNAME'] ?? 'PatchDashboard',
            'slack_icon_url' => $this->normalizeIconUrl($_ENV['SLACK_ICON_URL'] ?? null),
        ]);
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

    private function sendWebhookAlerts(string $summary, array $vulnerabilities, int $serverId, array $rules): void
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return;
        }

        $cardTemplate = $this->slackService->resolveCardTemplate($rules);
        $serverContext = $this->resolveServerContext($serverId);

        $webhooks = $this->pdo->query('SELECT * FROM webhook_endpoints WHERE enabled = 1')->fetchAll();
        foreach ($webhooks as $webhook) {
            $payload = !empty($webhook['slack_format'])
                ? $this->slackService->buildAlertPayload(
                    $summary,
                    $vulnerabilities,
                    $serverId,
                    $webhook['slack_channel'] ?? null,
                    $webhook['slack_username'] ?? null,
                    $webhook['slack_icon_url'] ?? null,
                    $cardTemplate,
                    $serverContext
                )
                : [
                    'summary' => $summary,
                    'server_id' => $serverId,
                    'vulnerabilities' => $vulnerabilities,
                    'timestamp' => date('c'),
                ];

            $ok = $this->dispatchWebhook($webhook, $payload);
            $channel = !empty($webhook['slack_format']) ? 'slack' : 'webhook';
            $this->logEvent($channel, (int) $webhook['id'], $ok ? 'sent' : 'failed', $summary);
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
        if ($result === false) {
            return false;
        }

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
            return $status >= 200 && $status < 300;
        }

        return true;
    }

    private function findWebhook(int $id, bool $includeSecret = false): ?array
    {
        $columns = 'id, name, url, enabled, slack_format, slack_channel, slack_username, slack_icon_url, created_at';
        if ($includeSecret) {
            $columns = 'id, name, url, secret, enabled, slack_format, slack_channel, slack_username, slack_icon_url, created_at';
        }
        $stmt = $this->pdo->prepare("SELECT {$columns} FROM webhook_endpoints WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
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
        $defaults = $this->slackService->defaultCardTemplate();

        return [
            'min_severity' => 'HIGH',
            'alert_on' => 'new',
            'cooldown_minutes' => 60,
            'slack_header' => $defaults['header'],
            'slack_body_template' => $defaults['body'],
            'slack_footer_template' => $defaults['footer'],
        ];
    }

    /**
     * @return array{server_alias: string, server_name: string}
     */
    private function resolveServerContext(int $serverId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT server_alias, server_name FROM servers WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $serverId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'server_alias' => "server-{$serverId}",
                'server_name' => "server-{$serverId}",
            ];
        }

        return [
            'server_alias' => (string) ($row['server_alias'] ?: $row['server_name']),
            'server_name' => (string) $row['server_name'],
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
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

    private function normalizeIconUrl(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
