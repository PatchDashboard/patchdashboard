import { FormEvent, useEffect, useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type AlertRules = {
  min_severity: string;
  alert_on: string;
  cooldown_minutes: number;
};

type Webhook = {
  id: number;
  name: string;
  url: string;
  enabled: number;
};

type AlertEvent = {
  id: number;
  channel: string;
  status: string;
  summary: string;
  sent_at: string;
};

export function AlertsPage() {
  const { token } = useAuth();
  const [rules, setRules] = useState<AlertRules | null>(null);
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [events, setEvents] = useState<AlertEvent[]>([]);
  const [webhookName, setWebhookName] = useState('');
  const [webhookUrl, setWebhookUrl] = useState('');

  useEffect(() => {
    if (!token) return;
    api<{ rules: AlertRules }>('/api/v1/alerts/rules', {}, token).then((data) => setRules(data.rules));
    api<{ webhooks: Webhook[] }>('/api/v1/alerts/webhooks', {}, token).then((data) => setWebhooks(data.webhooks));
    api<{ events: AlertEvent[] }>('/api/v1/alerts/events', {}, token).then((data) => setEvents(data.events));
  }, [token]);

  async function saveRules(event: FormEvent) {
    event.preventDefault();
    if (!token || !rules) return;
    const data = await api<{ rules: AlertRules }>(
      '/api/v1/alerts/rules',
      { method: 'PUT', body: JSON.stringify(rules) },
      token,
    );
    setRules(data.rules);
  }

  async function addWebhook(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    await api(
      '/api/v1/alerts/webhooks',
      { method: 'POST', body: JSON.stringify({ name: webhookName, url: webhookUrl, enabled: true }) },
      token,
    );
    setWebhookName('');
    setWebhookUrl('');
    const data = await api<{ webhooks: Webhook[] }>('/api/v1/alerts/webhooks', {}, token);
    setWebhooks(data.webhooks);
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Alerts</h1>
        <p className="text-slate-400">Configure email and webhook notifications for container CVEs.</p>
      </div>

      <form onSubmit={saveRules} className="card grid gap-4 md:grid-cols-3">
        <label className="space-y-1 text-sm">
          <span>Minimum severity</span>
          <select
            className="input"
            value={rules?.min_severity ?? 'HIGH'}
            onChange={(e) => setRules((current) => ({ ...(current ?? rules!), min_severity: e.target.value }))}
          >
            <option>CRITICAL</option>
            <option>HIGH</option>
            <option>MEDIUM</option>
            <option>LOW</option>
          </select>
        </label>
        <label className="space-y-1 text-sm">
          <span>Cooldown (minutes)</span>
          <input
            className="input"
            type="number"
            value={rules?.cooldown_minutes ?? 60}
            onChange={(e) =>
              setRules((current) => ({
                ...(current ?? rules!),
                cooldown_minutes: Number(e.target.value),
              }))
            }
          />
        </label>
        <div className="flex items-end">
          <button className="btn btn-primary" type="submit">
            Save rules
          </button>
        </div>
      </form>

      <form onSubmit={addWebhook} className="card grid gap-4 md:grid-cols-3">
        <label className="space-y-1 text-sm">
          <span>Webhook name</span>
          <input className="input" value={webhookName} onChange={(e) => setWebhookName(e.target.value)} />
        </label>
        <label className="space-y-1 text-sm">
          <span>Webhook URL</span>
          <input className="input" value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} />
        </label>
        <div className="flex items-end">
          <button className="btn btn-primary" type="submit">
            Add webhook
          </button>
        </div>
      </form>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Webhooks</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Name</th>
              <th className="pb-2 pr-4">URL</th>
              <th className="pb-2">Enabled</th>
            </tr>
          </thead>
          <tbody>
            {webhooks.map((webhook) => (
              <tr key={webhook.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{webhook.name}</td>
                <td className="py-3 pr-4">{webhook.url}</td>
                <td className="py-3">{webhook.enabled ? 'Yes' : 'No'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Recent alert events</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Channel</th>
              <th className="pb-2 pr-4">Status</th>
              <th className="pb-2 pr-4">Summary</th>
              <th className="pb-2">Sent</th>
            </tr>
          </thead>
          <tbody>
            {events.map((event) => (
              <tr key={event.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{event.channel}</td>
                <td className="py-3 pr-4">{event.status}</td>
                <td className="py-3 pr-4">{event.summary}</td>
                <td className="py-3">{event.sent_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
