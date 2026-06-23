import { FormEvent, useEffect, useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type AlertRules = {
  min_severity: string;
  alert_on: string;
  cooldown_minutes: number;
  slack_header?: string;
  slack_body_template?: string;
  slack_footer_template?: string;
};

type SlackPreview = {
  text: string;
  blocks: Array<{
    type: string;
    text?: { text?: string };
    elements?: Array<{ text?: string }>;
  }>;
};

type Webhook = {
  id: number;
  name: string;
  url: string;
  enabled: number;
  slack_format?: number;
  slack_channel?: string | null;
  slack_username?: string | null;
  slack_icon_url?: string | null;
};

type AlertEvent = {
  id: number;
  channel: string;
  status: string;
  summary: string;
  sent_at: string;
};

export function AlertsPage() {
  const { token, user } = useAuth();
  const [rules, setRules] = useState<AlertRules | null>(null);
  const [templateVariables, setTemplateVariables] = useState<string[]>([]);
  const [slackPreview, setSlackPreview] = useState<SlackPreview | null>(null);
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [events, setEvents] = useState<AlertEvent[]>([]);
  const [message, setMessage] = useState('');

  const [slackName, setSlackName] = useState('Slack alerts');
  const [slackUrl, setSlackUrl] = useState('');
  const [slackChannel, setSlackChannel] = useState('');
  const [slackUsername, setSlackUsername] = useState('PatchDashboard');
  const [slackIconUrl, setSlackIconUrl] = useState('');

  const [webhookName, setWebhookName] = useState('');
  const [webhookUrl, setWebhookUrl] = useState('');

  async function loadWebhooks() {
    if (!token) return;
    const data = await api<{ webhooks: Webhook[] }>('/api/v1/alerts/webhooks', {}, token);
    setWebhooks(data.webhooks);
  }

  useEffect(() => {
    if (!token) return;
    api<{ rules: AlertRules }>('/api/v1/alerts/rules', {}, token).then((data) => setRules(data.rules));
    api<{ variables: string[] }>('/api/v1/alerts/slack/template-defaults', {}, token).then((data) =>
      setTemplateVariables(data.variables),
    );
    loadWebhooks();
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
    setMessage('Alert rules saved.');
  }

  async function saveSlackTemplate(event: FormEvent) {
    event.preventDefault();
    if (!token || !rules || !user?.admin) return;
    const data = await api<{ rules: AlertRules }>(
      '/api/v1/alerts/rules',
      {
        method: 'PUT',
        body: JSON.stringify({
          min_severity: rules.min_severity,
          alert_on: rules.alert_on,
          cooldown_minutes: rules.cooldown_minutes,
          slack_header: rules.slack_header ?? '',
          slack_body_template: rules.slack_body_template ?? '',
          slack_footer_template: rules.slack_footer_template ?? '',
        }),
      },
      token,
    );
    setRules(data.rules);
    setMessage('Slack card template saved.');
  }

  async function previewSlackTemplate() {
    if (!token || !rules || !user?.admin) return;
    const data = await api<{ preview: SlackPreview }>(
      '/api/v1/alerts/slack/preview',
      {
        method: 'POST',
        body: JSON.stringify({
          slack_header: rules.slack_header ?? '',
          slack_body_template: rules.slack_body_template ?? '',
          slack_footer_template: rules.slack_footer_template ?? '',
        }),
      },
      token,
    );
    setSlackPreview(data.preview);
  }

  async function resetSlackTemplate() {
    if (!token) return;
    const data = await api<{ template: { header: string; body: string; footer: string } }>(
      '/api/v1/alerts/slack/template-defaults',
      {},
      token,
    );
    setRules((current) =>
      current
        ? {
            ...current,
            slack_header: data.template.header,
            slack_body_template: data.template.body,
            slack_footer_template: data.template.footer,
          }
        : current,
    );
    setSlackPreview(null);
    setMessage('Slack template reset to defaults (save to apply).');
  }

  async function addSlack(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    await api(
      '/api/v1/alerts/webhooks',
      {
        method: 'POST',
        body: JSON.stringify({
          name: slackName,
          url: slackUrl,
          enabled: true,
          slack_format: true,
          slack_channel: slackChannel || null,
          slack_username: slackUsername || 'PatchDashboard',
          slack_icon_url: slackIconUrl || null,
        }),
      },
      token,
    );
    setSlackUrl('');
    setSlackIconUrl('');
    await loadWebhooks();
    setMessage('Slack webhook added.');
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
    await loadWebhooks();
    setMessage('Generic webhook added.');
  }

  async function testWebhook(id: number) {
    if (!token) return;
    const result = await api<{ success: boolean; message: string }>(
      `/api/v1/alerts/webhooks/${id}/test`,
      { method: 'POST' },
      token,
    );
    setMessage(result.message);
    const data = await api<{ events: AlertEvent[] }>('/api/v1/alerts/events', {}, token);
    setEvents(data.events);
  }

  async function toggleWebhook(webhook: Webhook) {
    if (!token) return;
    await api(
      `/api/v1/alerts/webhooks/${webhook.id}`,
      { method: 'PATCH', body: JSON.stringify({ enabled: !webhook.enabled }) },
      token,
    );
    await loadWebhooks();
  }

  async function removeWebhook(id: number) {
    if (!token || !confirm('Remove this webhook?')) return;
    await api(`/api/v1/alerts/webhooks/${id}`, { method: 'DELETE' }, token);
    await loadWebhooks();
    setMessage('Webhook removed.');
  }

  const slackWebhooks = webhooks.filter((w) => w.slack_format);
  const genericWebhooks = webhooks.filter((w) => !w.slack_format);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Alerts</h1>
        <p className="text-slate-400">
          Email users with &ldquo;receive alerts&rdquo; enabled, and notify Slack or other webhooks for container CVEs.
        </p>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

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

      {user?.admin ? (
        <form onSubmit={saveSlackTemplate} className="card space-y-4">
          <div>
            <h2 className="text-lg font-medium">Slack card template</h2>
            <p className="mt-1 text-sm text-slate-400">
              Customize the Block Kit layout sent to Slack. Use Slack mrkdwn in the body/footer. Variables are
              replaced when alerts fire.
            </p>
          </div>

          <div className="flex flex-wrap gap-2 text-xs text-slate-400">
            {templateVariables.map((variable) => (
              <code key={variable} className="rounded bg-slate-950 px-2 py-1">
                {variable}
              </code>
            ))}
          </div>

          <label className="block space-y-1 text-sm">
            <span>Header (plain text, max 150 chars)</span>
            <input
              className="input"
              value={rules?.slack_header ?? ''}
              onChange={(e) =>
                setRules((current) => ({ ...(current ?? rules!), slack_header: e.target.value }))
              }
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>Body template</span>
            <textarea
              className="input min-h-32 font-mono text-xs"
              value={rules?.slack_body_template ?? ''}
              onChange={(e) =>
                setRules((current) => ({ ...(current ?? rules!), slack_body_template: e.target.value }))
              }
            />
          </label>

          <label className="block space-y-1 text-sm">
            <span>Footer template</span>
            <input
              className="input"
              value={rules?.slack_footer_template ?? ''}
              onChange={(e) =>
                setRules((current) => ({ ...(current ?? rules!), slack_footer_template: e.target.value }))
              }
            />
          </label>

          <div className="flex flex-wrap gap-2">
            <button className="btn btn-primary" type="submit">
              Save template
            </button>
            <button className="btn btn-secondary" type="button" onClick={previewSlackTemplate}>
              Preview
            </button>
            <button className="btn btn-secondary" type="button" onClick={resetSlackTemplate}>
              Reset to defaults
            </button>
          </div>

          {slackPreview ? (
            <div className="rounded-lg border border-slate-800 bg-slate-950 p-4 text-sm">
              <div className="mb-2 text-xs uppercase tracking-wide text-slate-500">Preview</div>
              <div className="mb-3 text-base font-semibold text-sky-300">
                {slackPreview.blocks[0]?.text?.text ?? 'Header'}
              </div>
              <pre className="whitespace-pre-wrap text-slate-200">
                {slackPreview.blocks[1]?.text?.text ?? slackPreview.text}
              </pre>
              {slackPreview.blocks[2]?.elements?.[0]?.text ? (
                <div className="mt-3 text-xs text-slate-500">{slackPreview.blocks[2].elements[0].text}</div>
              ) : null}
            </div>
          ) : null}
        </form>
      ) : null}

      <div className="card space-y-4">
        <div>
          <h2 className="text-lg font-medium">Slack integration</h2>
          <p className="mt-1 text-sm text-slate-400">
            Create an{' '}
            <a
              className="text-sky-300 hover:underline"
              href="https://api.slack.com/messaging/webhooks"
              target="_blank"
              rel="noreferrer"
            >
              incoming webhook
            </a>{' '}
            in your Slack workspace and paste the URL below. Alerts use Block Kit formatting with CVE details.
          </p>
        </div>

        <form onSubmit={addSlack} className="grid gap-4 md:grid-cols-2">
          <label className="space-y-1 text-sm">
            <span>Name</span>
            <input className="input" value={slackName} onChange={(e) => setSlackName(e.target.value)} />
          </label>
          <label className="space-y-1 text-sm md:col-span-2">
            <span>Incoming webhook URL</span>
            <input
              className="input"
              placeholder="https://hooks.slack.com/services/..."
              value={slackUrl}
              onChange={(e) => setSlackUrl(e.target.value)}
              required
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>Channel override (optional)</span>
            <input
              className="input"
              placeholder="#security-alerts"
              value={slackChannel}
              onChange={(e) => setSlackChannel(e.target.value)}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>Bot display name</span>
            <input className="input" value={slackUsername} onChange={(e) => setSlackUsername(e.target.value)} />
          </label>
          <label className="space-y-1 text-sm md:col-span-2">
            <span>Bot profile picture URL</span>
            <input
              className="input"
              type="url"
              placeholder="https://example.com/patchdashboard-icon.png"
              value={slackIconUrl}
              onChange={(e) => setSlackIconUrl(e.target.value)}
            />
            <span className="block text-xs text-slate-500">
              Public HTTPS image URL (PNG/JPG/GIF). Slack uses this as the bot avatar via{' '}
              <code className="text-slate-400">icon_url</code>.
            </span>
          </label>
          {slackIconUrl ? (
            <div className="flex items-center gap-3 md:col-span-2">
              <img
                src={slackIconUrl}
                alt="Slack bot preview"
                className="h-10 w-10 rounded-full border border-slate-700 bg-slate-900 object-cover"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                }}
              />
              <span className="text-xs text-slate-500">Preview (must be reachable by Slack&apos;s servers)</span>
            </div>
          ) : null}
          <div className="md:col-span-2">
            <button className="btn btn-primary" type="submit">
              Connect Slack
            </button>
          </div>
        </form>

        {slackWebhooks.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="text-slate-400">
                <tr>
                  <th className="pb-2 pr-4">Name</th>
                  <th className="pb-2 pr-4">Channel</th>
                  <th className="pb-2 pr-4">Avatar</th>
                  <th className="pb-2 pr-4">Enabled</th>
                  <th className="pb-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                {slackWebhooks.map((webhook) => (
                  <tr key={webhook.id} className="border-t border-slate-800">
                    <td className="py-3 pr-4">{webhook.name}</td>
                    <td className="py-3 pr-4">{webhook.slack_channel || 'Default'}</td>
                    <td className="py-3 pr-4">
                      {webhook.slack_icon_url ? (
                        <img
                          src={webhook.slack_icon_url}
                          alt=""
                          className="h-8 w-8 rounded-full border border-slate-700 object-cover"
                        />
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="py-3 pr-4">{webhook.enabled ? 'Yes' : 'No'}</td>
                    <td className="py-3">
                      <div className="flex flex-wrap gap-2">
                        <button className="btn btn-secondary" type="button" onClick={() => testWebhook(webhook.id)}>
                          Test
                        </button>
                        <button className="btn btn-secondary" type="button" onClick={() => toggleWebhook(webhook)}>
                          {webhook.enabled ? 'Disable' : 'Enable'}
                        </button>
                        <button className="btn btn-secondary" type="button" onClick={() => removeWebhook(webhook.id)}>
                          Remove
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <form onSubmit={addWebhook} className="card grid gap-4 md:grid-cols-3">
        <div className="md:col-span-3">
          <h2 className="text-lg font-medium">Generic webhooks</h2>
          <p className="mt-1 text-sm text-slate-400">JSON payloads for custom integrations.</p>
        </div>
        <label className="space-y-1 text-sm">
          <span>Webhook name</span>
          <input className="input" value={webhookName} onChange={(e) => setWebhookName(e.target.value)} />
        </label>
        <label className="space-y-1 text-sm md:col-span-2">
          <span>Webhook URL</span>
          <input className="input" value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} />
        </label>
        <div className="flex items-end">
          <button className="btn btn-primary" type="submit">
            Add webhook
          </button>
        </div>
      </form>

      {genericWebhooks.length > 0 ? (
        <div className="card overflow-x-auto">
          <h2 className="mb-4 text-lg font-medium">Generic webhook endpoints</h2>
          <table className="min-w-full text-left text-sm">
            <thead className="text-slate-400">
              <tr>
                <th className="pb-2 pr-4">Name</th>
                <th className="pb-2 pr-4">URL</th>
                <th className="pb-2 pr-4">Enabled</th>
                <th className="pb-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {genericWebhooks.map((webhook) => (
                <tr key={webhook.id} className="border-t border-slate-800">
                  <td className="py-3 pr-4">{webhook.name}</td>
                  <td className="py-3 pr-4">{webhook.url}</td>
                  <td className="py-3 pr-4">{webhook.enabled ? 'Yes' : 'No'}</td>
                  <td className="py-3">
                    <div className="flex flex-wrap gap-2">
                      <button className="btn btn-secondary" type="button" onClick={() => testWebhook(webhook.id)}>
                        Test
                      </button>
                      <button className="btn btn-secondary" type="button" onClick={() => toggleWebhook(webhook)}>
                        {webhook.enabled ? 'Disable' : 'Enable'}
                      </button>
                      <button className="btn btn-secondary" type="button" onClick={() => removeWebhook(webhook.id)}>
                        Remove
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

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
                <td className="py-3 pr-4 capitalize">{event.channel}</td>
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
