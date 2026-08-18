import api from './axios';

export const register    = (data) => api.post('/register', data);
export const login       = (email, password) => api.post('/login', { email, password });
export const googleLogin = (credential) => api.post('/auth/google', { credential });
