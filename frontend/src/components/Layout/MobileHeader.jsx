import { NavLink } from 'react-router-dom';

export default function MobileHeader() {
  return (
    <header className="layout-mobile-header mobile-header-bar">
      <span style={{ color: '#22C55E', fontWeight: 700, fontSize: '18px', letterSpacing: '-0.5px' }}>
        NoExcuses
      </span>
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
    </header>
  );
}
