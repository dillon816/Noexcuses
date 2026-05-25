import api from './axios';

export const getScore       = ()     => api.get('/recovery/score');
export const calculateScore = (date) => api.post('/recovery/score', { date });
export const logSommeil     = (data) => api.post('/recovery/sommeil', data);
