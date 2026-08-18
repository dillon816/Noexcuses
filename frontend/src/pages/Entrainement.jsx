import { useEffect, useState } from 'react';
import {
  getSeances, getModeles, getEnCours, createSeance, createModele, getSeance,
  addSerie, updateSerie, deleteSerie, dupliquerSeance,
  terminerSeance, rouvrirSeance, updateSeance, deleteSeance,
} from '../api/entrainement';
import { useToast, useConfirm } from '../components/common/UIFeedback';
import EmptyState from '../components/common/EmptyState';

const STATUT_INFO = {
  en_cours: { label: 'En cours', bg: '#FFF7ED', color: '#EA580C' },
  terminee: { label: 'Terminée', bg: '#F0FDF4', color: '#16A34A' },
  modele:   { label: 'Modèle',   bg: '#EFF6FF', color: '#2563EB' },
  archivee: { label: 'Archivée', bg: '#F1F5F9', color: '#64748B' },
};
const statutInfo = (s) => STATUT_INFO[s] || { label: s, bg: '#F1F5F9', color: '#64748B' };

const TYPES_SEANCE = ['Push', 'Pull', 'Legs', 'Upper', 'Lower', 'Full Body', 'Cardio'];

// Regroupe les séries par exercice (id_exercice) en conservant l'ordre d'apparition.
const groupByExercice = (series = []) => {
  const groups = [];
  const index = {};
  for (const s of series) {
    const key = s.exerciceId ?? s.exercice;
    if (index[key] === undefined) {
      index[key] = groups.length;
      groups.push({ nom: s.exercice, series: [] });
    }
    groups[index[key]].series.push(s);
  }
  return groups;
};

const plural = (n, mot) => `${n} ${mot}${n !== 1 ? 's' : ''}`;

export default function Entrainement() {
  const toast   = useToast();
  const confirm = useConfirm();
  const [busy, setBusy] = useState(null); // id de la séance en cours d'action (Démarrer/Terminer)
  const [tab, setTab]           = useState('modeles'); // 'modeles' | 'historique'
  const [modeles, setModeles]   = useState([]);
  const [enCours, setEnCours]   = useState([]);
  const [seances, setSeances]   = useState([]); // historique = terminées
  const [active, setActive]     = useState(null);
  const [view, setView]         = useState('list');
  const [createMode, setCreateMode] = useState('modele'); // 'modele' | 'seance'
  const [form, setForm]         = useState({ nom: '', date: new Date().toISOString().split('T')[0] });
  const [serieForm, setSerieForm]   = useState({ exerciceNom: '', repetitions: '', chargeKg: '', nbSeries: '3' });
  const [editing, setEditing]       = useState(null); // série en cours d'édition
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
  const loadEnCours = () => getEnCours().then((r) => setEnCours(r.data));
  const loadSeances = () => getSeances(100).then((r) => setSeances(r.data));
  const reloadAll   = () => Promise.all([loadModeles(), loadEnCours(), loadSeances()]);

  // Chargement initial des trois listes, uniquement au montage.
  useEffect(() => { reloadAll(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const openDetail = (id) => getSeance(id).then((r) => { setActive(r.data); setRenaming(false); setEditing(null); setView('detail'); });

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
      await reloadAll();
    } catch {
      toast('Erreur lors de la création.', 'error');
    } finally { setLoading(false); }
  };

  const refreshActive = async () => {
    const r = await getSeance(active.id);
    setActive(r.data);
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
      await refreshActive();
      setSerieForm({ exerciceNom: '', repetitions: '', chargeKg: '', nbSeries: '3' });
    } catch {
      toast('Erreur lors de l\'ajout de l\'exercice.', 'error');
    }
  };

  // Ajoute une série de plus à un exercice déjà présent (reprend reps/charge de la dernière).
  const handleAddSet = async (group) => {
    const last = group.series[group.series.length - 1];
    try {
      await addSerie(active.id, {
        exerciceNom: group.nom,
        repetitions: last ? parseInt(last.repetitions) : 10,
        chargeKg: last ? parseFloat(last.chargeKg) : 0,
        nbSeries: 1,
      });
      await refreshActive();
    } catch { toast('Erreur lors de l\'ajout de la série.', 'error'); }
  };

  const handleUpdateSerie = async (serieId) => {
    try {
      await updateSerie(active.id, serieId, { repetitions: parseInt(editing.repetitions), chargeKg: parseFloat(editing.chargeKg) });
      await refreshActive();
      setEditing(null);
    } catch { toast('Erreur lors de la modification.', 'error'); }
  };

  const handleDeleteSerie = async (serieId) => {
    try {
      await deleteSerie(active.id, serieId);
      await refreshActive();
    } catch { toast('Erreur lors de la suppression.', 'error'); }
  };

  // Supprime un exercice entier (toutes ses séries).
  const handleDeleteExercice = async (group) => {
    const ok = await confirm({
      title: 'Supprimer l\'exercice ?',
      message: `Toutes les séries de "${group.nom}" seront supprimées.`,
      confirmLabel: 'Supprimer', danger: true,
    });
    if (!ok) return;
    try {
      for (const s of group.series) {
        await deleteSerie(active.id, s.id);
      }
      await refreshActive();
    } catch { toast('Erreur lors de la suppression de l\'exercice.', 'error'); }
  };

  const handleTerminer = async (id = active?.id) => {
    setBusy(id);
    try {
      await terminerSeance(id);
      if (active?.id === id) await refreshActive();
      await reloadAll();
      toast('Séance terminée, bien joué !', 'success');
    } catch { toast('Erreur lors de la validation de la séance.', 'error'); }
    finally { setBusy(null); }
  };

  const handleRouvrir = async () => {
    try {
      await rouvrirSeance(active.id);
      await refreshActive();
      await reloadAll();
      toast('Séance rouverte : tu peux la modifier.', 'info');
    } catch { toast('Erreur lors de la réouverture.', 'error'); }
  };

  const handleRename = async () => {
    const nom = renameValue.trim();
    if (!nom) { setRenaming(false); return; }
    try {
      await updateSeance(active.id, { nom });
      await refreshActive();
      setRenaming(false);
      await reloadAll();
      toast('Renommé.', 'success');
    } catch { toast('Erreur lors du renommage.', 'error'); }
  };

  // Démarrer un modèle (ou refaire une séance) : duplique en une séance réelle du jour.
  const handleDemarrer = async (e, id) => {
    if (e) e.stopPropagation();
    setBusy(id);
    try {
      const r = await dupliquerSeance(id);
      await reloadAll();
      setTab('historique');
      await openDetail(r.data.id);
      toast('Séance démarrée : ajuste-la puis termine-la.', 'success');
    } catch {
      toast('Erreur lors du démarrage de la séance.', 'error');
    } finally { setBusy(null); }
  };

  const handleDelete = async (e, id) => {
    if (e) e.stopPropagation();
    const ok = await confirm({
      title: 'Supprimer définitivement ?',
      message: 'Cette action est irréversible.',
      confirmLabel: 'Supprimer', danger: true,
    });
    if (!ok) return;
    try {
      await deleteSeance(id);
      await reloadAll();
      if (active?.id === id) { setActive(null); setView('list'); }
      toast('Supprimé.', 'success');
    } catch { toast('Erreur lors de la suppression.', 'error'); }
  };

  // ---------- Vue création ----------
  if (view === 'create') {
    const isModele = createMode === 'modele';
    return (
      <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
        <button onClick={() => setView('list')} style={styles.back}>← Retour</button>
        <h1 style={styles.title}>{isModele ? 'Nouveau modèle' : 'Nouvelle séance'}</h1>
        <p style={{ color: '#64748B', fontSize: '14px', marginTop: '4px' }}>
          {isModele
            ? 'Prépare une routine réutilisable (Push, Pull, Legs…) que tu pourras démarrer en un clic.'
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

  // ---------- Vue détail ----------
  if (view === 'detail' && active) {
    const isModele   = active.statut === 'modele';
    const isEnCours  = active.statut === 'en_cours';
    const isTerminee = active.statut === 'terminee';
    const editable   = isModele || isEnCours;
    const info       = statutInfo(active.statut);
    const groups     = groupByExercice(active.series);
    const nbExos     = active.nbExercices ?? groups.length;
    const nbSeries   = active.series?.length || 0;

    return (
      <div className="page-wrap narrow" style={{ margin: '0 auto' }}>
        <button onClick={() => setView('list')} style={styles.back}>← {isModele ? 'Mes modèles' : isEnCours ? 'Retour' : 'Historique'}</button>
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
                  title="Renommer" aria-label="Renommer"
                >✏️</button>
              </h1>
            )}
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '6px', flexWrap: 'wrap' }}>
              <span style={{ ...styles.badge, background: info.bg, color: info.color }}>{info.label}</span>
              {!isModele && <span style={{ color: '#64748B', fontSize: '14px' }}>{active.dateSeance}</span>}
              <span style={{ color: '#64748B', fontSize: '14px' }}>{plural(nbExos, 'exercice')} · {plural(nbSeries, 'série')}</span>
            </div>
            <p style={{ color: '#64748B', fontSize: '13px', marginTop: '6px' }}>
              {isModele ? 'Volume cible' : 'Volume'} : <strong style={{ color: '#334155' }}>{active.tonnageTotal} kg</strong>
            </p>
          </div>

          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            {isModele && (
              <button style={styles.btn} onClick={() => handleDemarrer(null, active.id)} disabled={busy === active.id}>
                {busy === active.id ? '…' : '▶ Démarrer la séance'}
              </button>
            )}
            {isEnCours && (
              <button style={{ ...styles.btn, background: '#0F172A' }} onClick={() => handleTerminer()} disabled={busy === active.id}>
                {busy === active.id ? '…' : '✓ Terminer la séance'}
              </button>
            )}
            {isTerminee && (
              <button style={{ ...styles.btn, background: 'none', color: '#0F172A', border: '1px solid #CBD5E1' }} onClick={handleRouvrir}>✎ Modifier</button>
            )}
          </div>
        </div>

        {isEnCours && (
          <div style={styles.enCoursHint}>
            Séance en cours : ajuste tes exercices, séries, répétitions et charges, puis clique sur <strong>Terminer la séance</strong>.
          </div>
        )}

        {/* Exercices groupés */}
        {groups.length > 0 ? (
          <div style={styles.card}>
            <h2 style={styles.cardTitle}>{isModele ? 'Exercices du modèle' : 'Exercices'}</h2>
            {groups.map((g, gi) => (
              <div key={gi} style={styles.exoBlock}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px' }}>
                  <p style={{ fontWeight: 700, color: '#0F172A' }}>{g.nom} <span style={{ color: '#94A3B8', fontWeight: 400, fontSize: '13px' }}>· {plural(g.series.length, 'série')}</span></p>
                  {editable && (
                    <div style={{ display: 'flex', gap: '6px' }}>
                      <button onClick={() => handleAddSet(g)} style={styles.miniBtn} title="Ajouter une série" aria-label={`Ajouter une série à ${g.nom}`}>+ série</button>
                      <button onClick={() => handleDeleteExercice(g)} style={{ ...styles.iconBtn }} title="Supprimer l'exercice" aria-label={`Supprimer l'exercice ${g.nom}`}>🗑️</button>
                    </div>
                  )}
                </div>
                <div className="table-scroll">
                  <table style={{ width: '100%', borderCollapse: 'collapse', marginTop: '6px', fontSize: '14px', minWidth: '360px' }}>
                    <tbody>
                      {g.series.map((s, si) => (
                        <tr key={s.id} style={{ borderTop: si === 0 ? 'none' : '1px solid #F1F5F9' }}>
                          <td style={{ ...styles.td, color: '#94A3B8', width: '70px' }}>Série {si + 1}</td>
                          {editable && editing?.id === s.id ? (
                            <>
                              <td style={styles.td}>
                                <input type="number" min="1" value={editing.repetitions}
                                  onChange={(e) => setEditing({ ...editing, repetitions: e.target.value })}
                                  style={{ ...styles.inlineInput, width: '56px' }} /> reps
                              </td>
                              <td style={styles.td}>
                                <input type="number" min="0" step="0.5" value={editing.chargeKg}
                                  onChange={(e) => setEditing({ ...editing, chargeKg: e.target.value })}
                                  style={{ ...styles.inlineInput, width: '64px' }} /> kg
                              </td>
                              <td style={{ ...styles.td, color: '#334155', fontWeight: 600 }}>
                                {Math.round(parseInt(editing.repetitions || 0) * parseFloat(editing.chargeKg || 0))} kg
                              </td>
                              <td style={{ ...styles.td, textAlign: 'right' }}>
                                <button onClick={() => handleUpdateSerie(s.id)} style={styles.saveBtn}>✓</button>
                                <button onClick={() => setEditing(null)} style={styles.cancelBtn}>✕</button>
                              </td>
                            </>
                          ) : (
                            <>
                              <td style={styles.td}>{s.repetitions} reps</td>
                              <td style={styles.td}>{s.chargeKg} kg</td>
                              <td style={{ ...styles.td, color: '#334155', fontWeight: 600 }}>{s.tonnage} kg</td>
                              <td style={{ ...styles.td, textAlign: 'right' }}>
                                {editable && (
                                  <>
                                    <button onClick={() => setEditing({ id: s.id, repetitions: s.repetitions, chargeKg: s.chargeKg })} style={styles.iconBtn} title="Modifier la série" aria-label="Modifier la série">✏️</button>
                                    <button onClick={() => handleDeleteSerie(s.id)} style={{ ...styles.iconBtn, marginLeft: '4px' }} title="Supprimer la série" aria-label="Supprimer la série">🗑️</button>
                                  </>
                                )}
                              </td>
                            </>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div style={{ ...styles.card, textAlign: 'center', color: '#94A3B8' }}>
            <p>Aucun exercice pour l'instant. Ajoute-en ci-dessous.</p>
          </div>
        )}

        {/* Ajouter un exercice (modèle ou séance en cours) */}
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
          : <button style={{ ...styles.btn, background: 'none', color: '#22C55E', border: '1px solid #22C55E' }} onClick={() => startCreate('seance')} title="Entraînement ponctuel, sans passer par un modèle">+ Séance ponctuelle</button>}
      </div>

      {/* Séance(s) en cours : bien visible, au-dessus des onglets */}
      {enCours.length > 0 && (
        <div style={styles.enCoursWrap}>
          <p style={styles.enCoursTitle}>● Séance en cours</p>
          {enCours.map((s) => (
            <div key={s.id} className="lift-card" style={styles.enCoursCard} onClick={() => openDetail(s.id)}>
              <div style={{ minWidth: 0 }}>
                <p style={{ fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.nom}</p>
                <p style={{ fontSize: '13px', color: '#9A3412' }}>{s.dateSeance} · {plural(s.nbExercices, 'exercice')} · {plural(s.nbSeries, 'série')}</p>
              </div>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexShrink: 0 }}>
                <button onClick={(e) => { e.stopPropagation(); openDetail(s.id); }} style={styles.reprendreBtn}>▶ Reprendre</button>
                <button onClick={(e) => { e.stopPropagation(); handleTerminer(s.id); }} style={{ ...styles.btn, background: '#0F172A', padding: '8px 14px' }} disabled={busy === s.id}>
                  {busy === s.id ? '…' : '✓ Terminer'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

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
          <EmptyState
            icon="🗂️"
            title="Aucun modèle pour l'instant"
            subtitle="Crée une routine (Push, Pull, Legs…) pour la démarrer en un clic."
            action={<button style={styles.btn} onClick={() => startCreate('modele')}>+ Nouveau modèle</button>}
          />
        ) : (
          <div style={styles.modeleGrid}>
            {modeles.map((m) => (
              <div key={m.id} className="lift-card" style={styles.modeleCard} onClick={() => openDetail(m.id)}>
                <div style={{ minWidth: 0 }}>
                  <p style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{m.nom}</p>
                  <p style={{ fontSize: '13px', color: '#64748B' }}>{plural(m.nbExercices, 'exercice')} · {plural(m.nbSeries, 'série')}</p>
                </div>
                <div style={{ display: 'flex', gap: '6px', alignItems: 'center', flexShrink: 0 }}>
                  <button onClick={(e) => handleDemarrer(e, m.id)} style={styles.startBtn} title="Démarrer la séance" disabled={busy === m.id}>
                    {busy === m.id ? '…' : '▶ Démarrer la séance'}
                  </button>
                  <button onClick={(e) => { e.stopPropagation(); handleDelete(e, m.id); }} style={styles.iconBtn} title="Supprimer le modèle" aria-label={`Supprimer le modèle ${m.nom}`}>🗑️</button>
                </div>
              </div>
            ))}
          </div>
        )
      )}

      {/* ---- Onglet Historique (séances terminées) ---- */}
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
              {plural(seancesFiltrees.length, 'séance')} terminée{seancesFiltrees.length !== 1 ? 's' : ''}
              {filtre !== 'tout' || search ? ' trouvée' + (seancesFiltrees.length !== 1 ? 's' : '') : ''}
            </p>
          </div>

          {seances.length === 0 ? (
            <EmptyState
              icon="🏁"
              title="Aucune séance terminée"
              subtitle="Démarre un modèle, fais ta séance, puis termine-la : elle apparaîtra ici."
            />
          ) : seancesFiltrees.length === 0 ? (
            <EmptyState
              icon="📭"
              title="Aucune séance sur cette période"
              action={<button style={{ ...styles.btn, background: 'none', color: '#22C55E', border: '1px solid #22C55E' }}
                onClick={() => { setFiltre('tout'); setDateFrom(''); setDateTo(''); }}>
                Voir toutes les séances
              </button>}
            />
          ) : (
            <>
              {seancesPage.map((s) => {
                const info = statutInfo(s.statut);
                return (
                  <div key={s.id} className="seance-row" onClick={() => openDetail(s.id)}>
                    <div style={{ minWidth: 0 }}>
                      <p style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.nom}</p>
                      <p style={{ fontSize: '13px', color: '#64748B' }}>{s.dateSeance} · {plural(s.nbExercices, 'exercice')} · {plural(s.nbSeries, 'série')}</p>
                    </div>
                    <div className="seance-row-actions">
                      <span style={{ color: '#64748B', fontSize: '13px', whiteSpace: 'nowrap' }}>{s.tonnageTotal} kg</span>
                      <span style={{ ...styles.badge, background: info.bg, color: info.color, whiteSpace: 'nowrap' }}>{info.label}</span>
                      <button onClick={(e) => { e.stopPropagation(); handleDelete(e, s.id); }} style={styles.iconBtn} title="Supprimer la séance" aria-label={`Supprimer la séance ${s.nom}`}>🗑️</button>
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
  td:          { padding: '7px 8px', verticalAlign: 'middle' },
  inlineInput: { padding: '4px 6px', border: '1px solid #22C55E', borderRadius: '6px', fontSize: '14px' },
  saveBtn:     { background: '#22C55E', color: '#fff', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', fontWeight: 700 },
  cancelBtn:   { background: '#E2E8F0', color: '#64748B', border: 'none', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', marginLeft: '4px' },
  iconBtn:     { background: 'none', border: 'none', cursor: 'pointer', fontSize: '15px', padding: '2px 4px', borderRadius: '4px' },
  miniBtn:     { padding: '3px 10px', background: '#F0FDF4', color: '#16A34A', border: '1px solid #BBF7D0', borderRadius: '6px', fontSize: '12px', fontWeight: 600, cursor: 'pointer' },
  exoBlock:    { padding: '12px 0', borderTop: '1px solid #F1F5F9' },
  reuseBtn:      { padding: '4px 10px', background: '#EFF6FF', color: '#3B82F6', border: '1px solid #BFDBFE', borderRadius: '6px', fontSize: '12px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  startBtn:      { padding: '8px 14px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontSize: '13px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  reprendreBtn:  { padding: '8px 14px', background: '#fff', color: '#EA580C', border: '1px solid #FDBA74', borderRadius: '8px', fontSize: '13px', fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap' },
  tabs:          { display: 'flex', gap: '6px', marginBottom: '16px', borderBottom: '1px solid #E2E8F0' },
  tabBtn:        { padding: '10px 18px', background: 'none', border: 'none', borderBottom: '2px solid transparent', color: '#64748B', fontSize: '15px', fontWeight: 600, cursor: 'pointer', marginBottom: '-1px' },
  tabBtnActive:  { color: '#0F172A', borderBottom: '2px solid #22C55E' },
  modeleGrid:    { display: 'grid', gap: '10px' },
  modeleCard:    { background: '#fff', borderRadius: '10px', padding: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', cursor: 'pointer', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  enCoursWrap:   { background: '#FFF7ED', border: '1px solid #FED7AA', borderRadius: '12px', padding: '14px 16px', marginBottom: '16px' },
  enCoursTitle:  { fontSize: '13px', fontWeight: 700, color: '#EA580C', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: '10px' },
  enCoursCard:   { background: '#fff', borderRadius: '10px', padding: '14px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', cursor: 'pointer', boxShadow: '0 1px 3px rgba(234,88,12,0.12)', marginTop: '8px', flexWrap: 'wrap' },
  enCoursHint:   { background: '#FFF7ED', border: '1px solid #FED7AA', borderRadius: '10px', padding: '10px 14px', marginTop: '16px', fontSize: '13px', color: '#9A3412' },
  filterBar:     { background: '#fff', borderRadius: '12px', padding: '14px 16px', marginBottom: '16px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  filterBtn:     { padding: '6px 14px', borderRadius: '20px', border: '1px solid #E2E8F0', background: '#F8FAFC', color: '#64748B', fontSize: '13px', fontWeight: 500, cursor: 'pointer' },
  filterBtnActive: { background: '#0F172A', color: '#fff', border: '1px solid #0F172A' },
  dateInput:     { padding: '5px 10px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '13px' },
  clearBtn:      { padding: '5px 10px', background: 'none', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '12px', color: '#94A3B8', cursor: 'pointer' },
  pagination:    { display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '16px', marginTop: '16px', padding: '12px 0' },
  pageBtn:       { padding: '8px 16px', background: '#F8FAFC', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '13px', fontWeight: 500, cursor: 'pointer', color: '#0F172A' },
};
