import { useEffect, useState } from 'react';
import { getSeances, createSeance, getSeance, addSerie, updateSerie, deleteSerie, dupliquerSeance, terminerSeance, deleteSeance } from '../api/entrainement';

export default function Entrainement() {
  const [seances, setSeances]   = useState([]);
  const [active, setActive]     = useState(null);
  const [view, setView]         = useState('list');
  const [form, setForm]         = useState({ nom: '', date: new Date().toISOString().split('T')[0] });
  const [serieForm, setSerieForm]   = useState({ exerciceNom: '', repetitions: '', chargeKg: '', nbSeries: '3' });
  const [editing, setEditing]       = useState(null); // { id, repetitions, chargeKg }
  const [loading, setLoading]   = useState(false);

  const [filtre, setFiltre] = useState('tout'); // 'tout' | 'semaine' | 'mois' | 'custom'
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo]   = useState('');

  const load = () => getSeances(100).then((r) => setSeances(r.data));

  useEffect(() => { load(); }, []);

  const handleCreate = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const r = await createSeance(form);
      const detail = await getSeance(r.data.id);
      setActive(detail.data);
      setView('detail');
      await load();
    } catch {
      alert('Erreur lors de la création de la séance.');
    } finally { setLoading(false); }
  };

  const handleAddSerie = async (e) => {
    e.preventDefault();
    if (!active) return;
    try {
      await addSerie(active.id, {
        exerciceNom: serieForm.exerciceNom,
        repetitions: parseInt(serieForm.repetitions),
        chargeKg: parseFloat(serieForm.chargeKg),
        nbSeries: parseInt(serieForm.nbSeries) || 1,
      });
      const r = await getSeance(active.id);
      setActive(r.data);
      setSerieForm({ exerciceNom: '', repetitions: '', chargeKg: '', nbSeries: '3' });
    } catch {
      alert('Erreur lors de l\'ajout de la série.');
    }
  };

  const handleUpdateSerie = async (serieId) => {
    try {
      await updateSerie(active.id, serieId, { repetitions: parseInt(editing.repetitions), chargeKg: parseFloat(editing.chargeKg) });
      const r = await getSeance(active.id);
      setActive(r.data);
      setEditing(null);
    } catch { alert('Erreur lors de la modification.'); }
  };

  const handleDeleteSerie = async (serieId) => {
    try {
      await deleteSerie(active.id, serieId);
      const r = await getSeance(active.id);
      setActive(r.data);
    } catch { alert('Erreur lors de la suppression.'); }
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

  const handleDupliquer = async (e, id) => {
    e.stopPropagation();
    try {
      const r = await dupliquerSeance(id);
      const detail = await getSeance(r.data.id);
      await load();
      setActive(detail.data);
      setView('detail');
    } catch {
      alert('Erreur lors de la duplication de la séance.');
    }
  };

  if (view === 'create') return (
    <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
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
    <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
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
          <div className="table-scroll">
          <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '12px', fontSize: '14px', minWidth: '420px' }}>
            <thead>
              <tr style={{ color: '#64748B', textAlign: 'left' }}>
                <th style={styles.th}>Exercice</th>
                <th style={styles.th}>Reps</th>
                <th style={styles.th}>Charge</th>
                <th style={{ ...styles.th, color: '#22C55E' }}>Tonnage</th>
                <th style={styles.th}></th>
              </tr>
            </thead>
            <tbody>
              {active.series.map((s) => (
                <tr key={s.id} style={{ borderTop: '1px solid #F1F5F9' }}>
                  <td style={styles.td}><strong>{s.exercice}</strong></td>
                  {editing?.id === s.id ? (
                    <>
                      <td style={styles.td}>
                        <input type="number" min="1" value={editing.repetitions}
                          onChange={(e) => setEditing({ ...editing, repetitions: e.target.value })}
                          style={{ ...styles.inlineInput, width: '60px' }} />
                      </td>
                      <td style={styles.td}>
                        <input type="number" min="0" step="0.5" value={editing.chargeKg}
                          onChange={(e) => setEditing({ ...editing, chargeKg: e.target.value })}
                          style={{ ...styles.inlineInput, width: '70px' }} />
                      </td>
                      <td style={{ ...styles.td, color: '#22C55E', fontWeight: 600 }}>
                        {Math.round(parseInt(editing.repetitions || 0) * parseFloat(editing.chargeKg || 0))} kg
                      </td>
                      <td style={styles.td}>
                        <button onClick={() => handleUpdateSerie(s.id)} style={styles.saveBtn}>✓</button>
                        <button onClick={() => setEditing(null)} style={styles.cancelBtn}>✕</button>
                      </td>
                    </>
                  ) : (
                    <>
                      <td style={styles.td}>{s.repetitions}</td>
                      <td style={styles.td}>{s.chargeKg} kg</td>
                      <td style={{ ...styles.td, color: '#22C55E', fontWeight: 600 }}>{s.tonnage} kg</td>
                      <td style={styles.td}>
                        <button onClick={() => setEditing({ id: s.id, repetitions: s.repetitions, chargeKg: s.chargeKg })} style={styles.iconBtn}>✏️</button>
                        <button onClick={() => handleDeleteSerie(s.id)} style={{ ...styles.iconBtn, marginLeft: '4px' }}>🗑️</button>
                      </td>
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        </div>
      )}

      {/* Ajouter une série */}
      {active.statut === 'en_cours' && (
        <div style={styles.card}>
          <h2 style={styles.cardTitle}>Ajouter une série</h2>
          <form onSubmit={handleAddSerie} style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '12px', alignItems: 'center' }}>
            <input style={{ ...styles.input, flex: 2, minWidth: '140px' }} placeholder="Exercice (ex: Squat)" value={serieForm.exerciceNom} onChange={(e) => setSerieForm({ ...serieForm, exerciceNom: e.target.value })} required />
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '2px' }}>
              <input style={{ ...styles.input, width: '52px', textAlign: 'center' }} type="number" placeholder="3" min="1" max="20" value={serieForm.nbSeries} onChange={(e) => setSerieForm({ ...serieForm, nbSeries: e.target.value })} required />
              <span style={{ fontSize: '10px', color: '#94A3B8' }}>séries</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '2px' }}>
              <input style={{ ...styles.input, width: '62px', textAlign: 'center' }} type="number" placeholder="10" min="1" value={serieForm.repetitions} onChange={(e) => setSerieForm({ ...serieForm, repetitions: e.target.value })} required />
              <span style={{ fontSize: '10px', color: '#94A3B8' }}>reps</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '2px' }}>
              <input style={{ ...styles.input, width: '70px', textAlign: 'center' }} type="number" placeholder="0" min="0" step="0.5" value={serieForm.chargeKg} onChange={(e) => setSerieForm({ ...serieForm, chargeKg: e.target.value })} required />
              <span style={{ fontSize: '10px', color: '#94A3B8' }}>kg</span>
            </div>
            <button style={styles.btn} type="submit">+ Ajouter</button>
          </form>
        </div>
      )}
    </div>
  );

  // --- Filtrage client-side ---
  const seancesFiltrees = seances.filter((s) => {
    const d = s.dateSeance; // 'YYYY-MM-DD'
    const today = new Date().toISOString().split('T')[0];

    if (filtre === 'semaine') {
      const lundi = new Date();
      lundi.setDate(lundi.getDate() - lundi.getDay() + (lundi.getDay() === 0 ? -6 : 1));
      return d >= lundi.toISOString().split('T')[0] && d <= today;
    }
    if (filtre === 'mois') {
      const debut = new Date();
      debut.setDate(1);
      return d >= debut.toISOString().split('T')[0] && d <= today;
    }
    if (filtre === 'custom') {
      if (dateFrom && d < dateFrom) return false;
      if (dateTo   && d > dateTo)   return false;
      return true;
    }
    return true; // 'tout'
  });

  return (
    <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', gap: '12px' }}>
        <h1 style={styles.title}>Entraînements</h1>
        <button style={styles.btn} onClick={() => setView('create')}>+ Nouvelle séance</button>
      </div>

      {/* Barre de filtres */}
      <div style={styles.filterBar}>
        <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', alignItems: 'center' }}>
          {[
            { key: 'tout',    label: 'Tout' },
            { key: 'semaine', label: 'Cette semaine' },
            { key: 'mois',    label: 'Ce mois' },
            { key: 'custom',  label: 'Période…' },
          ].map(({ key, label }) => (
            <button
              key={key}
              onClick={() => setFiltre(key)}
              style={{ ...styles.filterBtn, ...(filtre === key ? styles.filterBtnActive : {}) }}
            >{label}</button>
          ))}
        </div>

        {filtre === 'custom' && (
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center', marginTop: '10px', flexWrap: 'wrap' }}>
            <label style={{ fontSize: '13px', color: '#64748B' }}>Du</label>
            <input type="date" style={styles.dateInput} value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
            <label style={{ fontSize: '13px', color: '#64748B' }}>au</label>
            <input type="date" style={styles.dateInput} value={dateTo}   onChange={(e) => setDateTo(e.target.value)} />
            {(dateFrom || dateTo) && (
              <button style={styles.clearBtn} onClick={() => { setDateFrom(''); setDateTo(''); }}>✕ Effacer</button>
            )}
          </div>
        )}

        <p style={{ fontSize: '13px', color: '#94A3B8', marginTop: '8px' }}>
          {seancesFiltrees.length} séance{seancesFiltrees.length !== 1 ? 's' : ''}
          {filtre !== 'tout' ? ' sur cette période' : ' au total'}
        </p>
      </div>

      {seances.length === 0 ? (
        <p style={{ color: '#64748B' }}>Aucune séance. Commencez dès maintenant !</p>
      ) : seancesFiltrees.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '40px 0', color: '#94A3B8' }}>
          <p style={{ fontSize: '32px', marginBottom: '8px' }}>📭</p>
          <p style={{ fontWeight: 500 }}>Aucune séance sur cette période</p>
          <button style={{ ...styles.btn, marginTop: '12px', background: 'none', color: '#22C55E', border: '1px solid #22C55E' }}
            onClick={() => { setFiltre('tout'); setDateFrom(''); setDateTo(''); }}>
            Voir toutes les séances
          </button>
        </div>
      ) : (
        seancesFiltrees.map((s) => (
          <div key={s.id} className="seance-row" onClick={() => { getSeance(s.id).then((r) => { setActive(r.data); setView('detail'); }); }}>
            <div style={{ minWidth: 0 }}>
              <p style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.nom}</p>
              <p style={{ fontSize: '13px', color: '#64748B' }}>{s.dateSeance} · {s.nbSeries} série{s.nbSeries !== 1 ? 's' : ''}</p>
            </div>
            <div className="seance-row-actions">
              <span style={{ color: '#22C55E', fontWeight: 700, whiteSpace: 'nowrap' }}>{s.tonnageTotal} kg</span>
              <span style={{ ...styles.badge, background: s.statut === 'terminee' ? '#F0FDF4' : '#FFF7ED', color: s.statut === 'terminee' ? '#16A34A' : '#EA580C', whiteSpace: 'nowrap' }}>
                {s.statut}
              </span>
              <button onClick={(e) => handleDupliquer(e, s.id)} style={styles.reuseBtn} title="Réutiliser cette séance">
                ↺
              </button>
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
  th:          { padding: '6px 8px', fontWeight: 600, fontSize: '13px' },
  td:          { padding: '8px', verticalAlign: 'middle' },
  inlineInput: { padding: '4px 6px', border: '1px solid #22C55E', borderRadius: '6px', fontSize: '14px' },
  saveBtn:     { background: '#22C55E', color: '#fff', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', fontWeight: 700 },
  cancelBtn:   { background: '#E2E8F0', color: '#64748B', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', marginLeft: '4px' },
  iconBtn:     { background: 'none', border: 'none', cursor: 'pointer', fontSize: '15px', padding: '2px 4px', borderRadius: '4px' },
  reuseBtn:      { padding: '4px 10px', background: '#EFF6FF', color: '#3B82F6', border: '1px solid #BFDBFE', borderRadius: '6px', fontSize: '12px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  filterBar:     { background: '#fff', borderRadius: '12px', padding: '14px 16px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  filterBtn:     { padding: '6px 14px', borderRadius: '20px', border: '1px solid #E2E8F0', background: '#F8FAFC', color: '#64748B', fontSize: '13px', fontWeight: 500, cursor: 'pointer' },
  filterBtnActive: { background: '#0F172A', color: '#fff', border: '1px solid #0F172A' },
  dateInput:     { padding: '5px 10px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '13px' },
  clearBtn:      { padding: '5px 10px', background: 'none', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '12px', color: '#94A3B8', cursor: 'pointer' },
};
