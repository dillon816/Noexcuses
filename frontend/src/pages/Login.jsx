import { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function Login() {
  const [form, setForm]     = useState({ email: '', password: '' });
  const [error, setError]   = useState('');
  const [loading, setLoading] = useState(false);
  const { login, loginWithGoogle } = useAuth();
  const navigate            = useNavigate();
  const googleBtnRef        = useRef(null);

  // Bouton "Se connecter avec Google" : on charge le script officiel Google (GIS)
  // puis on affiche le bouton. Le Client ID vient de la variable d'environnement.
  useEffect(() => {
    const clientId = process.env.REACT_APP_GOOGLE_CLIENT_ID;
    if (!clientId || clientId === 'changeme') return; // OAuth non configuré : on n'affiche rien

    const handleCredential = async (response) => {
      setError('');
      try {
        await loginWithGoogle(response.credential);
        navigate('/dashboard');
      } catch {
        setError('Connexion Google échouée.');
      }
    };

    const init = () => {
      if (!window.google?.accounts?.id || !googleBtnRef.current) return;
      window.google.accounts.id.initialize({ client_id: clientId, callback: handleCredential });
      window.google.accounts.id.renderButton(googleBtnRef.current, {
        theme: 'outline', size: 'large', width: 356, text: 'signin_with', locale: 'fr',
      });
    };

    if (window.google?.accounts?.id) {
      init();
    } else {
      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = init;
      document.body.appendChild(script);
    }
  }, [loginWithGoogle, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(form.email, form.password);
      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Email ou mot de passe incorrect.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        <h1 style={styles.logo}>NoExcuses</h1>
        <p style={styles.subtitle}>Connectez-vous à votre espace</p>

        {error && <div style={styles.error}>{error}</div>}

        <form onSubmit={handleSubmit}>
          <label style={styles.label}>Email</label>
          <input
            style={styles.input}
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            placeholder="you@example.com"
            required
          />

          <label style={styles.label}>Mot de passe</label>
          <input
            style={styles.input}
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            placeholder="••••••••"
            required
          />

          <button style={{ ...styles.btn, opacity: loading ? 0.7 : 1 }} type="submit" disabled={loading}>
            {loading ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>

        <div style={styles.divider}>
          <span style={styles.dividerLine} />
          <span style={styles.dividerText}>ou</span>
          <span style={styles.dividerLine} />
        </div>

        <div ref={googleBtnRef} style={{ display: 'flex', justifyContent: 'center' }} />

        <p style={{ marginTop: '16px', textAlign: 'center', fontSize: '14px', color: '#64748B' }}>
          Pas encore de compte ?{' '}
          <Link to="/register" style={{ color: '#22C55E', fontWeight: 600 }}>S'inscrire</Link>
        </p>
      </div>
    </div>
  );
}

const styles = {
  page:  { minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#F1F5F9', padding: '16px' },
  card:  { background: '#fff', borderRadius: '16px', padding: '40px 32px', width: '100%', maxWidth: '420px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' },
  logo:  { color: '#22C55E', fontWeight: 700, fontSize: '24px', textAlign: 'center', marginBottom: '4px' },
  subtitle: { textAlign: 'center', color: '#64748B', fontSize: '14px', marginBottom: '28px' },
  label: { display: 'block', fontSize: '14px', fontWeight: 500, color: '#0F172A', marginBottom: '6px', marginTop: '16px' },
  input: { width: '100%', padding: '10px 14px', border: '1px solid #E2E8F0', borderRadius: '8px', fontSize: '15px', outline: 'none', background: '#fff' },
  btn:   { width: '100%', marginTop: '24px', padding: '12px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: '8px', fontSize: '15px', fontWeight: 600 },
  error: { background: '#FEF2F2', color: '#DC2626', padding: '10px 14px', borderRadius: '8px', fontSize: '14px', marginBottom: '8px' },
  divider: { display: 'flex', alignItems: 'center', gap: '12px', margin: '20px 0' },
  dividerLine: { flex: 1, height: '1px', background: '#E2E8F0' },
  dividerText: { fontSize: '13px', color: '#94A3B8' },
};
