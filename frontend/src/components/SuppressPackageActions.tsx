import { useState } from 'react';
import { addSuppression, isPackageSuppressed, type Suppression } from '../lib/suppressions';
import { useAuth } from '../lib/auth';

type SuppressPackageActionsProps = {
  packageName: string;
  serverName?: string;
  serverAlias?: string;
  suppressions: Suppression[];
  onSuppressed?: (suppression: Suppression) => void;
  /** When the caller already knows this package is suppressed (e.g. patch list). */
  alreadySuppressed?: boolean;
};

export function SuppressPackageActions({
  packageName,
  serverName,
  serverAlias,
  suppressions,
  onSuppressed,
  alreadySuppressed = false,
}: SuppressPackageActionsProps) {
  const { token } = useAuth();
  const [busy, setBusy] = useState<'global' | 'server' | null>(null);
  const suppressed =
    alreadySuppressed || isPackageSuppressed(suppressions, packageName, serverName);

  if (suppressed) {
    return <span className="text-xs text-orange-300">Suppressed</span>;
  }

  async function suppress(scope: 'global' | 'server') {
    if (!token) return;
    setBusy(scope);
    try {
      const targetServer = scope === 'global' ? '0' : serverName!;
      const created = await addSuppression(token, packageName, targetServer);
      onSuppressed?.(created);
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="flex flex-wrap gap-1">
      {serverName ? (
        <button
          type="button"
          className="btn btn-secondary px-2 py-1 text-xs"
          disabled={busy !== null}
          onClick={() => suppress('server')}
          title={serverAlias ? `Suppress on ${serverAlias}` : 'Suppress on this server'}
        >
          {busy === 'server' ? '…' : 'This server'}
        </button>
      ) : null}
      <button
        type="button"
        className="btn btn-secondary px-2 py-1 text-xs"
        disabled={busy !== null}
        onClick={() => suppress('global')}
        title="Suppress on all servers"
      >
        {busy === 'global' ? '…' : 'All servers'}
      </button>
    </div>
  );
}
