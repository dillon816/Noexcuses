import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

const NAV = [
  { to: '/dashboard',    label: 'Dashboard',        icon: '▦' },
  { to: '/nutrition',    label: 'Nutrition',         icon: '🥗' },
  { to: '/entrainement', label: 'Entraînements',     icon: '🏋' },
  { to: '/recovery',     label: 'Recovery Budget',   icon: '⚡' },
  { to: '/progression',  label: 'Progression',       icon: '📈' },
  { to: '/profil',       label: 'Mon Profil',        icon: '👤' },
];

const base = {
  display: 'flex',
  alignItems: 'center',
  gap: '10px',
  padding: '10px 16px',
  borderRadius: '8px',
  color: '#94A3B8',
  fontSize: '15px',
  fontWeight: 500,
  transition: 'background 0.15s, color 0.15s',
  margin: '2px 8px',
};

export default function Sidebar() {
  const { logout } = useAuth();

  return (
    <nav className="layout-sidebar" style={{
      width: 'var(--sidebar-width)',
      background: 'var(--color-sidebar)',
      height: '100vh',
      position: 'fixed',
      left: 0, top: 0,
      flexDirection: 'column',
      paddingTop: '24px',
    }}>
      <div style={{ padding: '0 24px 28px', color: '#22C55E', fontWeight: 700, fontSize: '20px', letterSpacing: '-0.5px' }}>
        NoExcuses
      </div>

      <div style={{ flex: 1 }}>
        {NAV.map(({ to, label, icon }) => (
          <NavLink
            key={to}
            to={to}
            style={({ isActive }) => ({
              ...base,
              background: isActive ? 'rgba(34,197,94,0.12)' : 'transparent',
              color: isActive ? '#22C55E' : '#94A3B8',
            })}
          >
            <span>{icon}</span>
            <span>{label}</span>
          </NavLink>
        ))}
      </div>

      <button
        onClick={logout}
        style={{
          ...base,
          margin: '8px',
          background: 'transparent',
          border: 'none',
          color: '#64748B',
          cursor: 'pointer',
          width: 'calc(100% - 16px)',
          justifyContent: 'flex-start',
        }}
      >
        <span>🚪</span>
        <span>Déconnexion</span>
      </button>
    </nav>
  );
}
