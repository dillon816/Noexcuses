import { useEffect, useState } from 'react';
import { getSeances, createSeance, getSeance, addSerie, terminerSeance, deleteSeance } from '../api/entrainement';

export default function Entrainement() {
  const [seances, setSeances]   = useState([]);
  const [active, setActive]     = useState(null);
  const [view, setView]         = useState('list');
  const [form, setForm]         = useState({ nom: '', date: new Date().toISOString().split('T')[0] });
  const [serieForm, setSerieForm] = useState({ exerciceId: '', exerciceNom: '', repetitions: '', chargeKg: '' });
  const [loading, setLoading]   = useState(false);

  const load = () => getSeances().then((r) => setSeances(r.data));

  useEffect(() => { load(); }, []);

  const handleCreate = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const r = await createSeance(form);
      await load();
      const detail = await getSeance(r.data.id);
      setActive(detail.data);
      setView('detail');
    } finally { setLoading(false); }
  };

  const handleAddSerie = async (e) => {
    e.preventDefault();
    if (!active) return;
    await addSerie(active.id, {
      exerciceId: parseInt(serieForm.exerciceId) || 1,
      repetitions: parseInt(serieForm.repetitions),
      chargeKg: parseFloat(serieForm.chargeKg),
    });
    const r = await getSeance(active.id);
    setActive(r.data);
    setSerieForm({ exerciceId: '', exerciceNom: '', repetitions: '', chargeKg: '' });
  };

  const handleTerminer = async () => {
    await terminerSeance(active.id);
    const r = await getSeance(active.id);
    setActive(r.data);
    await load();
  };

  const handleDelete = async (id) => {
    await deleteSeance(id);
    await load();
    if (active?.id === id) { setActive(null); setView('list'); }
  };

  if (view === 'create') return (
    <div style={styles.page}>
      <button onClick={() => setView('list')} style={styles.back}>← Retour</button>
      <h1 style={styles.title}>Nouvelle séance</h1>
      <div style={styles.card}>
        <form onSubmit={handleCreate}>
          <label style={styles.label}>Nom de la séance</label>
          <input style={styles.input} value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} placeholder="Push day, Legs…" required />
          <label style={styles.label}>Date</label>
          <input style={styles.input} type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
          <button style={{ ...styles.btn, marginTop: '16px', opacity: loading ? 0.7 : 1 }} type="submit" disabled={loading}>
            {loading ? '…' : 'Créer et commencer'}
          </button>
        </form>
      </div>
    </div>
  );

  if (view === 'detail' && active) return (
    <div style={styles.page}>
      <button onClick={() => setView('list')} style={styles.back}>← Séances</button>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h1 style={styles.title}>{active.nom}</h1>
          <p style={{ color: '#64748B', fontSize: '14px' }}>{active.dateSeance} · {active.statut}</p>
          <p style={{ color: '#22C55E', fontWeight: 700, fontSize: '20px', marginTop: '4px' }}>{active.tonnageTotal} kg <span style={{ fontSize: '13px', color: '#64748B', fontWeight: 400 }}>tonnage</span></p>
        </div>
        {active.statut === 'en_cours' && (
          <button style={{ ...styles.btn, background: '#0F172A' }} onClick={handleTerminer}>✓ Terminer</button>
        )}
      </div>

      {/* Séries existantes */}
      {active.series?.length > 0 && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Séries</h2>
          <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '12px', fontSize: '14px' }}>
            <thead>
              <tr style={{ color: '#64748B', textAlign: 'left' }}>
                <th style={styles.th}>Exercice</th>
                <th style={styles.th}>Séries</th>
                <th style={styles.th}>Reps</th>
                <th style={styles.th}>Charge</th>
                <th style={styles.th}>Tonnage</th>
              </tr>
            </thead>
            <tbody>
              {active.series.map((s) => (
                <tr key={s.id} style={{ borderTop: '1px solid #F1F5F9' }}>
                  <td style={styles.td}>{s.exercice}</td>
                  <td style={styles.td}>{s.numeroSerie}</td>
                  <td style={styles.td}>{s.repetitions}</td>
                  <td style={styles.td}>{s.chargeKg} kg</td>
                  <td style={{ ...styles.td, color: '#22C55E', fontWeight: 600 }}>{s.tonnage} kg</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Ajouter une série */}
      {active.statut === 'en_cours' && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Ajouter une série</h2>
          <form onSubmit={handleAddSerie} style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '12px' }}>
            <input style={{ ...styles.input, flex: 2, minWidth: '120px' }} placeholder="Exercice ID (ex: 1)" value={serieForm.exerciceId} onChange={(e) => setSerieForm({ ...serieForm, exerciceId: e.target.value })} required />
            <input style={{ ...styles.input, width: '80px' }} type="number" placeholder="Reps" min="1" value={serieForm.repetitions} onChange={(e) => setSerieForm({ ...serieForm, repetitions: e.target.value })} required />
            <input style={{ ...styles.input, width: '90px' }} type="number" placeholder="kg" min="0" step="0.5" value={serieForm.chargeKg} onChange={(e) => setSerieForm({ ...serieForm, chargeKg: e.target.value })} required />
            <button style={styles.btn} type="submit">+ Ajouter</button>
          </form>
        </div>
      )}
    </div>
  );

  return (
    <div style={styles.page}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h1 style={styles.title}>Entraînements</h1>
        <button style={styles.btn} onClick={() => setView('create')}>+ Nouvelle séance</button>
      </div>

      {seances.length === 0 ? (
        <p style={{ color: '#64748B' }}>Aucune séance. Commencez dès maintenant !</p>
      ) : (
        seances.map((s) => (
          <div key={s.id} style={styles.seanceRow} onClick={() => { getSeance(s.id).then((r) => { setActive(r.data); setView('detail'); }); }}>
            <div>
              <p style={{ fontWeight: 600 }}>{s.nom}</p>
              <p style={{ fontSize: '13px', color: '#64748B' }}>{s.dateSeance} · {s.nbSeries} séries</p>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
              <span style={{ color: '#22C55E', fontWeight: 700 }}>{s.tonnageTotal} kg</span>
              <span style={{ ...styles.badge, background: s.statut === 'terminee' ? '#F0FDF4' : '#FFF7ED', color: s.statut === 'terminee' ? '#16A34A' : '#EA580C' }}>
                {s.statut}
              </span>
              <button onClick={(e) => { e.stopPropagation(); handleDelete(s.id); }} style={{ background: 'none', border: 'none', color: '#94A3B8', cursor: 'pointer' }}>✕</button>
            </div>
          </div>
        ))
      )}
    </div>
  );
}

const styles = {
  page:     { padding: '24px', maxWidth: '800px', margin: '0 auto' },
  title:    { fontSize: '30px', fontWeight: 700, color: '#0F172A' },
  back:     { background: 'none', border: 'none', color: '#22C55E', cursor: 'pointer', marginBottom: '12px', fontWeight: 500 },
  card:     { background: '#fff', borderRadius: '12px', padding: '20px', marginTop: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle: { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em' },
  label:    { display: 'block', fontSize: '14px', fontWeight: 500, marginBottom: '6px', marginTop: '14px' },
  input:    { padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  btn:      { padding: '10px 20px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontWeight: 600, cursor: 'pointer' },
  seanceRow: { background: '#fff', borderRadius: '10px', padding: '16px', marginBottom: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  badge:    { padding: '2px 8px', borderRadius: '20px', fontSize: '12px', fontWeight: 500 },
  th:       { padding: '6px 8px', fontWeight: 600, fontSize: '13px' },
  td:       { padding: '8px', verticalAlign: 'middle' },
};
