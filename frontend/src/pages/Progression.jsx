import { useEffect, useState } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, ComposedChart } from 'recharts';
import { getStatsPoids, getStatsCalories, getStatsEntrainement, logPoids } from '../api/progression';
import EmptyState from '../components/common/EmptyState';
import { useToast } from '../components/common/UIFeedback';

const TABS = ['Poids', 'Calories', 'Entraînement'];

// Tooltip du graphe d'entraînement : volume de la semaine, tendance et nombre de séances.
function TrainingTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null;
  const p = payload[0].payload;
  return (
    <div style={{ background: '#fff', border: '1px solid #E2E8F0', borderRadius: 8, padding: '8px 10px', fontSize: 12, boxShadow: '0 1px 4px rgba(0,0,0,0.08)' }}>
      <p style={{ fontWeight: 600, marginBottom: 4 }}>Semaine du {label}</p>
      <p>Volume : <strong>{p.tonnage} kg</strong></p>
      <p style={{ color: '#2563EB' }}>Tendance : {p.tendance} kg</p>
      <p style={{ color: '#64748B' }}>{p.nbSeances} séance{p.nbSeances !== 1 ? 's' : ''}</p>
    </div>
  );
}

export default function Progression() {
  const toast = useToast();
  const [tab, setTab]           = useState('Poids');
  const [poids, setPoids]       = useState([]);
  const [calories, setCalories] = useState([]);
  const [training, setTraining] = useState([]);
  const [newPoids, setNewPoids] = useState('');

  useEffect(() => {
    Promise.all([getStatsPoids(), getStatsCalories(), getStatsEntrainement()])
      .then(([p, c, t]) => { setPoids(p.data); setCalories(c.data); setTraining(t.data); })
      .catch(console.error);
  }, []);

  // Moyenne mobile sur 3 semaines pour tracer la tendance de charge.
  const trainingTrend = training.map((d, i, arr) => {
    const slice = arr.slice(Math.max(0, i - 2), i + 1);
    const avg = slice.reduce((s, x) => s + x.tonnage, 0) / slice.length;
    return { ...d, tendance: Math.round(avg) };
  });
  const hasTraining = training.some((d) => d.tonnage > 0);

  const handleLogPoids = async (e) => {
    e.preventDefault();
    try {
      await logPoids({ poidsKg: parseFloat(newPoids) });
      setNewPoids('');
      const r = await getStatsPoids();
      setPoids(r.data);
      toast('Poids enregistré !', 'success');
    } catch {
      toast('Erreur lors de l\'enregistrement du poids.', 'error');
    }
  };

  return (
    <div className="page-wrap mid" style={{ margin: '0 auto' }}>
      <h1 style={styles.title}>Progression</h1>

      {/* Tabs */}
      <div className="tabs-bar">
        {TABS.map((t) => (
          <button
            key={t}
            style={{ ...styles.tab, ...(tab === t ? styles.tabActive : {}) }}
            onClick={() => setTab(t)}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'Poids' && (
        <div>
          <div style={styles.card}>
            <h2 style={styles.cardTitle}>Évolution du poids (90 jours)</h2>
            {poids.length === 0 ? (
              <EmptyState
                icon="⚖️"
                title="Aucune pesée enregistrée"
                subtitle="Ajoute ton poids ci-dessous pour suivre son évolution dans le temps."
              />
            ) : (
              <ResponsiveContainer width="100%" height={260}>
                <LineChart data={poids} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#F1F5F9" />
                  <XAxis dataKey="date" tick={{ fontSize: 11 }} tickFormatter={(d) => d.slice(5)} />
                  <YAxis tick={{ fontSize: 11 }} domain={['auto', 'auto']} />
                  <Tooltip formatter={(v) => [`${v} kg`, 'Poids']} />
                  <Line type="monotone" dataKey="poidsKg" stroke="#22C55E" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </div>
          <div style={styles.card}>
            <h2 style={styles.cardTitle}>Enregistrer mon poids</h2>
            <form onSubmit={handleLogPoids} style={{ display: 'flex', gap: '10px', marginTop: '12px' }}>
              <input
                style={{ ...styles.input, width: '120px' }}
                type="number" step="0.1" min="20" max="500"
                placeholder="kg" value={newPoids}
                onChange={(e) => setNewPoids(e.target.value)} required
              />
              <button style={styles.btn} type="submit">Enregistrer</button>
            </form>
          </div>
        </div>
      )}

      {tab === 'Calories' && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Calories journalières (30 jours)</h2>
          {calories.length === 0 ? (
            <EmptyState
              icon="🍽️"
              title="Aucune donnée sur la période"
              subtitle="Enregistre tes repas dans Nutrition pour voir ici l'évolution de tes calories par rapport à ton objectif."
            />
          ) : (
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={calories} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#F1F5F9" />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} tickFormatter={(d) => d.slice(5)} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip formatter={(v, n) => [v, n === 'calories' ? 'Consommé (kcal)' : 'Objectif (kcal)']} />
                <Bar dataKey="calories"  fill="#22C55E" radius={[3, 3, 0, 0]} />
                <Bar dataKey="objectif"  fill="#E2E8F0" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </div>
      )}

      {tab === 'Entraînement' && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Charge d'entraînement par semaine (8 semaines)</h2>
          <p style={{ fontSize: '13px', color: '#94A3B8', marginTop: '-6px', marginBottom: '12px' }}>
            Volume total soulevé par semaine (séances terminées) et sa tendance sur 3 semaines.
          </p>
          {hasTraining ? (
            <ResponsiveContainer width="100%" height={280}>
              <ComposedChart data={trainingTrend} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#F1F5F9" />
                <XAxis dataKey="semaine" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip content={<TrainingTooltip />} />
                <Bar dataKey="tonnage" name="Volume" fill="#93C5FD" radius={[3, 3, 0, 0]} />
                <Line type="monotone" dataKey="tendance" name="Tendance" stroke="#2563EB" strokeWidth={2} dot={{ r: 3 }} />
              </ComposedChart>
            </ResponsiveContainer>
          ) : (
            <EmptyState
              icon="🏋️"
              title="Aucune séance terminée sur la période"
              subtitle="Termine des séances dans Entraînement pour suivre ici l'évolution de ta charge de travail."
            />
          )}
        </div>
      )}
    </div>
  );
}

const styles = {
  page:      { padding: '24px', maxWidth: '900px', margin: '0 auto' },
  title:     { fontSize: '30px', fontWeight: 700, marginBottom: '20px' },
  tabs:      { display: 'flex', gap: '8px', marginBottom: '20px' },
  tab:       { padding: '8px 20px', background: '#fff', border: '1px solid #E2E8F0', borderRadius: '8px', cursor: 'pointer', fontWeight: 500, fontSize: '14px' },
  tabActive: { background: '#22C55E', color: '#fff', border: '1px solid #22C55E' },
  card:      { background: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle: { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '12px' },
  input:     { padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  btn:       { padding: '8px 16px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontWeight: 600, cursor: 'pointer' },
  success:   { background: '#F0FDF4', color: '#16A34A', padding: '10px', borderRadius: '8px', fontSize: '14px', marginBottom: '8px' },
};
