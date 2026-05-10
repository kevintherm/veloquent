import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getAuthToken } from './lib/tokenAuth';
import { VELO_CONFIG } from './lib/config';

window.axios = axios;
window.Pusher = Pusher;

const apiPrefix = VELO_CONFIG.api_prefix || 'api';
window.axios.defaults.baseURL = `/${apiPrefix}`;

window.axios.interceptors.request.use((config) => {
	if (config.headers?.Authorization) {
		return config;
	}

	const token = getAuthToken();

	if (token) {
		config.headers.Authorization = `Bearer ${token}`;
	}

	return config;
});

const rt = VELO_CONFIG.realtime ?? {};

const driver = (rt.type ?? import.meta.env.VITE_BROADCAST_CONNECTION ?? 'reverb').toLowerCase();

const LOOPBACK = /^(127\.\d+\.\d+\.\d+|::1|localhost)$/i;

const scheme = rt.scheme ?? (window.location.protocol === 'https:' ? 'https' : 'http');
const defaultPort = scheme === 'https' ? 443 : 80;

const isLoopback = !rt.host || LOOPBACK.test(rt.host);
const host = isLoopback ? window.location.hostname : rt.host;
const port = isLoopback ? defaultPort : (rt.port != null ? Number(rt.port) : defaultPort);

const key = rt.key;
const cluster = rt.cluster;
const forceTLS = scheme === 'https';

const buildEchoConnectionConfig = () => {
	switch (driver) {
		case 'pusher': {
			const wsHost = (host && host.includes('pusher.com') && host.startsWith('api-'))
				? undefined
				: host;

			return {
				key,
				config: {
					broadcaster: 'pusher',
					key,
					cluster,
					...(wsHost ? { wsHost, wsPort: port, wssPort: port } : {}),
					forceTLS,
					enabledTransports: ['ws', 'wss'],
				},
			};
		}

		case 'ably':
			return {
				key,
				config: {
					broadcaster: 'pusher',
					key,
					wsHost: host,
					wsPort: port,
					wssPort: port,
					forceTLS,
					enabledTransports: ['ws', 'wss'],
				},
			};

		case 'reverb':
		default:
			return {
				key,
				config: {
					broadcaster: 'reverb',
					key,
					wsHost: host,
					wsPort: port,
					wssPort: port,
					forceTLS,
					enabledTransports: ['ws', 'wss'],
				},
			};
	}
};

const disconnectEcho = () => {
	if (window.Echo) {
		window.Echo.disconnect();
		window.Echo = null;
	}
};

const connectEcho = () => {
	const token = getAuthToken();
	const { key: echoKey, config } = buildEchoConnectionConfig();

	if (!echoKey || !token) {
		disconnectEcho();
		return;
	}

	disconnectEcho();

	window.Echo = new Echo({
		...config,
		authEndpoint: `/${apiPrefix}/broadcasting/auth`,
		auth: {
			headers: {
				Authorization: `Bearer ${token}`,
			},
		},
	});
};

window.connectEcho = connectEcho;
window.disconnectEcho = disconnectEcho;

connectEcho();
