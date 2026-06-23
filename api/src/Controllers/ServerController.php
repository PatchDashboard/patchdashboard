<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ContainerScanService;
use PatchDashboard\Api\Services\ServerService;
use PatchDashboard\Api\Services\SshKeyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ServerController
{
    public function __construct(
        private ServerService $serverService,
        private ContainerScanService $containerScanService,
        private SshKeyService $sshKeyService,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $hostsOnly = ($params['hosts_only'] ?? '1') !== '0';
        $summaries = $this->containerScanService->workloadSummariesByServer();
        $servers = array_map(function (array $server) use ($summaries) {
            $summary = $summaries[(int) $server['id']] ?? [
                'workloads' => 0,
                'host_scans' => 0,
                'container_workloads' => 0,
                'host_cves' => 0,
                'container_cves' => 0,
            ];
            $server['monitoring'] = $summary;

            return $server;
        }, $this->serverService->listAll($hostsOnly));

        return $this->json($response, ['servers' => $servers]);
    }

    public function workloads(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $serverId = (int) $args['id'];
        $server = $this->serverService->findById($serverId);
        if ($server === null || $this->serverService->isContainerHost($server)) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $data = $this->containerScanService->serverWorkloads($serverId);

        return $this->json($response, [
            'server' => [
                'id' => $serverId,
                'server_alias' => $server['server_alias'],
                'server_name' => $server['server_name'],
                'trusted' => (int) ($server['trusted'] ?? 0),
            ],
            ...$data,
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $server = $this->serverService->findById((int) $args['id']);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }
        return $this->json($response, ['server' => $server]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $server = $this->serverService->update((int) $args['id'], $body);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }
        return $this->json($response, ['server' => $server]);
    }

    public function activate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->serverService->setTrusted((int) $args['id'], true);
        return $this->json($response, ['status' => 'activated']);
    }

    public function deactivate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $result = $this->serverService->revokeTrust((int) $args['id'], $this->sshKeyService);
        } catch (\InvalidArgumentException) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        return $this->json($response, [
            'status' => 'untrusted',
            'client_key_revoked' => $result['client_key_revoked'],
            'ssh_key_removed' => $result['ssh_key_removed'],
        ]);
    }

    public function enroll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $serverIp = trim((string) ($body['server_ip'] ?? ''));
        $sshUser = trim((string) ($body['ssh_user'] ?? 'root'));
        $serverAlias = trim((string) ($body['server_alias'] ?? ''));
        $sshPassword = (string) ($body['ssh_password'] ?? '');
        $sshPassword = $sshPassword !== '' ? $sshPassword : null;

        if ($serverIp === '') {
            return $this->json($response, ['error' => 'server_ip is required'], 422);
        }

        $params = $request->getServerParams();
        $https = (!empty($params['HTTPS']) && $params['HTTPS'] !== 'off')
            || ($request->getHeaderLine('X-Forwarded-Proto') === 'https');
        $host = $request->getHeaderLine('Host') ?: 'localhost';
        $apiBase = ($https ? 'https' : 'http') . '://' . $host;
        $authKey = $this->serverService->getInstallKey();

        if ($authKey === '') {
            return $this->json($response, ['error' => 'Install key is not configured'], 500);
        }

        try {
            $keys = $this->sshKeyService->ensureKeyPair();
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'Unable to prepare SSH keys: ' . $e->getMessage()], 500);
        }

        $sshDeployed = $this->sshKeyService->deployAuthorizedKey($serverIp, $sshUser, $sshPassword);
        $enrollment = $this->sshKeyService->runEnrollmentScript(
            $serverIp,
            $sshUser,
            $apiBase,
            $authKey,
            $serverAlias !== '' ? $serverAlias : null,
            $sshPassword,
        );

        $server = $this->serverService->findByIpOrName($serverIp, $serverAlias !== '' ? $serverAlias : null);
        if ($server !== null) {
            $this->serverService->markSshEnrolled((int) $server['id'], $keys['fingerprint']);
            if ($serverAlias !== '' && $serverAlias !== ($server['server_alias'] ?? '')) {
                $this->serverService->update((int) $server['id'], ['server_alias' => $serverAlias]);
            }
        }

        if (!$enrollment['ok']) {
            return $this->json($response, [
                'error' => 'Enrollment failed',
                'ssh_deployed' => $sshDeployed,
                'output' => $enrollment['output'],
                'dashboard_public_key' => $keys['public_key'],
            ], 502);
        }

        return $this->json($response, [
            'status' => 'enrolled',
            'ssh_deployed' => $sshDeployed,
            'output' => $enrollment['output'],
            'server' => $server,
            'message' => 'Agent installed. Trust the server under Servers when it appears.',
        ], 201);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->serverService->delete((int) $args['id']);
        return $this->json($response, ['status' => 'deleted']);
    }

    public function onboarding(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getServerParams();
        $https = (!empty($params['HTTPS']) && $params['HTTPS'] !== 'off')
            || ($request->getHeaderLine('X-Forwarded-Proto') === 'https');
        $host = $request->getHeaderLine('Host') ?: 'localhost';
        $baseUrl = ($https ? 'https' : 'http') . '://' . $host;

        $publicKey = null;
        $fingerprint = null;
        try {
            $keys = $this->sshKeyService->ensureKeyPair();
            $publicKey = $keys['public_key'];
            $fingerprint = $keys['fingerprint'];
        } catch (\Throwable) {
            $publicKey = $this->sshKeyService->getPublicKey();
        }

        $installRoot = dirname(__DIR__, 3);
        $enrollScript = $installRoot . '/agents/client/enroll-server.sh';

        $bootstrap = $this->sshKeyService->getBootstrapKeyPair();

        return $this->json($response, [
            'https' => $https,
            'base_url' => $baseUrl,
            'push_script' => '/opt/patchdashboard/agents/client/check-in.sh',
            'pull_installer' => $baseUrl . '/legacy/html/client/client_installer.php',
            'recommend_pull' => $https,
            'dashboard_public_key' => $publicKey,
            'dashboard_ssh_fingerprint' => $fingerprint,
            'bootstrap_ssh_configured' => $bootstrap !== null,
            'bootstrap_public_key' => $bootstrap['public_key'] ?? null,
            'bootstrap_ssh_fingerprint' => $bootstrap['fingerprint'] ?? null,
            'enroll_script' => $enrollScript,
            'enroll_command_example' => sprintf(
                'sudo %s --host SERVER_IP --user root --api-base %s --auth-key YOUR_PD_AUTH_KEY',
                $enrollScript,
                $baseUrl
            ),
        ]);
    }

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = (array) $request->getAttribute('user');
        return !empty($user['admin']);
    }

    public function reboot(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->schedulePower($request, $response, (int) $args['id'], 'reboot');
    }

    public function shutdown(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->schedulePower($request, $response, (int) $args['id'], 'shutdown');
    }

    private function schedulePower(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $id,
        string $action
    ): ResponseInterface {
        $body = (array) ($request->getParsedBody() ?? []);
        $mode = (string) ($body['mode'] ?? 'graceful');
        if (!in_array($mode, ['graceful', 'forced', 'scheduled'], true)) {
            return $this->json($response, ['error' => 'mode must be graceful, forced, or scheduled'], 422);
        }

        $scheduledAt = isset($body['scheduled_at']) ? (string) $body['scheduled_at'] : null;
        if ($mode === 'scheduled' && ($scheduledAt === null || trim($scheduledAt) === '')) {
            return $this->json($response, ['error' => 'scheduled_at is required for scheduled mode'], 422);
        }

        if (!$this->serverService->schedulePowerCommand($id, $action, $mode, $scheduledAt)) {
            $server = $this->serverService->findById($id);
            if ($server !== null && $this->serverService->isContainerHost($server)) {
                return $this->json($response, [
                    'error' => 'Power commands are disabled for container workloads. Untrust to remove this node.',
                ], 403);
            }

            return $this->json($response, ['error' => 'Server not found, not trusted, or invalid schedule time'], 404);
        }

        return $this->json($response, [
            'status' => $action === 'reboot' ? 'reboot_scheduled' : 'shutdown_scheduled',
            'mode' => $mode,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
