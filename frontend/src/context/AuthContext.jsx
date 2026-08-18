import { createContext, useContext, useState, useCallback } from 'react';
import { login as apiLogin, googleLogin as apiGoogleLogin } from '../api/auth';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem('user');
    return stored ? JSON.parse(stored) : null;
  });

  const login = useCallback(async (email, password) => {
    const res = await apiLogin(email, password);
    const token = res.data.token;
    localStorage.setItem('jwt_token', token);
    const decoded = parseJwt(token);
    const userData = { email, id: decoded?.id };
    localStorage.setItem('user', JSON.stringify(userData));
    setUser(userData);
    return userData;
  }, []);

  // Connexion via Google : on envoie le token Google au backend qui nous renvoie notre propre JWT
  const loginWithGoogle = useCallback(async (credential) => {
    const res = await apiGoogleLogin(credential);
    const token = res.data.token;
    localStorage.setItem('jwt_token', token);
    const decoded = parseJwt(token);
    const userData = { email: decoded?.username, id: decoded?.id };
    localStorage.setItem('user', JSON.stringify(userData));
    setUser(userData);
    return userData;
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('user');
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, login, loginWithGoogle, logout, isAuthenticated: !!user }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be inside AuthProvider');
  return ctx;
};

function parseJwt(token) {
  try {
    return JSON.parse(atob(token.split('.')[1]));
  } catch {
    return null;
  }
}
