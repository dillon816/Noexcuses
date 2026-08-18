import api from './axios';

export const getSeances     = (limit = 20)             => api.get(`/seances?limit=${limit}`);
export const getModeles     = ()                       => api.get('/seances/modeles');
export const getSeance      = (id)                     => api.get(`/seances/${id}`);
export const createSeance   = (data)                   => api.post('/seances', data);
export const createModele   = (data)                   => api.post('/seances', { ...data, modele: true });
export const updateSeance   = (id, data)               => api.patch(`/seances/${id}`, data);
export const addSerie       = (seanceId, data)         => api.post(`/seances/${seanceId}/series`, data);
export const updateSerie    = (seanceId, serieId, data) => api.patch(`/seances/${seanceId}/series/${serieId}`, data);
export const deleteSerie    = (seanceId, serieId)      => api.delete(`/seances/${seanceId}/series/${serieId}`);
export const dupliquerSeance = (id)                    => api.post(`/seances/${id}/dupliquer`);
export const terminerSeance = (id)                     => api.put(`/seances/${id}/terminer`);
export const rouvrirSeance  = (id)                     => api.put(`/seances/${id}/rouvrir`);
export const deleteSeance   = (id)                     => api.delete(`/seances/${id}`);
