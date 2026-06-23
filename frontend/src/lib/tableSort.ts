export type SortDir = 'asc' | 'desc';

export type SortState<K extends string = string> = {
  key: K;
  dir: SortDir;
} | null;

export function toggleSort<K extends string>(current: SortState<K>, key: K): SortState<K> {
  if (current?.key !== key) {
    return { key, dir: 'asc' };
  }
  return { key, dir: current.dir === 'asc' ? 'desc' : 'asc' };
}

export function severityRank(severity?: string | null): number {
  switch (severity?.toUpperCase()) {
    case 'CRITICAL':
      return 4;
    case 'HIGH':
      return 3;
    case 'MEDIUM':
      return 2;
    case 'LOW':
      return 1;
    default:
      return 0;
  }
}

function compareValues(a: unknown, b: unknown): number {
  if (typeof a === 'number' && typeof b === 'number') {
    return a - b;
  }
  return String(a ?? '').localeCompare(String(b ?? ''), undefined, { numeric: true, sensitivity: 'base' });
}

export function sortRows<T>(
  rows: T[],
  sort: SortState,
  getters: Record<string, (row: T) => unknown>,
): T[] {
  if (!sort) {
    return rows;
  }

  const getter = getters[sort.key];
  if (!getter) {
    return rows;
  }

  const sorted = [...rows].sort((left, right) => {
    const cmp = compareValues(getter(left), getter(right));
    return sort.dir === 'asc' ? cmp : -cmp;
  });

  return sorted;
}
