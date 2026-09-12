// Pequenos utilitários compartilhados entre app.js e calendar.js.
export const money = value => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
export const dateBR = value => value ? new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR') : '-';
export const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
export const todayStr = () => new Date().toISOString().slice(0, 10);
