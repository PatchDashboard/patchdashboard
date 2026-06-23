export function PieChart({ percent, label }: { percent: number; label: string }) {
  const clamped = Math.max(0, Math.min(100, percent));
  return (
    <div className="flex flex-col items-center gap-3">
      <div
        className="relative flex h-36 w-36 items-center justify-center rounded-full"
        style={{
          background: `conic-gradient(#38bdf8 ${clamped * 3.6}deg, #1e293b ${clamped * 3.6}deg)`,
        }}
      >
        <div className="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-slate-900 text-center">
          <div className="text-2xl font-semibold text-sky-300">{clamped}%</div>
          <div className="text-[10px] uppercase tracking-wide text-slate-400">healthy</div>
        </div>
      </div>
      <div className="text-sm text-slate-400">{label}</div>
    </div>
  );
}
