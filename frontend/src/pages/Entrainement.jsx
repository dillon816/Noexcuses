import { useEffect, useState } from 'react';
import {
  getSeances, getModeles, createSeance, createModele, getSeance,
  addSerie, updateSerie, deleteSerie, dupliquerSeance,
  terminerSeance, rouvrirSeance, updateSeance, deleteSeance,
} from '../api/entrainement';

const STATUT_INFO = {
  en_cours: { label: 'En cours', bg: '#FFF7ED', color: '#EA580C' },
  terminee: { label: 'Terminée', bg: '#F0FDF4', color: '#16A34A' },
  modele:   { label: 'Modèle',   bg: '#EFF6FF', color: '#2563EB' },
  archivee: { label: 'Archivée', bg: '#F1F5F9', color: '#64748B' },
};
const statutInfo = (s) => STATUT_INFO[s] || { label: s, bg: '#F1F5F9', color: '#64748B' };

const TYPES_SEANCE = ['Push', 'Pull', 'Legs', 'Upper', 'Lower', 'Full Body', 'Cardio'];

export default function Entrainement() {
  const [tab, setTab]           = useState('modeles'); // 'modeles' | 'historique'
  const [modeles, setModeles]   = useState([]);
  const [seances, setSeances]   = useState([]);
  const [active, setActive]     = useState(null);
  const [view, setView]         = useState('list');
  const [createMode, setCreateMode] = useState('modele'); // 'modele' | 'seance'
  const [form, setForm]         = useState({ nom: '', date: new Date().toISOString().split('T')[0] });
  const [serieForm, setSerieForm]   = useState({ exerciceNom: '', repetitions: '', chargeKg: '', nbSeries: '3' });
  const [editing, setEditing]       = useState(null); // serie en cours d'edition
  const [renaming, setRenaming]     = useState(false);
  const [renameValue, setRenameValue] = useState('');
  const [loading, setLoading]   = useState(false);

  const [filtre, setFiltre] = useState('tout'); // 'tout' | 'semaine' | 'mois' | 'custom'
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo]   = useState('');
  const [search, setSearch]   = useState('');
  const [page, setPage]       = useState(1);
  const PER_PAGE = 10;

  const loadModeles = () => getModeles().then((r) => setModeles(r.data));
  const loadSeances = () => getSeances(100).then((r) => setSeances(r.data));

  useEffect(() => { loadModeles(); loadSeances(); }, []);

  const openDetail = (id) => getSeance(id).then((r) => { setActive(r.data); setRenaming(false); setView('detail'); });

  const startCreate = (mode) => {
    setCreateMode(mode);
    setForm({ nom: '', date: new Date().toISOString().split('T')[0] });
    setView('create');
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const r = createMode === 'modele'
        ? await createModele({ nom: form.nom })
        : await createSeance(form);
      await openDetail(r.data.id);
      await Promise.all([loadModeles(), loadSeances()]);
    } catch {
      alert('Erreur lors de la création.');
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
    await loadSeances();
  };

  const handleRouvrir = async () => {
    await rouvrirSeance(active.id);
    const r = await getSeance(active.id);
    setActive(r.data);
    await loadSeances();
  };

  const handleRename = async () => {
    const nom = renameValue.trim();
    if (!nom) { setRenaming(false); return; }
    try {
      await updateSeance(active.id, { nom });
      const r = await getSeance(active.id);
      setActive(r.data);
      setRenaming(false);
      await Promise.all([loadModeles(), loadSeances()]);
    } catch { alert('Erreur lors du renommage.'); }
  };

  // Demarrer un modele (ou refaire une seance) : duplique en une seance reelle du jour.
  const handleDemarrer = async (e, id) => {
    if (e) e.stopPropagation();
    try {
      const r = await dupliquerSeance(id);
      await Promise.all([loadModeles(), loadSeances()]);
      setTab('historique');
      await openDetail(r.data.id);
    } catch {
      alert('Erreur lors du démarrage de la séance.');
    }
  };

  const handleDelete = async (e, id) => {
    if (e) e.stopPropagation();
    await deleteSeance(id);
    await Promise.all([loadModeles(), loadSeances()]);
    if (active?.id === id) { setActive(null); setView('list'); }
  };

  // ---------- Vue creation ----------
  if (view === 'create') {
    const isModele = createMode === 'modele';
    return (
      <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
        <button onClick={() => setView('list')} style={styles.back}>← Retour</button>
        <h1 style={styles.title}>{isModele ? 'Nouveau modèle' : 'Nouvelle séance'}</h1>
        <p style={{ color: '#64748B', fontSize: '14px', marginTop: '4px' }}>
          {isModele
            ? 'Prépare un gabarit réutilisable (Push, Pull, Legs…) que tu pourras lancer en un clic.'
            : 'Séance ponctuelle que tu commences maintenant.'}
        </p>
        <div style={styles.card}>
          <form onSubmit={handleCreate}>
            <label style={styles.label}>Type</label>
            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '12px' }}>
              {TYPES_SEANCE.map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => setForm({ ...form, nom: type })}
                  style={{
                    padding: '6px 14px', borderRadius: '20px', border: '1px solid #E2E8F0',
                    background: form.nom === type ? '#0F172A' : '#F8FAFC',
                    color: form.nom === type ? '#fff' : '#64748B',
                    fontSize: '13px', fontWeight: 500, cursor: 'pointer',
                  }}
                >{type}</button>
              ))}
            </div>
            <label style={styles.label}>Nom <span style={{ color: '#94A3B8', fontWeight: 400 }}>(personnalisable)</span></label>
            <input
              style={styles.input}
              value={form.nom}
              onChange={(e) => setForm({ ...form, nom: e.target.value })}
              placeholder="ex : Push lourd, Jambes…"
              required
            />
            {!isModele && (
              <>
                <label style={styles.label}>Date</label>
                <input style={styles.input} type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
              </>
            )}
            <button style={{ ...styles.btn, marginTop: '16px', opacity: loading ? 0.7 : 1 }} type="submit" disabled={loading}>
              {loading ? '…' : isModele ? 'Créer le modèle' : 'Créer et commencer'}
            </button>
          </form>
        </div>
      </div>
    );
  }

  // ---------- Vue detail ----------
  if (view === 'detail' && active) {
    const isModele   = active.statut === 'modele';
    const isEnCours  = active.statut === 'en_cours';
    const isTerminee = active.statut === 'terminee';
    const editable   = isModele || isEnCours;
    const info       = statutInfo(active.statut);

    return (
      <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
        <button onClick={() => setView('list')} style={styles.back}>← {isModele ? 'Mes modèles' : 'Historique'}</button>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '12px' }}>
          <div style={{ minWidth: 0 }}>
            {renaming ? (
              <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                <input
                  style={{ ...styles.input, fontSize: '18px', fontWeight: 700 }}
                  value={renameValue}
                  onChange={(e) => setRenameValue(e.target.value)}
                  autoFocus
                />
                <button onClick={handleRename} style={styles.saveBtn}>✓</button>
                <button onClick={() => setRenaming(false)} style={styles.cancelBtn}>✕</button>
              </div>
            ) : (
              <h1 style={styles.title}>
                {active.nom}
                <button
                  onClick={() => { setRenameValue(active.nom); setRenaming(true); }}
                  style={{ ...styles.iconBtn, fontSize: '16px', marginLeft: '8px', verticalAlign: 'middle' }}
                  title="Renommer"
                >✏️</button>
              </h1>
            )}
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '6px', flexWrap: 'wrap' }}>
              <span style={{ ...styles.badge, background: info.bg, color: info.color }}>{info.label}</span>
              <span style={{ color: '#64748B', fontSize: '14px' }}>
                {isModele
                  ? `${active.series?.length || 0} exercice${(active.series?.length || 0) !== 1 ? 's' : ''}`
                  : active.dateSeance}
              </span>
            </div>
            <p style={{ color: '#64748B', fontSize: '13px', marginTop: '6px' }}>
              {isModele ? 'Volume cible' : 'Volume'} : <strong style={{ color: '#334155' }}>{active.tonnageTotal} kg</strong>
            </p>
          </div>

          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            {isModele && (
              <button style={{ ...styles.btn }} onClick={() => handleDemarrer(null, active.id)}>▶ Démarrer cette séance</button>
            )}
            {isEnCours && (
              <button style={{ ...styles.btn, background: '#0F172A' }} onClick={handleTerminer}>✓ Terminer</button>
            )}
            {isTerminee && (
              <button style={{ ...styles.btn, background: 'none', color: '#0F172A', border: '1px solid #CBD5E1' }} onClick={handleRouvrir}>✎ Modifier</button>
            )}
          </div>
        </div>

        {/* Séries */}
        {active.series?.length > 0 ? (
          <div style={styles.card}>
            <h2 style={styles.cardTitle}>{isModele ? 'Exercices du modèle' : 'Séries'}</h2>
            <div className="table-scroll">
              <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '12px', fontSize: '14px', minWidth: '420px' }}>
                <thead>
                  <tr style={{ color: '#64748B', textAlign: 'left' }}>
                    <th style={styles.th}>Exercice</th>
                    <th style={styles.th}>Reps</th>
                    <th style={styles.th}>Charge</th>
                    <th style={styles.th}>Volume</th>
                    {editable && <th style={styles.th}></th>}
                  </tr>
                </thead>
                <tbody>
                  {active.series.map((s) => (
                    <tr key={s.id} style={{ borderTop: '1px solid #F1F5F9' }}>
                      <td style={styles.td}><strong>{s.exercice}</strong></td>
                      {editable && editing?.id === s.id ? (
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
                          <td style={{ ...styles.td, color: '#334155', fontWeight: 600 }}>
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
                          <td style={{ ...styles.td, color: '#334155', fontWeight: 600 }}>{s.tonnage} kg</td>
                          {editable && (
                            <td style={styles.td}>
                              <button onClick={() => setEditing({ id: s.id, repetitions: s.repetitions, chargeKg: s.chargeKg })} style={styles.iconBtn}>✏️</button>
                              <button onClick={() => handleDeleteSerie(s.id)} style={{ ...styles.iconBtn, marginLeft: '4px' }}>🗑️</button>
                            </td>
                          )}
                        </>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          <div style={{ ...styles.card, textAlign: 'center', color: '#94A3B8' }}>
            <p>Aucun exercice pour l'instant. Ajoute-en ci-dessous.</p>
          </div>
        )}

        {/* Ajouter une série (modèle ou séance en cours) */}
        {editable && (
          <div style={styles.card}>
            <h2 style={styles.cardTitle}>Ajouter un exercice</h2>
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
  }

  // ---------- Vue liste ----------
  // Filtrage client-side (historique)
  const seancesFiltrees = seances.filter((s) => {
    const d = s.dateSeance;
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
    return true;
  }).filter((s) => !search || s.nom.toLowerCase().includes(search.toLowerCase()));

  const totalPages = Math.ceil(seancesFiltrees.length / PER_PAGE);
  const seancesPage = seancesFiltrees.slice((page - 1) * PER_PAGE, page * PER_PAGE);

  return (
    <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', gap: '12px', flexWrap: 'wrap' }}>
        <h1 style={styles.title}>Entraînements</h1>
        {tab === 'modeles'
          ? <button style={styles.btn} onClick={() => startCreate('modele')}>+ Nouveau modèle</button>
          : <button style={{ ...styles.btn, background: 'none', color: '#22C55E', border: '1px solid #22C55E' }} onClick={() => startCreate('seance')}>+ Séance libre</button>}
      </div>

      {/* Onglets */}
      <div style={styles.tabs}>
        {[
          { key: 'modeles',    label: 'Mes modèles' },
          { key: 'historique', label: 'Historique' },
        ].map(({ key, label }) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            style={{ ...styles.tabBtn, ...(tab === key ? styles.tabBtnActive : {}) }}
          >{label}</button>
        ))}
      </div>

      {/* ---- Onglet Modèles ---- */}
      {tab === 'modeles' && (
        modeles.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '40px 0', color: '#94A3B8' }}>
            <p style={{ fontSize: '32px', marginBottom: '8px' }}>🗂️</p>
            <p style={{ fontWeight: 500 }}>Aucun modèle pour l'instant</p>
            <p style={{ fontSize: '13px', marginTop: '4px' }}>Crée un gabarit (Push, Pull, Legs…) pour le lancer en un clic.</p>
            <button style={{ ...styles.btn, marginTop: '12px' }} onClick={() => startCreate('modele')}>+ Nouveau modèle</button>
          </div>
        ) : (
          <div style={styles.modeleGrid}>
            {modeles.map((m) => (
              <div key={m.id} style={styles.modeleCard} onClick={() => openDetail(m.id)}>
                <div style={{ minWidth: 0 }}>
                  <p style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{m.nom}</p>
                  <p style={{ fontSize: '13px', color: '#64748B' }}>{m.nbSeries} exercice{m.nbSeries !== 1 ? 's' : ''}</p>
                </div>
                <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                  <button onClick={(e) => handleDemarrer(e, m.id)} style={styles.startBtn} title="Démarrer cette séance">▶ Démarrer</button>
                  <button onClick={(e) => { e.stopPropagation(); handleDelete(e, m.id); }} style={{ background: 'none', border: 'none', color: '#94A3B8', cursor: 'pointer' }}>✕</button>
                </div>
              </div>
            ))}
          </div>
        )
      )}

      {/* ---- Onglet Historique ---- */}
      {tab === 'historique' && (
        <>
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
                  onClick={() => { setFiltre(key); setPage(1); }}
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

            <div style={{ marginTop: '10px' }}>
              <input
                type="text"
                placeholder="Rechercher une séance (Push day, Legs…)"
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                style={{ ...styles.dateInput, width: '100%', boxSizing: 'border-box' }}
              />
            </div>

            <p style={{ fontSize: '13px', color: '#94A3B8', marginTop: '8px' }}>
              {seancesFiltrees.length} séance{seancesFiltrees.length !== 1 ? 's' : ''}
              {filtre !== 'tout' || search ? ' trouvée' + (seancesFiltrees.length !== 1 ? 's' : '') : ' au total'}
            </p>
          </div>

          {seances.length === 0 ? (
            <p style={{ color: '#64748B' }}>Aucune séance. Démarre un modèle ou crée une séance libre.</p>
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
            <>
              {seancesPage.map((s) => {
                const info = statutInfo(s.statut);
                return (
                  <div key={s.id} className="seance-row" onClick={() => openDetail(s.id)}>
                    <div style={{ minWidth: 0 }}>
                      <p style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.nom}</p>
                      <p style={{ fontSize: '13px', color: '#64748B' }}>{s.dateSeance} · {s.nbSeries} série{s.nbSeries !== 1 ? 's' : ''}</p>
                    </div>
                    <div className="seance-row-actions">
                      <span style={{ color: '#64748B', fontSize: '13px', whiteSpace: 'nowrap' }}>{s.tonnageTotal} kg</span>
                      <span style={{ ...styles.badge, background: info.bg, color: info.color, whiteSpace: 'nowrap' }}>{info.label}</span>
                      <button onClick={(e) => handleDemarrer(e, s.id)} style={styles.reuseBtn} title="Refaire cette séance">↺</button>
                      <button onClick={(e) => { e.stopPropagation(); handleDelete(e, s.id); }} style={{ background: 'none', border: 'none', color: '#94A3B8', cursor: 'pointer' }}>✕</button>
                    </div>
                  </div>
                );
              })}

              {totalPages > 1 && (
                <div style={styles.pagination}>
                  <button
                    style={{ ...styles.pageBtn, opacity: page === 1 ? 0.4 : 1 }}
                    disabled={page === 1}
                    onClick={() => setPage(p => p - 1)}
                  >← Précédent</button>
                  <span style={{ fontSize: '13px', color: '#64748B' }}>
                    Page {page} / {totalPages}
                  </span>
                  <button
                    style={{ ...styles.pageBtn, opacity: page === totalPages ? 0.4 : 1 }}
                    disabled={page === totalPages}
                    onClick={() => setPage(p => p + 1)}
                  >Suivant →</button>
                </div>
              )}
            </>
          )}
        </>
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
  badge:    { padding: '2px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: 600 },
  th:          { padding: '6px 8px', fontWeight: 600, fontSize: '13px' },
  td:          { padding: '8px', verticalAlign: 'middle' },
  inlineInput: { padding: '4px 6px', border: '1px solid #22C55E', borderRadius: '6px', fontSize: '14px' },
  saveBtn:     { background: '#22C55E', color: '#fff', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', fontWeight: 700 },
  cancelBtn:   { background: '#E2E8F0', color: '#64748B', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', marginLeft: '4px' },
  iconBtn:     { background: 'none', border: 'none', cursor: 'pointer', fontSize: '15px', padding: '2px 4px', borderRadius: '4px' },
  reuseBtn:      { padding: '4px 10px', background: '#EFF6FF', color: '#3B82F6', border: '1px solid #BFDBFE', borderRadius: '6px', fontSize: '12px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  startBtn:      { padding: '5px 12px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '6px', fontSize: '13px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  tabs:          { display: 'flex', gap: '6px', marginBottom: '16px', borderBottom: '1px solid #E2E8F0' },
  tabBtn:        { padding: '10px 18px', background: 'none', border: 'none', borderBottom: '2px solid transparent', color: '#64748B', fontSize: '15px', fontWeight: 600, cursor: 'pointer', marginBottom: '-1px' },
  tabBtnActive:  { color: '#0F172A', borderBottom: '2px solid #22C55E' },
  modeleGrid:    { display: 'grid', gap: '10px' },
  modeleCard:    { background: '#fff', borderRadius: '10px', padding: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', cursor: 'pointer', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  filterBar:     { background: '#fff', borderRadius: '12px', padding: '14px 16px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  filterBtn:     { padding: '6px 14px', borderRadius: '20px', border: '1px solid #E2E8F0', background: '#F8FAFC', color: '#64748B', fontSize: '13px', fontWeight: 500, cursor: 'pointer' },
  filterBtnActive: { background: '#0F172A', color: '#fff', border: '1px solid #0F172A' },
  dateInput:     { padding: '5px 10px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '13px' },
  clearBtn:      { padding: '5px 10px', background: 'none', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '12px', color: '#94A3B8', cursor: 'pointer' },
  pagination:    { display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '16px', marginTop: '16px', padding: '12px 0' },
  pageBtn:       { padding: '8px 16px', background: '#F8FAFC', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '13px', fontWeight: 500, cursor: 'pointer', color: '#0F172A' },
};
