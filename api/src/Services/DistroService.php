<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class DistroService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function listWithVersions(): array
    {
        $distros = $this->pdo->query('SELECT * FROM distro ORDER BY distro_name')->fetchAll();
        $versions = $this->pdo->query('SELECT * FROM distro_version ORDER BY distro_id, version_num')->fetchAll();

        $byDistro = [];
        foreach ($versions as $version) {
            $byDistro[(int) $version['distro_id']][] = $version;
        }

        return array_map(function (array $distro) use ($byDistro) {
            $distro['versions'] = $byDistro[(int) $distro['id']] ?? [];
            return $distro;
        }, $distros);
    }
}
