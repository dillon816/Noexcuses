// État vide réutilisable : icône + titre + sous-titre + action optionnelle.
// Sert à remplacer les zones blanches (graphes ou listes sans données).
export default function EmptyState({ icon = '📭', title, subtitle, action }) {
  return (
    <div style={{ textAlign: 'center', padding: '40px 16px', color: '#94A3B8' }}>
      <div style={{ fontSize: 34, marginBottom: 8 }}>{icon}</div>
      <p style={{ fontWeight: 600, color: '#64748B' }}>{title}</p>
      {subtitle && <p style={{ fontSize: 13, marginTop: 4, maxWidth: 320, marginLeft: 'auto', marginRight: 'auto' }}>{subtitle}</p>}
      {action && <div style={{ marginTop: 14 }}>{action}</div>}
    </div>
  );
}
