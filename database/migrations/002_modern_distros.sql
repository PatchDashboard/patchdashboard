-- 002: Modern distro versions and new distros

INSERT IGNORE INTO distro (id, distro_name, icon_path, upgrade_command, package_manager) VALUES
  (9, 'Rocky Linux', 'img/icon/rocky.png', 'dnf -y install', 'dnf'),
  (10, 'AlmaLinux', 'img/icon/almalinux.png', 'dnf -y install', 'dnf'),
  (11, 'CentOS Stream', 'img/icon/centos.png', 'dnf -y install', 'dnf');

UPDATE distro SET package_manager = 'dnf', upgrade_command = 'dnf -y install'
WHERE distro_name IN ('Fedora', 'RHEL', 'Oracle') AND package_manager = 'yum';

INSERT IGNORE INTO distro_version (version_num, distro_id, eol_date, codename) VALUES
  ('18.04_Desktop', 1, '2023-04-30', 'bionic'),
  ('18.04_Server', 1, '2023-04-30', 'bionic'),
  ('20.04_Desktop', 1, '2025-04-30', 'focal'),
  ('20.04_Server', 1, '2025-04-30', 'focal'),
  ('22.04_Desktop', 1, '2027-04-30', 'jammy'),
  ('22.04_Server', 1, '2027-04-30', 'jammy'),
  ('24.04_Desktop', 1, '2029-04-30', 'noble'),
  ('24.04_Server', 1, '2029-04-30', 'noble'),
  ('9', 2, '2022-07-18', 'stretch'),
  ('10', 2, '2024-06-30', 'buster'),
  ('11', 2, '2026-08-31', 'bullseye'),
  ('12', 2, '2028-06-30', 'bookworm'),
  ('13', 2, '2030-06-30', 'trixie'),
  ('8', 4, '2021-12-31', NULL),
  ('8', 5, '2029-05-31', NULL),
  ('9', 5, '2032-05-31', NULL),
  ('10', 5, '2035-05-31', NULL),
  ('8', 6, '2029-05-31', NULL),
  ('9', 6, '2032-05-31', NULL),
  ('10', 6, '2035-05-31', NULL),
  ('8', 9, '2029-05-31', NULL),
  ('9', 9, '2032-05-31', NULL),
  ('10', 9, '2035-05-31', NULL),
  ('8', 10, '2029-05-31', NULL),
  ('9', 10, '2032-05-31', NULL),
  ('10', 10, '2035-05-31', NULL),
  ('8', 11, '2029-05-31', NULL),
  ('9', 11, '2032-05-31', NULL);

INSERT IGNORE INTO distro_version (version_num, distro_id, eol_date, codename)
SELECT CAST(n.num AS CHAR), 3, '2099-12-31', NULL
FROM (
  SELECT 21 AS num UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
  UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
  UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35
  UNION SELECT 36 UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION SELECT 40
  UNION SELECT 41 UNION SELECT 42
) n
WHERE NOT EXISTS (
  SELECT 1 FROM distro_version dv WHERE dv.distro_id = 3 AND dv.version_num = CAST(n.num AS CHAR)
);
