import { NavLink } from 'react-router-dom';

const NAV = [
  { to: '/dashboard',    label: 'Home',     icon: '▦' },
  { to: '/nutrition',    label: 'Nutrition', icon: '🥗' },
  { to: '/entrainement', label: 'Training',  icon: '🏋' },
  { to: '/recovery',     label: 'Recovery',  icon: '⚡' },
  { to: '/progression',  label: 'Stats',     icon: '📈' },
];

export default function MobileNav() {
  return (
    <nav style={{
      position: 'fixed',
      bottom: 0, left: 0, right: 0,
      height: 'var(--mobile-nav-h)',
      background: 'var(--color-sidebar)',
      display: 'flex',
      borderTop: '1px solid #1E293B',
      zIndex: 100,
    }}>
      {NAV.map(({ to, label, icon }) => (
        <NavLink
          key={to}
          to={to}
          style={({ isActive }) => ({
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '2px',
            color: isActive ? '#22C55E' : '#64748B',
            fontSize: '10px',
            fontWeight: 500,
            textDecoration: 'none',
          })}
        >
          <span style={{ fontSize: '20px' }}>{icon}</span>
          <span>{label}</span>
        </NavLink>
      ))}
    </nav>
  );
}
