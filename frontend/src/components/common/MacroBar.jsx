export default function MacroBar({ label, current, target, color = '#22C55E' }) {
  const pct = target > 0 ? Math.min((current / target) * 100, 100) : 0;

  return (
    <div style={{ marginBottom: '12px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
        <span style={{ fontSize: '14px', fontWeight: 500, color: '#0F172A' }}>{label}</span>
        <span style={{ fontSize: '14px', color: '#64748B' }}>{current}g / {target}g</span>
      </div>
      <div style={{ height: '8px', background: '#E2E8F0', borderRadius: '4px', overflow: 'hidden' }}>
        <div
          style={{
            width: `${pct}%`,
            height: '100%',
            background: color,
            borderRadius: '4px',
            transition: 'width 0.4s ease',
          }}
        />
      </div>
    </div>
  );
}
