import { api } from './api';

export type Suppression = {
  id: number;
  package_name: string;
  server_name: string;
};

export async function fetchSuppressions(token: string): Promise<Suppression[]> {
  const data = await api<{ suppressions: Suppression[] }>('/api/v1/suppressions', {}, token);
  return data.suppressions;
}

export function isPackageSuppressed(
  suppressions: Suppression[],
  packageName: string,
  serverName?: string,
): boolean {
  return suppressions.some(
    (item) =>
      item.package_name === packageName &&
      (item.server_name === '0' || (serverName !== undefined && item.server_name === serverName)),
  );
}

export async function addSuppression(
  token: string,
  packageName: string,
  serverName: string,
): Promise<Suppression> {
  const data = await api<{ suppression: Suppression }>(
    '/api/v1/suppressions',
    {
      method: 'POST',
      body: JSON.stringify({ package_name: packageName, server_name: serverName }),
    },
    token,
  );
  return data.suppression;
}
