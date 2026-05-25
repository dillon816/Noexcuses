import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import MobileNav from './MobileNav';

const BREAKPOINT = 768;

export default function Layout() {
  const isMobile = window.innerWidth < BREAKPOINT;

  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      {!isMobile && <Sidebar />}

      <main style={{
        flex: 1,
        marginLeft: isMobile ? 0 : 'var(--sidebar-width)',
        paddingBottom: isMobile ? 'var(--mobile-nav-h)' : 0,
        background: 'var(--color-bg)',
        minHeight: '100vh',
      }}>
        <Outlet />
      </main>

      {isMobile && <MobileNav />}
    </div>
  );
}
