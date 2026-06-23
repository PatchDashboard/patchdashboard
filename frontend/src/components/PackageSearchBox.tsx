import { FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

export function PackageSearchBox({ className }: { className?: string }) {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const defaultValue = params.get('q') ?? '';

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const query = String(form.get('q') ?? '').trim();
    if (query) {
      navigate(`/search?q=${encodeURIComponent(query)}`);
    }
  }

  return (
    <form onSubmit={onSubmit} className={className}>
      <input
        name="q"
        className="input"
        placeholder="Find servers with package..."
        defaultValue={defaultValue}
      />
      <div className="mt-2 text-xs text-slate-500">
        <Link className="text-sky-400 hover:underline" to={defaultValue ? `/vulnerabilities?q=${encodeURIComponent(defaultValue)}&server_id=all` : '/vulnerabilities'}>
          Search CVEs instead
        </Link>
      </div>
    </form>
  );
}
