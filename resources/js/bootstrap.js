import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { applyAuthHeader, getAuthToken } from './lib/tokenAuth';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

applyAuthHeader(window.axios);

window.axios.interceptors.request.use((config) => {
	const token = getAuthToken();

	if (token) {
		config.headers.Authorization = `Bearer ${token}`;
	} else if (config.headers?.Authorization) {
		delete config.headers.Authorization;
	}

	return config;
});

const broadcastConnection = (import.meta.env.VITE_BROADCAST_CONNECTION ?? 'reverb').toLowerCase();

const buildEchoConnectionConfig = () => {
	if (broadcastConnection === 'pusher') {
		const pusherScheme = import.meta.env.VITE_PUSHER_SCHEME ?? 'https';
		const pusherHost = import.meta.env.VITE_PUSHER_HOST || undefined;
		const pusherPort = Number(import.meta.env.VITE_PUSHER_PORT ?? (pusherScheme === 'https' ? 443 : 80));

		return {
			key: import.meta.env.VITE_PUSHER_APP_KEY,
			config: {
				broadcaster: 'pusher',
				key: import.meta.env.VITE_PUSHER_APP_KEY,
				cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
				wsHost: pusherHost,
				wsPort: pusherPort,
				wssPort: pusherPort,
				forceTLS: pusherScheme === 'https',
				enabledTransports: ['ws', 'wss'],
			},
		};
	}

	const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? (window.location.protocol === 'https:' ? 'https' : 'http');
	const reverbHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
	const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? (reverbScheme === 'https' ? 443 : 80));

	return {
		key: import.meta.env.VITE_REVERB_APP_KEY,
		config: {
			broadcaster: 'pusher',
			key: import.meta.env.VITE_REVERB_APP_KEY,
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
		authEndpoint: '/broadcasting/auth',
		auth: {
			headers: {
				Authorization: `Bearer ${token}`,
			},
		},
	});
};

window.connectEcho = connectEcho;
window.disconnectEcho = disconnectEcho;

// connectEcho();