import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getDashboard } from '../api/progression';
import { getScore } from '../api/recovery';
import CalorieCircle from '../components/common/CalorieCircle';
import MacroBar from '../components/common/MacroBar';

export default function Dashboard() {
  const [data, setData]       = useState(null);
  const [recovery, setRecovery] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      getDashboard(),
      getScore().catch(() => ({ data: null })), // ne pas bloquer le dashboard si le score échoue
    ])
      .then(([d, r]) => { setData(d.data); setRecovery(r?.data ?? null); })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div style={styles.loading}>Chargement…</div>;

  const cal    = data?.caloriesConsommees  ?? 0;
  const target = data?.caloriesObjectif    ?? 2000;
  const macros = [
    { label: 'Protéines', current: Math.round(data?.proteines ?? 0), target: 150, color: '#22C55E' },
    { label: 'Glucides',  current: Math.round(data?.glucides  ?? 0), target: 250, color: '#3B82F6' },
    { label: 'Lipides',   current: Math.round(data?.lipides   ?? 0), target: 70,  color: '#F59E0B' },
    { label: 'Fibres',    current: Math.round(data?.fibres    ?? 0), target: 30,  color: '#8B5CF6' },
  ];

  const recoveryColor = recovery?.score >= 70 ? '#22C55E' : recovery?.score >= 40 ? '#F59E0B' : '#EA580C';

  return (
    <div className="page-wrap wide" style={{ margin: '0 auto' }}>
      <header style={styles.header}>
        <h1 style={styles.title}>Dashboard</h1>
        <p style={styles.date}>{new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}</p>
      </header>

      <div className="grid-cards">
        {/* Calories */}
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Calories du jour</h2>
          <div style={{ display: 'flex', justifyContent: 'center', margin: '16px 0' }}>
            <CalorieCircle consumed={cal} target={target} />
          </div>
          <p style={{ textAlign: 'center', fontSize: '14px', color: '#64748B' }}>{cal} kcal / {target} kcal</p>
        </div>

        {/* Macros */}
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Macronutriments</h2>
          <div style={{ marginTop: '16px' }}>
            {macros.map((m) => <MacroBar key={m.label} {...m} />)}
          </div>
        </div>

        {/* Recovery Score */}
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Recovery Score</h2>
          <div style={{ textAlign: 'center', padding: '24px 0' }}>
            <div style={{ fontSize: '56px', fontWeight: 700, color: recoveryColor, lineHeight: 1 }}>
              {recovery?.score ?? '—'}
            </div>
            <div style={{ marginTop: '8px', fontSize: '14px', fontWeight: 500, color: recoveryColor }}>
              {recovery?.recommandation ?? 'Calculez votre score'}
            </div>
          </div>
          <Link to="/recovery" style={styles.linkBtn}>Voir le détail →</Link>
        </div>

        {/* Dernière séance */}
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Dernière séance</h2>
          {data?.derniereSeance ? (
            <div style={{ padding: '16px 0' }}>
              <p style={{ fontWeight: 600, fontSize: '16px' }}>{data.derniereSeance.nom}</p>
              <p style={{ color: '#64748B', fontSize: '14px', marginTop: '4px' }}>{data.derniereSeance.date}</p>
              <p style={{ color: '#22C55E', fontWeight: 600, marginTop: '8px', fontSize: '18px' }}>
                {data.derniereSeance.tonnage} kg
              </p>
              <p style={{ color: '#64748B', fontSize: '12px' }}>tonnage total</p>
            </div>
          ) : (
            <p style={{ color: '#64748B', fontSize: '14px', padding: '16px 0' }}>Aucune séance enregistrée.</p>
          )}
          <Link to="/entrainement" style={styles.linkBtn}>Voir les séances →</Link>
        </div>
      </div>

      {/* Actions rapides */}
      <div className="dash-actions">
        <Link to="/nutrition" style={styles.actionBtn}>+ Ajouter un repas</Link>
        <Link to="/entrainement" style={{ ...styles.actionBtn, background: '#0F172A' }}>+ Ajouter une séance</Link>
      </div>
    </div>
  );
}

const styles = {
  page:      { padding: '24px', maxWidth: '1200px', margin: '0 auto' }, // kept for ref
  loading:   { display: 'flex', alignItems: 'center', justifyContent: 'center', height: '60vh', color: '#64748B' },
  header:    { marginBottom: '24px' },
  title:     { fontSize: '30px', fontWeight: 700, color: '#0F172A' },
  date:      { color: '#64748B', fontSize: '14px', marginTop: '4px', textTransform: 'capitalize' },
  grid:      { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' },
  card:      { background: '#fff', borderRadius: '12px', padding: '20px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle: { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em' },
  linkBtn:   { display: 'block', textAlign: 'center', marginTop: '12px', color: '#22C55E', fontSize: '14px', fontWeight: 500 },
  actions:   { display: 'flex', gap: '12px', marginTop: '24px', flexWrap: 'wrap' },
  actionBtn: { padding: '12px 24px', background: '#22C55E', color: '#fff', borderRadius: '8px', fontWeight: 600, fontSize: '15px' },
};
