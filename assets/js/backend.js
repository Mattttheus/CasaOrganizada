// Escolhe o backend (MySQL local, Supabase ou vitrine) conforme BACKEND em config.js.
import { BACKEND } from './config.js';
import { configured as supabaseConfigured } from './supabaseClient.js';
import * as supabaseBackend from './data.js';
import * as localBackend from './localApi.js';
import * as demoBackend from './demo.js';

function isLocalNetwork() {
    const host = location.hostname;
    return host === 'localhost' || host === '127.0.0.1' || host.endsWith('.local')
        || /^(192\.168|10)\./.test(host) || /^172\.(1[6-9]|2\d|3[01])\./.test(host);
}

export const mode = BACKEND === 'auto' ? (isLocalNetwork() ? 'mysql' : 'demo') : BACKEND;
export const backend = { mysql: localBackend, supabase: supabaseBackend, demo: demoBackend }[mode];
export const configured = mode !== 'supabase' || supabaseConfigured;
