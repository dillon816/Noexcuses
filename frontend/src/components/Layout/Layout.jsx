import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import MobileNav from './MobileNav';
import MobileHeader from './MobileHeader';

export default function Layout() {
  return (
    <div className="layout-root">
      <Sidebar />

      <main className="layout-main">
        <MobileHeader />
        <Outlet />
      </main>

      <MobileNav />
    </div>
  );
}
