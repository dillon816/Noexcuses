import api from './axios';

export const searchAliments  = (term)         => api.get(`/nutrition/aliments?search=${encodeURIComponent(term)}`);
export const getJournal      = (date)          => api.get(`/nutrition/journal?date=${date}`);
export const addRepas        = (data)          => api.post('/nutrition/repas', data);
export const deleteRepas     = (id)            => api.delete(`/nutrition/repas/${id}`);
