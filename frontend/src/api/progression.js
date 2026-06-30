import api from './axios';

export const getDashboard          = ()           => api.get('/dashboard');
export const getStatsPoids         = (days = 90)  => api.get(`/progression/poids?days=${days}`);
export const getStatsCalories      = (days = 30)  => api.get(`/progression/calories?days=${days}`);
export const getStatsEntrainement  = (days = 30)  => api.get(`/progression/entrainement?days=${days}`);
export const logPoids              = (data)       => api.post('/profil/poids', data);
