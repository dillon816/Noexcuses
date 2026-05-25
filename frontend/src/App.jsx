import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/common/ProtectedRoute';
import Layout from './components/Layout/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import Nutrition from './pages/Nutrition';
import Entrainement from './pages/Entrainement';
import Recovery from './pages/Recovery';
import Progression from './pages/Progression';
import Profil from './pages/Profil';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login"    element={<Login />} />
          <Route path="/register" element={<Register />} />

          <Route path="/" element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard"    element={<Dashboard />} />
            <Route path="nutrition"    element={<Nutrition />} />
            <Route path="entrainement" element={<Entrainement />} />
            <Route path="recovery"     element={<Recovery />} />
            <Route path="progression"  element={<Progression />} />
            <Route path="profil"       element={<Profil />} />
          </Route>

          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
