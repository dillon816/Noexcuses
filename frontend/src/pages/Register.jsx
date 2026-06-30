import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { register } from '../api/auth';

export default function Register() {
  const [form, setForm]     = useState({ email: '', password: '', prenom: '', nom: '' });
  const [error, setError]   = useState('');
  const [loading, setLoading] = useState(false);
  const navigate            = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (form.password.length < 8) {
      setError('Le mot de passe doit faire au moins 8 caractères.');
      return;
    }
    setLoading(true);
    try {
      await register(form);
      navigate('/login', { state: { registered: true } });
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors de l\'inscription.');
    } finally {
      setLoading(false);
    }
  };

  const f = (key) => ({
    value: form[key],
    onChange: (e) => setForm({ ...form, [key]: e.target.value }),
  });

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        <h1 style={styles.logo}>NoExcuses</h1>
        <p style={styles.subtitle}>Créez votre compte</p>

        {error && <div style={styles.error}>{error}</div>}

        <form onSubmit={handleSubmit}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <div>
              <label style={styles.label}>Prénom</label>
              <input style={styles.input} type="text" placeholder="Jean" required {...f('prenom')} />
            </div>
            <div>
              <label style={styles.label}>Nom</label>
              <input style={styles.input} type="text" placeholder="Dupont" required {...f('nom')} />
            </div>
          </div>

          <label style={styles.label}>Email</label>
          <input style={styles.input} type="email" placeholder="you@example.com" required {...f('email')} />

          <label style={styles.label}>Mot de passe</label>
          <input style={styles.input} type="password" placeholder="8 caractères minimum" required {...f('password')} />

          <button style={{ ...styles.btn, opacity: loading ? 0.7 : 1 }} type="submit" disabled={loading}>
            {loading ? 'Création…' : 'Créer mon compte'}
          </button>
        </form>

        <p style={{ marginTop: '16px', textAlign: 'center', fontSize: '14px', color: '#64748B' }}>
          Déjà un compte ?{' '}
          <Link to="/login" style={{ color: '#22C55E', fontWeight: 600 }}>Se connecter</Link>
        </p>
      </div>
    </div>
  );
}

const styles = {
  page:  { minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#F1F5F9', padding: '16px' },
  card:  { background: '#fff', borderRadius: '16px', padding: '40px 32px', width: '100%', maxWidth: '440px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' },
  logo:  { color: '#22C55E', fontWeight: 700, fontSize: '24px', textAlign: 'center', marginBottom: '4px' },
  subtitle: { textAlign: 'center', color: '#64748B', fontSize: '14px', marginBottom: '20px' },
  label: { display: 'block', fontSize: '14px', fontWeight: 500, color: '#0F172A', marginBottom: '6px', marginTop: '14px' },
  input: { width: '100%', padding: '10px 14px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '15px', outline: 'none', background: '#fff' },
  btn:   { width: '100%', marginTop: '24px', padding: '12px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontSize: '15px', fontWeight: 600 },
  error: { background: '#FEF2F2', color: '#DC2626', padding: '10px 14px', borderRadius: '8px', fontSize: '14px', marginBottom: '8px' },
};
