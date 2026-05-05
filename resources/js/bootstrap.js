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

const broadcastConnection = (VELO_CONFIG.realtime?.type ?? import.meta.env.VITE_BROADCAST_CONNECTION ?? 'reverb').toLowerCase();

const buildEchoConnectionConfig = () => {
	const realtime = VELO_CONFIG.realtime;

	if (broadcastConnection === 'pusher') {
		const pusher = realtime?.pusher;
		const pusherScheme = pusher?.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? 'https';
		let pusherHost = pusher?.host || import.meta.env.VITE_PUSHER_HOST || undefined;

		// If the host is explicitly set to a Pusher API endpoint, it's likely a misconfiguration
		// for WebSocket connections. Official Pusher users should rely on the cluster.
		if (pusherHost && pusherHost.includes('pusher.com') && pusherHost.startsWith('api-')) {
			pusherHost = undefined;
		}

		const pusherPort = Number(pusher?.port ?? import.meta.env.VITE_PUSHER_PORT ?? (pusherScheme === 'https' ? 443 : 80));
		const pusherKey = pusher?.key ?? import.meta.env.VITE_PUSHER_APP_KEY;

		return {
			key: pusherKey,
			config: {
				broadcaster: 'pusher',
				key: pusherKey,
				cluster: pusher?.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER,
				wsHost: pusherHost,
				wsPort: pusherPort,
				wssPort: pusherPort,
				forceTLS: pusherScheme === 'https',
				enabledTransports: ['ws', 'wss'],
			},
		};
	}

	const reverb = realtime?.reverb;
	const reverbScheme = reverb?.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? (window.location.protocol === 'https:' ? 'https' : 'http');
	const reverbHost = reverb?.host ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
	const reverbPort = Number(reverb?.port ?? import.meta.env.VITE_REVERB_PORT ?? (reverbScheme === 'https' ? 443 : 80));
	const reverbKey = reverb?.key ?? import.meta.env.VITE_REVERB_APP_KEY;

	return {
		key: reverbKey,
		config: {
			broadcaster: 'pusher',
			key: reverbKey,
			cluster: 'mt1',
			wsHost: reverbHost,
			wsPort: reverbPort,
			wssPort: reverbPort,
			forceTLS: reverbScheme === 'https',
			enabledTransports: ['ws', 'wss'],
		},
	};
};

const disconnectEcho = () => {
	if (window.Echo) {
		window.Echo.disconnect();
		window.Echo = null;
	}
};

const connectEcho = () => {
	const token = getAuthToken();
	const { key, config } = buildEchoConnectionConfig();

	if (!key || !token) {
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
