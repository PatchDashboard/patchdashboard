import { FormEvent, useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type User = {
  id: number;
  user_id: string;
  email: string;
  admin: number;
  display_name: string;
  active: number;
  receive_alerts: number;
  last_seen?: string;
};

const emptyForm = {
  user_id: '',
  email: '',
  display_name: '',
  password: '',
  admin: false,
  active: true,
  receive_alerts: false,
};

export function UsersPage() {
  const { token, user } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [editing, setEditing] = useState<User | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState(emptyForm);

  async function loadUsers() {
    if (!token) return;
    const data = await api<{ users: User[] }>('/api/v1/users', {}, token);
    setUsers(data.users);
  }

  useEffect(() => {
    if (!token || !user?.admin) return;
    loadUsers();
  }, [token, user]);

  function openCreate() {
    setCreating(true);
    setEditing(null);
    setForm(emptyForm);
  }

  function openEdit(entry: User) {
    setEditing(entry);
    setCreating(false);
    setForm({
      user_id: entry.user_id,
      email: entry.email,
      display_name: entry.display_name,
      password: '',
      admin: Boolean(entry.admin),
      active: Boolean(entry.active),
      receive_alerts: Boolean(entry.receive_alerts),
    });
  }

  async function saveUser(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    if (creating) {
      await api(
        '/api/v1/users',
        {
          method: 'POST',
          body: JSON.stringify({
            user_id: form.user_id,
            email: form.email,
            display_name: form.display_name || form.user_id,
            password: form.password,
            admin: form.admin,
            active: form.active,
            receive_alerts: form.receive_alerts,
          }),
        },
        token,
      );
    } else if (editing) {
      const body: Record<string, unknown> = {
        email: form.email,
        display_name: form.display_name,
        admin: form.admin,
        active: form.active,
        receive_alerts: form.receive_alerts,
      };
      if (form.password) {
        body.password = form.password;
      }
      await api(`/api/v1/users/${editing.id}`, { method: 'PATCH', body: JSON.stringify(body) }, token);
    }
    setCreating(false);
    setEditing(null);
    setForm(emptyForm);
    await loadUsers();
  }

  async function removeUser(entry: User) {
    if (!token || !confirm(`Delete user ${entry.user_id}?`)) return;
    await api(`/api/v1/users/${entry.id}`, { method: 'DELETE' }, token);
    await loadUsers();
  }

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Users</h1>
          <p className="text-slate-400">Administrative user accounts.</p>
        </div>
        <button className="btn btn-primary" type="button" onClick={openCreate}>
          Add user
        </button>
      </div>

      {(creating || editing) && (
        <form onSubmit={saveUser} className="card grid gap-4 md:grid-cols-2">
          <label className="space-y-1 text-sm">
            <span>Username</span>
            <input
              className="input"
              value={form.user_id}
              readOnly={!creating}
              onChange={(e) => setForm({ ...form, user_id: e.target.value })}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>Display name</span>
            <input
              className="input"
              value={form.display_name}
              onChange={(e) => setForm({ ...form, display_name: e.target.value })}
            />
          </label>
          <label className="space-y-1 text-sm">
            <span>Email</span>
            <input className="input" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </label>
          <label className="space-y-1 text-sm">
            <span>{creating ? 'Password' : 'New password (optional)'}</span>
            <input
              className="input"
              type="password"
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
            />
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.admin} onChange={(e) => setForm({ ...form, admin: e.target.checked })} />
            Admin
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.active}
              onChange={(e) => setForm({ ...form, active: e.target.checked })}
            />
            Active
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.receive_alerts}
              onChange={(e) => setForm({ ...form, receive_alerts: e.target.checked })}
            />
            Receive alerts
          </label>
          <div className="flex items-end gap-2 md:col-span-2">
            <button className="btn btn-primary" type="submit">
              Save
            </button>
            <button
              className="btn btn-secondary"
              type="button"
              onClick={() => {
                setCreating(false);
                setEditing(null);
              }}
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Username</th>
              <th className="pb-2 pr-4">Email</th>
              <th className="pb-2 pr-4">Admin</th>
              <th className="pb-2 pr-4">Active</th>
              <th className="pb-2 pr-4">Alerts</th>
              <th className="pb-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map((entry) => (
              <tr key={entry.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{entry.user_id}</td>
                <td className="py-3 pr-4">{entry.email}</td>
                <td className="py-3 pr-4">{entry.admin ? 'Yes' : 'No'}</td>
                <td className="py-3 pr-4">{entry.active ? 'Yes' : 'No'}</td>
                <td className="py-3 pr-4">{entry.receive_alerts ? 'Yes' : 'No'}</td>
                <td className="py-3">
                  <div className="flex gap-2">
                    <button className="btn btn-secondary" type="button" onClick={() => openEdit(entry)}>
                      Edit
                    </button>
                    <button className="btn btn-secondary" type="button" onClick={() => removeUser(entry)}>
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
