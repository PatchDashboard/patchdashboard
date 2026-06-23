import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type Plugin = {
  id: number;
  name: string;
  disabled: number;
  installed: number;
  is_admin: number;
};

type PageMap = {
  id: number;
  page_name: string;
  real_file: string;
  plugin_name: string;
  on_navbar: number;
};

export function PluginsPage() {
  const { token, user } = useAuth();
  const [plugins, setPlugins] = useState<Plugin[]>([]);
  const [pages, setPages] = useState<PageMap[]>([]);

  useEffect(() => {
    if (!token || !user?.admin) return;
    api<{ plugins: Plugin[]; pages: PageMap[] }>('/api/v1/plugins', {}, token).then((data) => {
      setPlugins(data.plugins);
      setPages(data.pages);
    });
  }, [token, user]);

  async function togglePlugin(plugin: Plugin, field: 'disabled' | 'installed', value: boolean) {
    if (!token) return;
    const data = await api<{ plugin: Plugin }>(
      `/api/v1/plugins/${plugin.id}`,
      { method: 'PATCH', body: JSON.stringify({ [field]: value }) },
      token,
    );
    setPlugins((current) => current.map((p) => (p.id === plugin.id ? data.plugin : p)));
  }

  async function togglePage(page: PageMap, onNavbar: boolean) {
    if (!token) return;
    const data = await api<{ page: PageMap }>(
      `/api/v1/plugins/pages/${page.id}`,
      { method: 'PATCH', body: JSON.stringify({ on_navbar: onNavbar }) },
      token,
    );
    setPages((current) => current.map((p) => (p.id === page.id ? data.page : p)));
  }

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Plugins</h1>
        <p className="text-slate-400">Enable or disable legacy plugin modules and navbar pages.</p>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Plugin modules</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Name</th>
              <th className="pb-2 pr-4">Admin</th>
              <th className="pb-2 pr-4">Installed</th>
              <th className="pb-2">Enabled</th>
            </tr>
          </thead>
          <tbody>
            {plugins.map((plugin) => (
              <tr key={plugin.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{plugin.name}</td>
                <td className="py-3 pr-4">{plugin.is_admin ? 'Yes' : 'No'}</td>
                <td className="py-3 pr-4">
                  <input
                    type="checkbox"
                    checked={Boolean(plugin.installed)}
                    onChange={(e) => togglePlugin(plugin, 'installed', e.target.checked)}
                  />
                </td>
                <td className="py-3">
                  <input
                    type="checkbox"
                    checked={!plugin.disabled}
                    onChange={(e) => togglePlugin(plugin, 'disabled', !e.target.checked)}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Plugin pages</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Page</th>
              <th className="pb-2 pr-4">Plugin</th>
              <th className="pb-2 pr-4">File</th>
              <th className="pb-2">On navbar</th>
            </tr>
          </thead>
          <tbody>
            {pages.map((page) => (
              <tr key={page.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{page.page_name}</td>
                <td className="py-3 pr-4">{page.plugin_name}</td>
                <td className="py-3 pr-4">{page.real_file}</td>
                <td className="py-3">
                  <input
                    type="checkbox"
                    checked={Boolean(page.on_navbar)}
                    onChange={(e) => togglePage(page, e.target.checked)}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
