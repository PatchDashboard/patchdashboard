import { useEffect, useState } from 'react';
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
};

export function UsersPage() {
  const { token, user } = useAuth();
  const [users, setUsers] = useState<User[]>([]);

  useEffect(() => {
    if (!token || !user?.admin) return;
    api<{ users: User[] }>('/api/v1/users', {}, token).then((data) => setUsers(data.users));
  }, [token, user]);

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Users</h1>
        <p className="text-slate-400">Administrative user accounts.</p>
      </div>

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Username</th>
              <th className="pb-2 pr-4">Email</th>
              <th className="pb-2 pr-4">Admin</th>
              <th className="pb-2">Active</th>
            </tr>
          </thead>
          <tbody>
            {users.map((entry) => (
              <tr key={entry.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{entry.user_id}</td>
                <td className="py-3 pr-4">{entry.email}</td>
                <td className="py-3 pr-4">{entry.admin ? 'Yes' : 'No'}</td>
                <td className="py-3">{entry.active ? 'Yes' : 'No'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
