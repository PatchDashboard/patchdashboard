-- Host OS and image scans use runtime host/image; older schema coerced them to docker.

ALTER TABLE containers
  MODIFY runtime ENUM('host', 'image', 'docker', 'podman', 'lxc') NOT NULL;

UPDATE containers
SET runtime = 'host'
WHERE runtime = 'docker'
  AND (
    runtime_id = 'rootfs'
    OR image_ref = 'os:rootfs'
    OR image_ref LIKE 'os:%'
  );

UPDATE containers
SET runtime = 'image'
WHERE runtime = 'docker'
  AND (
    runtime_id LIKE 'docker:%'
    OR runtime_id LIKE 'podman:%'
  );
