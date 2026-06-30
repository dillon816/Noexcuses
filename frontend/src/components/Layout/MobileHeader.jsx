import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function MobileHeader() {
  const { logout } = useAuth();
  const navigate = useNavigate();

  // Déconnexion mobile : vide la session puis redirige vers le login
  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  return (
    <header className="layout-mobile-header mobile-header-bar">
      <span style={{ color: '#22C55E', fontWeight: 700, fontSize: '18px', letterSpacing: '-0.5px' }}>
        NoExcuses
      </span>
      <div style={{ display: 'flex', alignItems: 'center', gap: '18px' }}>
        <NavLink
          to="/profil"
          style={({ isActive }) => ({
            color: isActive ? '#22C55E' : '#94A3B8',
            fontSize: '22px',
            lineHeight: 1,
          })}
          title="Mon Profil"
        >
          👤
        </NavLink>
        <button
          onClick={handleLogout}
          style={{ background: 'none', border: 'none', color: '#94A3B8', fontSize: '20px', lineHeight: 1, cursor: 'pointer', padding: 0 }}
          title="Déconnexion"
        >
          🚪
        </button>
      </div>
    </header>
  );
}
