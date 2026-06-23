import { useState } from 'react';
import {
  defaultScheduledAtLocal,
  scheduledAtToIso,
  scheduleServerReboot,
  scheduleServerShutdown,
  type PowerAction,
  type PowerMode,
} from '../lib/serverPower';

type Props = {
  serverId: number;
  token: string;
  compact?: boolean;
  onScheduled?: (action: PowerAction, mode: PowerMode, scheduledAt?: string) => void;
};

export function ServerPowerControls({ serverId, token, compact = false, onScheduled }: Props) {
  const [action, setAction] = useState<PowerAction>('reboot');
  const [mode, setMode] = useState<PowerMode>('graceful');
  const [scheduledAt, setScheduledAt] = useState(defaultScheduledAtLocal);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit() {
    setBusy(true);
    setError('');
    try {
      const options =
        mode === 'scheduled'
          ? { mode, scheduledAt: scheduledAtToIso(scheduledAt) }
          : { mode };

      const label = action === 'reboot' ? 'reboot' : 'power off';
      const modeLabel = mode === 'scheduled' ? `scheduled for ${scheduledAt}` : mode;
      if (!confirm(`Queue ${label} (${modeLabel}) on the next agent check-in?`)) {
        return;
      }

      if (action === 'reboot') {
        await scheduleServerReboot(serverId, token, options);
      } else {
        await scheduleServerShutdown(serverId, token, options);
      }

      onScheduled?.(action, mode, mode === 'scheduled' ? scheduledAtToIso(scheduledAt) : undefined);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to queue power command.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={compact ? 'space-y-2' : 'card space-y-3'}>
      <div className={`grid gap-2 ${compact ? '' : 'md:grid-cols-4'}`}>
        <label className="space-y-1 text-sm">
          <span className="text-slate-400">Action</span>
          <select className="input" value={action} onChange={(e) => setAction(e.target.value as PowerAction)}>
            <option value="reboot">Reboot</option>
            <option value="shutdown">Power off</option>
          </select>
        </label>
        <label className="space-y-1 text-sm">
          <span className="text-slate-400">Mode</span>
          <select className="input" value={mode} onChange={(e) => setMode(e.target.value as PowerMode)}>
            <option value="graceful">Graceful</option>
            <option value="forced">Forced</option>
            <option value="scheduled">Scheduled</option>
          </select>
        </label>
        {mode === 'scheduled' ? (
          <label className="space-y-1 text-sm md:col-span-2">
            <span className="text-slate-400">Run at</span>
            <input
              className="input"
              type="datetime-local"
              value={scheduledAt}
              onChange={(e) => setScheduledAt(e.target.value)}
            />
          </label>
        ) : null}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <button className="btn btn-secondary" type="button" disabled={busy} onClick={submit}>
          {busy ? 'Queueing...' : 'Queue power command'}
        </button>
        {error ? <span className="text-sm text-red-300">{error}</span> : null}
      </div>
    </div>
  );
}
