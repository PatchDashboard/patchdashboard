<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class SlackService
{
    /**
     * @return array{header: string, body: string, footer: string}
     */
    public function defaultCardTemplate(): array
    {
        return [
            'header' => 'PatchDashboard alert',
            'body' => "*{{summary}}*\nServer: {{server_alias}} · ID `{{server_id}}`\n\n{{details}}",
            'footer' => 'Sent from PatchDashboard · {{timestamp}}',
        ];
    }

    /**
     * @return list<string>
     */
    public function templateVariables(): array
    {
        return [
            '{{summary}}',
            '{{server_id}}',
            '{{server_alias}}',
            '{{server_name}}',
            '{{count}}',
            '{{details}}',
            '{{timestamp}}',
        ];
    }

    /**
     * @param array<string, mixed>|null $fromRules
     * @return array{header: string, body: string, footer: string}
     */
    public function resolveCardTemplate(?array $fromRules): array
    {
        $defaults = $this->defaultCardTemplate();
        if ($fromRules === null) {
            return $defaults;
        }

        return [
            'header' => $this->nonEmptyString($fromRules['slack_header'] ?? null) ?? $defaults['header'],
            'body' => $this->nonEmptyString($fromRules['slack_body_template'] ?? null) ?? $defaults['body'],
            'footer' => $this->nonEmptyString($fromRules['slack_footer_template'] ?? null) ?? $defaults['footer'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $vulnerabilities
     * @param array{header: string, body: string, footer: string}|null $cardTemplate
     * @param array<string, mixed> $serverContext
     * @return array<string, mixed>
     */
    public function buildAlertPayload(
        string $summary,
        array $vulnerabilities,
        int $serverId,
        ?string $channel = null,
        ?string $username = null,
        ?string $iconUrl = null,
        ?array $cardTemplate = null,
        array $serverContext = []
    ): array {
        $template = $cardTemplate ?? $this->defaultCardTemplate();
        $vars = $this->buildTemplateVariables($summary, $vulnerabilities, $serverId, $serverContext);

        $headerText = $this->truncatePlainText($this->renderTemplate($template['header'], $vars));
        $bodyText = $this->renderTemplate($template['body'], $vars);
        $footerText = trim($this->renderTemplate($template['footer'], $vars));

        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => $headerText, 'emoji' => true],
            ],
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $bodyText],
            ],
        ];

        if ($footerText !== '') {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => $footerText],
                ],
            ];
        }

        $payload = [
            'text' => "PatchDashboard: {$summary}",
            'blocks' => $blocks,
        ];

        if ($channel !== null && trim($channel) !== '') {
            $payload['channel'] = trim($channel);
        }

        return $this->applyBotAppearance($payload, $username, $iconUrl);
    }

    /**
     * @param array{header: string, body: string, footer: string}|null $cardTemplate
     * @return array<string, mixed>
     */
    public function buildTestPayload(
        ?string $channel = null,
        ?string $username = null,
        ?string $iconUrl = null,
        ?array $cardTemplate = null
    ): array {
        return $this->buildAlertPayload(
            '1 new container vulnerability on sample-server',
            [
                [
                    'cve_id' => 'CVE-2024-0001',
                    'severity' => 'HIGH',
                    'package_name' => 'openssl',
                ],
                [
                    'cve_id' => 'CVE-2024-0002',
                    'severity' => 'CRITICAL',
                    'package_name' => 'curl',
                ],
            ],
            42,
            $channel,
            $username,
            $iconUrl,
            $cardTemplate,
            ['server_alias' => 'sample-server', 'server_name' => 'sample-server']
        );
    }

    /**
     * @param array{header?: string, body?: string, footer?: string} $template
     * @return array{text: string, blocks: array<int, array<string, mixed>>}
     */
    public function previewCard(array $template): array
    {
        $resolved = $this->resolveCardTemplate([
            'slack_header' => $template['header'] ?? null,
            'slack_body_template' => $template['body'] ?? null,
            'slack_footer_template' => $template['footer'] ?? null,
        ]);

        $payload = $this->buildTestPayload(null, null, null, $resolved);
        return [
            'text' => (string) ($payload['text'] ?? ''),
            'blocks' => (array) ($payload['blocks'] ?? []),
        ];
    }

    public function isSlackWebhookUrl(string $url): bool
    {
        return (bool) preg_match('#^https://hooks\.slack\.com/services/.+#i', trim($url));
    }

    /**
     * @param array<int, array<string, mixed>> $vulnerabilities
     * @param array<string, mixed> $serverContext
     * @return array<string, string>
     */
    private function buildTemplateVariables(
        string $summary,
        array $vulnerabilities,
        int $serverId,
        array $serverContext
    ): array {
        $alias = (string) ($serverContext['server_alias'] ?? $serverContext['server_name'] ?? "server-{$serverId}");
        $name = (string) ($serverContext['server_name'] ?? $alias);

        return [
            'summary' => $summary,
            'server_id' => (string) $serverId,
            'server_alias' => $alias,
            'server_name' => $name,
            'count' => (string) count($vulnerabilities),
            'details' => $this->formatVulnerabilityDetails($vulnerabilities),
            'timestamp' => date('c'),
        ];
    }

    /**
     * @param array<string, string> $vars
     */
    private function renderTemplate(string $template, array $vars): string
    {
        $rendered = $template;
        foreach ($vars as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
        }
        return $rendered;
    }

    /**
     * @param array<int, array<string, mixed>> $vulnerabilities
     */
    private function formatVulnerabilityDetails(array $vulnerabilities): string
    {
        $lines = [];
        foreach (array_slice($vulnerabilities, 0, 8) as $vuln) {
            $cve = (string) ($vuln['cve_id'] ?? 'unknown');
            $severity = strtoupper((string) ($vuln['severity'] ?? 'UNKNOWN'));
            $pkg = (string) ($vuln['package_name'] ?? '');
            $lines[] = '• *' . $severity . '* `' . $cve . '`' . ($pkg !== '' ? ' — ' . $pkg : '');
        }

        $remaining = count($vulnerabilities) - count($lines);
        if ($remaining > 0) {
            $lines[] = '_…and ' . $remaining . ' more_';
        }

        return $lines === [] ? '_No vulnerability details available._' : implode("\n", $lines);
    }

    private function truncatePlainText(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 150);
        }
        return substr($text, 0, 150);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyBotAppearance(array $payload, ?string $username, ?string $iconUrl): array
    {
        $payload['username'] = $username !== null && trim($username) !== '' ? trim($username) : 'PatchDashboard';

        $iconUrl = $iconUrl !== null ? trim($iconUrl) : '';
        if ($iconUrl !== '' && filter_var($iconUrl, FILTER_VALIDATE_URL)) {
            $payload['icon_url'] = $iconUrl;
        }

        return $payload;
    }
}
