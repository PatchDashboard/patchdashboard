import { api } from './api';

export type PowerMode = 'graceful' | 'forced' | 'scheduled';
export type PowerAction = 'reboot' | 'shutdown';

export type PowerCommandState = {
  power_cmd_action?: string | null;
  power_cmd_mode?: string | null;
  power_cmd_scheduled_at?: string | null;
  reboot_cmd_sent?: number;
  shutdown_cmd_sent?: number;
};

export type SchedulePowerOptions = {
  mode: PowerMode;
  scheduledAt?: string;
};

export async function scheduleServerReboot(
  serverId: number,
  token: string,
  options: SchedulePowerOptions,
) {
  return scheduleServerPower(serverId, 'reboot', token, options);
}

export async function scheduleServerShutdown(
  serverId: number,
  token: string,
  options: SchedulePowerOptions,
) {
  return scheduleServerPower(serverId, 'shutdown', token, options);
}

async function scheduleServerPower(
  serverId: number,
  action: PowerAction,
  token: string,
  options: SchedulePowerOptions,
) {
  const body: Record<string, string> = { mode: options.mode };
  if (options.mode === 'scheduled' && options.scheduledAt) {
    body.scheduled_at = options.scheduledAt;
  }
  return api<{ status: string; mode: PowerMode; scheduled_at?: string }>(
    `/api/v1/servers/${serverId}/${action}`,
    { method: 'POST', body: JSON.stringify(body) },
    token,
  );
}

export function serverPowerPending(server: PowerCommandState): string | null {
  if (server.power_cmd_action) {
    const mode = server.power_cmd_mode ?? 'graceful';
    const when =
      mode === 'scheduled' && server.power_cmd_scheduled_at
        ? ` @ ${server.power_cmd_scheduled_at}`
        : '';
    const label = server.power_cmd_action === 'shutdown' ? 'Power off' : 'Reboot';
    return `${label} (${mode})${when}`;
  }
  if (server.shutdown_cmd_sent) {
    return 'Power off (graceful)';
  }
  if (server.reboot_cmd_sent) {
    return 'Reboot (graceful)';
  }
  return null;
}

export function defaultScheduledAtLocal(): string {
  const date = new Date(Date.now() + 60 * 60 * 1000);
  date.setSeconds(0, 0);
  const offset = date.getTimezoneOffset();
  const local = new Date(date.getTime() - offset * 60 * 1000);
  return local.toISOString().slice(0, 16);
}

export function scheduledAtToIso(localValue: string): string {
  return new Date(localValue).toISOString();
}
