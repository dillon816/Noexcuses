import { useEffect, useState } from 'react';
import { getScore, calculateScore, logSommeil, getSommeilHistory } from '../api/recovery';

export default function Recovery() {
  const [score, setScore]       = useState(null);
  const [sleep, setSleep]       = useState({ heuresSommeil: '', qualite: '4', dateNuit: new Date().toISOString().split('T')[0] });
  const [loading, setLoading]           = useState(false);
  const [msg, setMsg]                   = useState('');
  const [error, setError]               = useState('');
  const [sommeilHistory, setSommeilHistory] = useState([]);

  useEffect(() => {
    getScore()
      .then((r) => setScore(r.data))
      .catch(() => setError('Impossible de charger le score de récupération.'));
    getSommeilHistory()
      .then((r) => setSommeilHistory(r.data))
      .catch(() => {});
  }, []);

  const handleCalc = async () => {
    setLoading(true);
    setError('');
    try {
      const r = await calculateScore();
      setScore(r.data);
    } catch {
      setError('Erreur lors du calcul du score. Vérifiez que vous avez enregistré du sommeil.');
    } finally { setLoading(false); }
  };

  const handleSleep = async (e) => {
    e.preventDefault();
    setError('');
    try {
      await logSommeil({ heuresSommeil: parseFloat(sleep.heuresSommeil), qualite: parseInt(sleep.qualite), dateNuit: sleep.dateNuit });
      setMsg('Sommeil enregistré !');
      setSleep({ ...sleep, heuresSommeil: '' });
      setTimeout(() => setMsg(''), 3000);
      getSommeilHistory().then((r) => setSommeilHistory(r.data));
    } catch {
      setError('Erreur lors de l\'enregistrement du sommeil.');
    }
  };

  const color = score?.score >= 70 ? '#22C55E' : score?.score >= 40 ? '#F59E0B' : '#EA580C';

  // Calcul détail des points (même logique que le backend)
  const computeBreakdown = (s) => {
    if (!s) return null;
    const h = parseFloat(s.heuresSommeilMoy ?? 0);
    const def = Math.abs(parseFloat(s.bilanCalorique ?? 0));
    const ch = parseFloat(s.charge7j ?? 0);
    const sommeilPts = h >= 8 ? 40 : h >= 7 ? 34 : h >= 6 ? 24 : h >= 5 ? 12 : 0;
    const calPts     = def <= 200 ? 30 : def <= 400 ? 22 : def <= 600 ? 14 : def <= 1000 ? 6 : 0;
    const chargePts  = ch === 0 ? 20 : ch <= 5000 ? 30 : ch <= 10000 ? 22 : ch <= 20000 ? 12 : 4;
    return { sommeilPts, calPts, chargePts };
  };
  const breakdown = computeBreakdown(score);

  const today = new Date().toISOString().split('T')[0];
  const isToday = sleep.dateNuit === today;

  return (
    <div className="page-wrap tight" style={{ margin: '0 auto' }}>
      <h1 style={styles.title}>Recovery Budget</h1>
      <p style={{ fontSize: '14px', color: '#64748B', marginBottom: '12px', lineHeight: '1.6' }}>
        Ton score du jour (0–100) reflète ta capacité à t'entraîner. Il est calculé automatiquement à partir de trois facteurs :
      </p>
      <div style={{ display: 'flex', gap: '10px', marginBottom: '20px', flexWrap: 'wrap' }}>
        <div style={styles.factorCard}>
          <span style={styles.factorIcon}>😴</span>
          <p style={styles.factorLabel}>Sommeil</p>
          <p style={styles.factorPts}>40 pts</p>
          <p style={styles.factorDesc}>Moyenne sur 7 nuits</p>
        </div>
        <div style={styles.factorCard}>
          <span style={styles.factorIcon}>🍽️</span>
          <p style={styles.factorLabel}>Bilan calorique</p>
          <p style={styles.factorPts}>30 pts</p>
          <p style={styles.factorDesc}>Calories du jour vs objectif</p>
        </div>
        <div style={styles.factorCard}>
          <span style={styles.factorIcon}>🏋️</span>
          <p style={styles.factorLabel}>Charge 7j</p>
          <p style={styles.factorPts}>30 pts</p>
          <p style={styles.factorDesc}>Tonnage cumulé cette semaine</p>
        </div>
      </div>

      {error && <div style={styles.errorBanner}>{error}</div>}

      {/* Score */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Score de récupération</h2>
        {score?.score != null ? (
          <div style={{ textAlign: 'center', padding: '24px 0' }}>
            <div style={{ fontSize: '72px', fontWeight: 700, color, lineHeight: 1 }}>{score.score}<span style={{ fontSize: '24px', color: '#94A3B8' }}>/100</span></div>
            <div style={{ fontSize: '18px', fontWeight: 600, color, marginTop: '8px' }}>{score.recommandation}</div>
            <div style={{ fontSize: '13px', color: '#64748B', marginTop: '4px' }}>Calculé le {score.dateCalcul}</div>

            <div style={{ display: 'flex', justifyContent: 'center', gap: '24px', marginTop: '20px', flexWrap: 'wrap' }}>
              <Stat label="Charge 7j" value={`${score.charge7j ?? 0} kg`} />
              <Stat label="Bilan calorique" value={`${score.bilanCalorique > 0 ? '+' : ''}${score.bilanCalorique ?? 0} kcal`} />
              <Stat label="Sommeil moy." value={`${score.heuresSommeilMoy ?? 0}h`} />
            </div>

            {breakdown && (
              <div style={{ marginTop: '20px', display: 'flex', justifyContent: 'center', gap: '12px', flexWrap: 'wrap' }}>
                <PtsBadge label="Sommeil" pts={breakdown.sommeilPts} max={40} />
                <PtsBadge label="Calories" pts={breakdown.calPts}    max={30} />
                <PtsBadge label="Charge"  pts={breakdown.chargePts}  max={30} />
              </div>
            )}
          </div>
        ) : (
          <p style={{ color: '#64748B', textAlign: 'center', padding: '20px 0' }}>
            Aucun score calculé. Enregistre ton sommeil puis clique sur "Calculer".
          </p>
        )}
        <div style={{ textAlign: 'center', marginTop: '8px' }}>
          <button style={{ ...styles.btn, opacity: loading ? 0.7 : 1 }} onClick={handleCalc} disabled={loading}>
            {loading ? 'Calcul…' : '⟳ Calculer le score'}
          </button>
        </div>
      </div>

      {/* Log sommeil */}
      <div style={styles.card}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={styles.cardTitle}>Sommeil</h2>
          {isToday && <span style={styles.updBadge}>Mise à jour du jour</span>}
        </div>
        <p style={{ fontSize: '13px', color: '#94A3B8', marginTop: '4px', marginBottom: '8px' }}>
          {isToday
            ? 'Si tu as déjà enregistré ce soir, re-soumettre mettra à jour l\'entrée existante.'
            : 'Tu peux corriger le sommeil d\'une nuit passée en changeant la date.'}
        </p>
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
          {parseFloat(sleep.heuresSommeil) > 0 && parseFloat(sleep.heuresSommeil) < 6 && parseInt(sleep.qualite) >= 4 && (
            <div style={styles.warnBanner}>
              ⚠️ {parseFloat(sleep.heuresSommeil)}h de sommeil reste insuffisant même avec une bonne qualité subjective. Le score de récupération en tiendra compte.
            </div>
          )}
          <button style={{ ...styles.btn, marginTop: '16px' }} type="submit">
            {isToday ? 'Enregistrer / Mettre à jour' : 'Enregistrer'}
          </button>
        </form>
      </div>

      {/* Historique sommeil */}
      {sommeilHistory.length > 0 && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Historique — 14 derniers jours</h2>
          <div style={{ marginTop: '12px' }}>
            {sommeilHistory.map((s) => {
              const q = ['😴','😪','😐','🙂','😄'][s.qualite - 1];
              const c = s.heuresSommeil >= 8 ? '#22C55E' : s.heuresSommeil >= 6 ? '#F59E0B' : '#EA580C';
              return (
                <div key={s.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0', borderBottom: '1px solid #F1F5F9' }}>
                  <span style={{ fontSize: '13px', color: '#64748B' }}>{s.dateNuit}</span>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <span style={{ fontWeight: 700, color: c, fontSize: '15px' }}>{s.heuresSommeil}h</span>
                    <span style={{ fontSize: '18px' }} title={`Qualité : ${s.qualite}/5`}>{q}</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Interprétation */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Interprétation du score</h2>
        <div style={{ marginTop: '12px' }}>
          <ScoreRange range="70–100" label="Séance normale"    color="#22C55E" desc="Tu es bien récupéré. Entraînement intensif possible." />
          <ScoreRange range="40–69"  label="Séance légère"     color="#F59E0B" desc="Récupération partielle. Privilégie cardio léger ou mobilité." />
          <ScoreRange range="0–39"   label="Repos recommandé"  color="#EA580C" desc="Fatigue accumulée. Repose-toi pour éviter le surentraînement." />
        </div>
        <div style={{ marginTop: '16px', padding: '12px', background: '#F8FAFC', borderRadius: '8px', fontSize: '13px', color: '#64748B', lineHeight: '1.7' }}>
          <b style={{ color: '#0F172A' }}>Comment est calculé le score ?</b><br />
          Sommeil moyen 7j : 8h+ = 40 pts, 7h = 34, 6h = 24, 5h = 12, moins = 0<br />
          Bilan calorique du jour : écart &lt; 200 kcal = 30 pts, &lt; 400 = 22, &lt; 600 = 14, &lt; 1000 = 6<br />
          Charge 7j (tonnage) : 1–5000 kg = 30 pts, 5–10t = 22, 10–20t = 12, plus = 4, zéro = 20
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

function PtsBadge({ label, pts, max }) {
  const pct = Math.round((pts / max) * 100);
  const c = pct >= 70 ? '#22C55E' : pct >= 40 ? '#F59E0B' : '#EA580C';
  return (
    <div style={{ textAlign: 'center', background: '#F8FAFC', borderRadius: '10px', padding: '10px 16px', minWidth: '80px' }}>
      <p style={{ fontSize: '18px', fontWeight: 700, color: c }}>{pts}<span style={{ fontSize: '12px', color: '#94A3B8' }}>/{max}</span></p>
      <p style={{ fontSize: '11px', color: '#64748B' }}>{label}</p>
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
  success:     { background: '#F0FDF4', color: '#16A34A', padding: '10px', borderRadius: '8px', fontSize: '14px', marginBottom: '8px' },
  errorBanner: { background: '#FEF2F2', color: '#DC2626', padding: '10px 14px', borderRadius: '8px', fontSize: '14px', marginBottom: '12px' },
  updBadge:    { fontSize: '11px', background: '#EFF6FF', color: '#3B82F6', padding: '3px 10px', borderRadius: '20px', fontWeight: 600 },
  warnBanner:  { background: '#FFFBEB', color: '#92400E', border: '1px solid #FDE68A', borderRadius: '8px', padding: '10px 14px', fontSize: '13px', marginTop: '12px', lineHeight: '1.5' },
  factorCard:  { flex: 1, minWidth: '130px', background: '#fff', borderRadius: '12px', padding: '16px', textAlign: 'center', boxShadow: '0 1px 4px rgba(0,0,0,0.06)', borderTop: '3px solid #22C55E' },
  factorIcon:  { fontSize: '24px' },
  factorLabel: { fontWeight: 600, fontSize: '13px', color: '#0F172A', marginTop: '8px' },
  factorPts:   { fontSize: '20px', fontWeight: 700, color: '#22C55E', margin: '4px 0' },
  factorDesc:  { fontSize: '11px', color: '#94A3B8' },
};
