import api from './axios';

export const getSeances     = (limit = 20)         => api.get(`/seances?limit=${limit}`);
export const getSeance      = (id)                 => api.get(`/seances/${id}`);
export const createSeance   = (data)               => api.post('/seances', data);
export const addSerie       = (seanceId, data)     => api.post(`/seances/${seanceId}/series`, data);
export const terminerSeance = (id)                 => api.put(`/seances/${id}/terminer`);
export const deleteSeance   = (id)                 => api.delete(`/seances/${id}`);
