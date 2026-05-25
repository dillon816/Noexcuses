import { useEffect, useState } from 'react';
import api from '../api/axios';

export default function Profil() {
  const [profil, setProfil]   = useState(null);
  const [form, setForm]       = useState({});
  const [msg, setMsg]         = useState('');
  const [error, setError]     = useState('');

  useEffect(() => {
    api.get('/profil').then((r) => { setProfil(r.data); setForm(r.data); });
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setMsg(''); setError('');
    try {
      await api.put('/profil', form);
      setMsg('Profil mis à jour !');
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors de la mise à jour.');
    }
  };

  if (!profil) return <div style={{ padding: '24px', color: '#64748B' }}>Chargement…</div>;

  const f = (key) => ({
    value: form[key] ?? '',
    onChange: (e) => setForm({ ...form, [key]: e.target.value }),
  });

  return (
    <div style={styles.page}>
      <h1 style={styles.title}>Mon Profil</h1>

      {msg   && <div style={styles.success}>{msg}</div>}
      {error && <div style={styles.error}>{error}</div>}

      <div style={styles.card}>
        <h2 style={styles.cardTitle}>Informations personnelles</h2>
        <form onSubmit={handleSubmit}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <Field label="Prénom"  {...f('prenom')} />
            <Field label="Nom"     {...f('nom')} />
          </div>
          <Field label="Email" value={profil.email} disabled />
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <div>
              <label style={styles.label}>Sexe</label>
              <select style={styles.input} value={form.sexe ?? ''} onChange={(e) => setForm({ ...form, sexe: e.target.value })}>
                <option value="">— Non renseigné</option>
                <option value="homme">Homme</option>
                <option value="femme">Femme</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <Field label="Taille (cm)" type="number" {...f('taille')} />
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <div>
              <label style={styles.label}>Niveau d'activité</label>
              <select style={styles.input} value={form.niveauActivite ?? ''} onChange={(e) => setForm({ ...form, niveauActivite: e.target.value })}>
                <option value="">— Non renseigné</option>
                <option value="sédentaire">Sédentaire</option>
                <option value="légèrement actif">Légèrement actif</option>
                <option value="modérément actif">Modérément actif</option>
                <option value="très actif">Très actif</option>
                <option value="extrêmement actif">Extrêmement actif</option>
              </select>
            </div>
            <Field label="Objectif calories" type="number" {...f('caloriesObjectif')} />
          </div>
          <button style={{ ...styles.btn, marginTop: '20px' }} type="submit">Sauvegarder</button>
        </form>
      </div>
    </div>
  );
}

function Field({ label, value, onChange, type = 'text', disabled }) {
  return (
    <div>
      <label style={{ display: 'block', fontSize: '14px', fontWeight: 500, marginBottom: '6px', marginTop: '14px', color: '#0F172A' }}>{label}</label>
      <input
        style={{ width: '100%', padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px', background: disabled ? '#F8FAFC' : '#fff' }}
        type={type} value={value ?? ''} onChange={onChange} disabled={disabled}
      />
    </div>
  );
}

const styles = {
  page:      { padding: '24px', maxWidth: '700px', margin: '0 auto' },
  title:     { fontSize: '30px', fontWeight: 700, marginBottom: '20px' },
  card:      { background: '#fff', borderRadius: '12px', padding: '24px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' },
  cardTitle: { fontSize: '14px', fontWeight: 600, color: '#64748B', textTransform: 'uppercase', letterSpacing: '0.05em' },
  label:     { display: 'block', fontSize: '14px', fontWeight: 500, marginBottom: '6px', marginTop: '14px', color: '#0F172A' },
  input:     { width: '100%', padding: '8px 12px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '14px' },
  btn:       { padding: '10px 24px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontWeight: 600, cursor: 'pointer' },
  success:   { background: '#F0FDF4', color: '#16A34A', padding: '10px 14px', borderRadius: '8px', fontSize: '14px', marginBottom: '12px' },
  error:     { background: '#FEF2F2', color: '#DC2626', padding: '10px 14px', borderRadius: '8px', fontSize: '14px', marginBottom: '12px' },
};
