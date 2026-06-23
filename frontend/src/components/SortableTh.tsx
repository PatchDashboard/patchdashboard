import clsx from 'clsx';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { SortState } from '../lib/tableSort';

type SortableThProps = {
  label: string;
  sortKey: string;
  sort: SortState;
  onSort: (key: string) => void;
  className?: string;
};

export function SortableTh({ label, sortKey, sort, onSort, className }: SortableThProps) {
  const active = sort?.key === sortKey;

  return (
    <th className={clsx('pb-2 pr-4', className)}>
      <button
        type="button"
        className={clsx(
          'inline-flex items-center gap-1 transition',
          active ? 'text-slate-200' : 'text-slate-400 hover:text-slate-200',
        )}
        onClick={() => onSort(sortKey)}
      >
        {label}
        {active ? (
          sort.dir === 'asc' ? (
            <ArrowUp size={14} aria-hidden />
          ) : (
            <ArrowDown size={14} aria-hidden />
          )
        ) : (
          <ArrowUpDown size={14} className="opacity-40" aria-hidden />
        )}
      </button>
    </th>
  );
}
