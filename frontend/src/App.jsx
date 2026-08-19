import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Suspense, lazy } from 'react';
import { AuthProvider } from './context/AuthContext';
import { UIFeedbackProvider } from './components/common/UIFeedback';
import ProtectedRoute from './components/common/ProtectedRoute';
import Layout from './components/Layout/Layout';
import Login from './pages/Login';
import Register from './pages/Register';

// Pages chargées à la demande (code splitting) : le bundle initial est plus léger
// et l'application démarre plus vite. Chaque page devient un chunk séparé.
const Dashboard    = lazy(() => import('./pages/Dashboard'));
const Nutrition    = lazy(() => import('./pages/Nutrition'));
const Entrainement = lazy(() => import('./pages/Entrainement'));
const Recovery     = lazy(() => import('./pages/Recovery'));
const Progression  = lazy(() => import('./pages/Progression'));
const Profil       = lazy(() => import('./pages/Profil'));

export default function App() {
  return (
    <AuthProvider>
      <UIFeedbackProvider>
      <BrowserRouter>
        <Suspense fallback={<div style={{ padding: '40px', textAlign: 'center', color: '#64748B' }}>Chargement…</div>}>
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
        </Suspense>
      </BrowserRouter>
      </UIFeedbackProvider>
    </AuthProvider>
  );
}
