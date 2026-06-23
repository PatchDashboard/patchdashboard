export type ServerRecord = {
  id: number;
  server_alias: string;
  server_name?: string;
  trusted?: number;
  is_container?: boolean;
  host_capabilities?: string | Record<string, unknown> | null;
};

export function isContainerServer(server: Pick<ServerRecord, 'is_container' | 'host_capabilities'>): boolean {
  if (server.is_container) {
    return true;
  }

  const capabilities = server.host_capabilities;
  if (!capabilities) {
    return false;
  }

  const parsed =
    typeof capabilities === 'string'
      ? (JSON.parse(capabilities) as Record<string, unknown>)
      : capabilities;

  return Boolean(parsed.container);
}

export const containerServerNotice =
  'Container workload — patch install, reboot, shutdown, and console are disabled. Untrust to remove this node from management.';
