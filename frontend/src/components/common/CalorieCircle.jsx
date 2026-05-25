export default function CalorieCircle({ consumed, target }) {
  const pct     = target > 0 ? Math.min(consumed / target, 1) : 0;
  const radius  = 70;
  const stroke  = 10;
  const norm    = radius - stroke / 2;
  const circ    = 2 * Math.PI * norm;
  const dash    = pct * circ;
  const remaining = Math.max(target - consumed, 0);

  return (
    <div style={{ position: 'relative', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
      <svg width={radius * 2} height={radius * 2} style={{ transform: 'rotate(-90deg)' }}>
        <circle cx={radius} cy={radius} r={norm} fill="none" stroke="#E2E8F0" strokeWidth={stroke} />
        <circle
          cx={radius} cy={radius} r={norm}
          fill="none"
          stroke="#22C55E"
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={`${dash} ${circ}`}
          style={{ transition: 'stroke-dasharray 0.5s ease' }}
        />
      </svg>
      <div style={{
        position: 'absolute',
        textAlign: 'center',
        lineHeight: 1.2,
      }}>
        <div style={{ fontSize: '22px', fontWeight: 700, color: '#0F172A' }}>{remaining}</div>
        <div style={{ fontSize: '12px', color: '#64748B' }}>kcal restantes</div>
      </div>
    </div>
  );
}
