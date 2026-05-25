import { useEffect, useState } from 'react';
import { getJournal, searchAliments, addRepas, deleteRepas } from '../api/nutrition';
import CalorieCircle from '../components/common/CalorieCircle';

const TYPES = ['petit-dejeuner', 'dejeuner', 'diner', 'encas'];
const TYPE_LABELS = { 'petit-dejeuner': 'Petit-déjeuner', 'dejeuner': 'Déjeuner', 'diner': 'Dîner', 'encas': 'En-cas' };

export default function Nutrition() {
  const [date, setDate]         = useState(new Date().toISOString().split('T')[0]);
  const [journal, setJournal]   = useState(null);
  const [search, setSearch]     = useState('');
  const [results, setResults]   = useState([]);
  const [selected, setSelected] = useState(null);
  const [quantite, setQuantite] = useState('100');
  const [typeRepas, setTypeRepas] = useState('dejeuner');
  const [loading, setLoading]   = useState(false);

  const loadJournal = () =>
    getJournal(date).then((r) => setJournal(r.data)).catch(() => setJournal(null));

  useEffect(() => { loadJournal(); }, [date]);

  const handleSearch = async () => {
    if (search.length < 2) return;
    const r = await searchAliments(search);
    setResults(r.data);
  };

  const handleAdd = async () => {
    if (!selected) return;
    setLoading(true);
    try {
      await addRepas({ alimentId: selected.id, quantiteG: parseFloat(quantite), typeRepas, date });
      setSelected(null); setSearch(''); setResults([]);
      await loadJournal();
    } finally { setLoading(false); }
  };

  const handleDelete = async (id) => {
    await deleteRepas(id);
    await loadJournal();
  };

  const totaux = journal?.totaux ?? {};
  const target = 2000;

  return (
    <div style={styles.page}>
      <header style={styles.header}>
        <h1 style={styles.title}>Nutrition</h1>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <button onClick={() => setDate(d => prev(d))} style={styles.navBtn}>◀</button>
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} style={styles.dateInput} />
          <button onClick={() => setDate(d => next(d))} style={styles.navBtn}>▶</button>
        </div>
      </header>

      {/* Résumé calories */}
      <div style={styles.summary}>
        <CalorieCircle consumed={Math.round(totaux.calories ?? 0)} target={target} />
        <div style={{ marginLeft: '24px' }}>
          {['proteines', 'glucides', 'lipides'].map((k) => (
            <div key={k} style={{ marginBottom: '6px', fontSize: '14px' }}>
              <strong>{capitalize(k)} :</strong>{' '}
              <span style={{ color: '#22C55E' }}>{Math.round(totaux[k] ?? 0)}g</span>
            </div>
          ))}
        </div>
      </div>

      {/* Recherche aliment */}
      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Ajouter un aliment</h2>
        <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
          <input
            style={{ ...styles.input, flex: 1 }}
            placeholder="Rechercher un aliment…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          />
          <button style={styles.btn} onClick={handleSearch}>Chercher</button>
        </div>

        {results.length > 0 && (
          <ul style={styles.resultList}>
            {results.map((a) => (
              <li
                key={a.id}
                onClick={() => setSelected(a)}
                style={{ ...styles.resultItem, background: selected?.id === a.id ? '#F0FDF4' : '#fff', border: selected?.id === a.id ? '1px solid #22C55E' : '1px solid #E2E8F0' }}
              >
                <strong>{a.nom}</strong>
                <span style={{ color: '#64748B', fontSize: '13px' }}> — {a.calories100g} kcal/100g</span>
              </li>
            ))}
          </ul>
        )}

        {selected && (
          <div style={{ display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap' }}>
            <input style={{ ...styles.input, width: '90px' }} type="number" value={quantite} onChange={(e) => setQuantite(e.target.value)} placeholder="g" min="1" />
            <select style={styles.input} value={typeRepas} onChange={(e) => setTypeRepas(e.target.value)}>
              {TYPES.map(t => <option key={t} value={t}>{TYPE_LABELS[t]}</option>)}
            </select>
            <button style={{ ...styles.btn, opacity: loading ? 0.7 : 1 }} onClick={handleAdd} disabled={loading}>
              {loading ? '…' : 'Ajouter'}
            </button>
          </div>
        )}
      </div>

      {/* Journal */}
      {TYPES.map((type) => {
        const lignes = (journal?.lignes ?? []).filter((l) => l.typeRepas === type);
        if (!lignes.length) return null;
        return (
          <div key={type} style={styles.card}>
            <h2 style={styles.cardTitle}>{TYPE_LABELS[type]}</h2>
            {lignes.map((l) => (
              <div key={l.id} style={styles.ligne}>
                <div>
                  <p style={{ fontWeight: 500 }}>{l.aliment}</p>
                  <p style={{ fontSize: '13px', color: '#64748B' }}>{l.quantiteG}g — {Math.round(l.calories)} kcal</p>
                </div>
                <button onClick={() => handleDelete(l.id)} style={styles.deleteBtn}>✕</button>
              </div>
            ))}
          </div>
        );
      })}
    </div>
  );
}

const prev = (d) => { const dt = new Date(d); dt.setDate(dt.getDate() - 1); return dt.toISOString().split('T')[0]; };
const next = (d) => { const dt = new Date(d); dt.setDate(dt.getDate() + 1); return dt.toISOString().split('T')[0]; };
const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);

const styles = {
  page:       { padding: '24px', maxWidth: '800px', margin: '0 auto' },
  header:     { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' },
  title:      { fontSize: '30px', fontWeight: 700 },
  navBtn:     { background: '#E2E8F0', border: 'none', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer' },
  dateInput:  { padding: '6px 10px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  summary:    { display: 'flex', alignItems: 'center', background: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  card:       { background: '#fff', borderRadius: '12px', padding: '20px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle:  { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em' },
  input:      { padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  btn:        { padding: '8px 16px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontWeight: 600 },
  resultList: { listStyle: 'none', marginTop: '8px', maxHeight: '200px', overflowY: 'auto' },
  resultItem: { padding: '8px 12px', borderRadius: '6px', cursor: 'pointer', marginBottom: '4px' },
  ligne:      { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0', borderBottom: '1px solid #F1F5F9' },
  deleteBtn:  { background: 'none', border: 'none', color: '#94A3B8', cursor: 'pointer', fontSize: '16px' },
};
