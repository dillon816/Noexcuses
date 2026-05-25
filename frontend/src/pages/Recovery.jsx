import { useEffect, useState } from 'react';
import { getScore, calculateScore, logSommeil } from '../api/recovery';

export default function Recovery() {
  const [score, setScore]       = useState(null);
  const [sleep, setSleep]       = useState({ heuresSommeil: '', qualite: '4', dateNuit: new Date().toISOString().split('T')[0] });
  const [loading, setLoading]   = useState(false);
  const [msg, setMsg]           = useState('');

  useEffect(() => { getScore().then((r) => setScore(r.data)).catch(() => {}); }, []);

  const handleCalc = async () => {
    setLoading(true);
    try {
      const r = await calculateScore();
      setScore(r.data);
    } finally { setLoading(false); }
  };

  const handleSleep = async (e) => {
    e.preventDefault();
    await logSommeil({ heuresSommeil: parseFloat(sleep.heuresSommeil), qualite: parseInt(sleep.qualite), dateNuit: sleep.dateNuit });
    setMsg('Sommeil enregistré !');
    setTimeout(() => setMsg(''), 3000);
  };

  const color = score?.score >= 70 ? '#22C55E' : score?.score >= 40 ? '#F59E0B' : '#EA580C';

  return (
    <div style={styles.page}>
      <h1 style={styles.title}>Recovery Budget</h1>

      {/* Score */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Score de récupération</h2>
        {score?.score != null ? (
          <div style={{ textAlign: 'center', padding: '24px 0' }}>
            <div style={{ fontSize: '72px', fontWeight: 700, color, lineHeight: 1 }}>{score.score}</div>
            <div style={{ fontSize: '18px', fontWeight: 600, color, marginTop: '8px' }}>{score.recommandation}</div>
            <div style={{ fontSize: '13px', color: '#64748B', marginTop: '4px' }}>Calculé le {score.dateCalcul}</div>

            <div style={{ display: 'flex', justifyContent: 'center', gap: '24px', marginTop: '20px', flexWrap: 'wrap' }}>
              <Stat label="Charge 7j" value={`${score.charge7j ?? 0} kg`} />
              <Stat label="Bilan calorique" value={`${score.bilanCalorique > 0 ? '+' : ''}${score.bilanCalorique ?? 0} kcal`} />
              <Stat label="Sommeil moy." value={`${score.heuresSommeilMoy ?? 0}h`} />
            </div>
          </div>
        ) : (
          <p style={{ color: '#64748B', textAlign: 'center', padding: '20px 0' }}>Aucun score calculé pour aujourd'hui.</p>
        )}
        <div style={{ textAlign: 'center', marginTop: '8px' }}>
          <button style={{ ...styles.btn, opacity: loading ? 0.7 : 1 }} onClick={handleCalc} disabled={loading}>
            {loading ? 'Calcul…' : '⟳ Calculer le score'}
          </button>
        </div>
      </div>

      {/* Log sommeil */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Enregistrer le sommeil</h2>
        {msg && <div style={styles.success}>{msg}</div>}
        <form onSubmit={handleSleep}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: '12px', marginTop: '12px' }}>
            <div>
              <label style={styles.label}>Date</label>
              <input style={styles.input} type="date" value={sleep.dateNuit} onChange={(e) => setSleep({ ...sleep, dateNuit: e.target.value })} />
            </div>
            <div>
              <label style={styles.label}>Heures de sommeil</label>
              <input style={styles.input} type="number" step="0.5" min="0" max="24" value={sleep.heuresSommeil} onChange={(e) => setSleep({ ...sleep, heuresSommeil: e.target.value })} placeholder="7.5" required />
            </div>
            <div>
              <label style={styles.label}>Qualité (1–5)</label>
              <select style={styles.input} value={sleep.qualite} onChange={(e) => setSleep({ ...sleep, qualite: e.target.value })}>
                {[1, 2, 3, 4, 5].map(v => <option key={v} value={v}>{v} — {['Très mauvais', 'Mauvais', 'Moyen', 'Bon', 'Excellent'][v - 1]}</option>)}
              </select>
            </div>
          </div>
          <button style={{ ...styles.btn, marginTop: '16px' }} type="submit">Enregistrer</button>
        </form>
      </div>

      {/* Interprétation */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Interprétation du score</h2>
        <div style={{ marginTop: '12px' }}>
          <ScoreRange range="70–100" label="Séance normale" color="#22C55E" desc="Vous êtes bien récupéré. Entraînement intensif possible." />
          <ScoreRange range="40–69"  label="Séance légère"  color="#F59E0B" desc="Récupération partielle. Favorisez cardio léger ou mobilité." />
          <ScoreRange range="0–39"   label="Repos recommandé" color="#EA580C" desc="Fatigue accumulée. Reposez-vous pour éviter le surentraînement." />
        </div>
      </div>
    </div>
  );
}

function Stat({ label, value }) {
  return (
    <div style={{ textAlign: 'center' }}>
      <p style={{ fontSize: '18px', fontWeight: 700, color: '#0F172A' }}>{value}</p>
      <p style={{ fontSize: '12px', color: '#64748B' }}>{label}</p>
    </div>
  );
}

function ScoreRange({ range, label, color, desc }) {
  return (
    <div style={{ display: 'flex', gap: '12px', padding: '10px 0', borderBottom: '1px solid #F1F5F9' }}>
      <span style={{ minWidth: '60px', fontWeight: 700, color }}>{range}</span>
      <div>
        <p style={{ fontWeight: 600, color }}>{label}</p>
        <p style={{ fontSize: '13px', color: '#64748B' }}>{desc}</p>
      </div>
    </div>
  );
}

const styles = {
  page:      { padding: '24px', maxWidth: '700px', margin: '0 auto' },
  title:     { fontSize: '30px', fontWeight: 700, marginBottom: '20px' },
  card:      { background: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle: { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em' },
  label:     { display: 'block', fontSize: '14px', fontWeight: 500, marginBottom: '6px' },
  input:     { width: '100%', padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  btn:       { padding: '10px 20px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontWeight: 600, cursor: 'pointer' },
  success:   { background: '#F0FDF4', color: '#16A34A', padding: '10px', borderRadius: '8px', fontSize: '14px', marginBottom: '8px' },
};
