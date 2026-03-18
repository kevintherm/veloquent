import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// import { configureEcho } from "@laravel/echo-vue";

// configureEcho({
//     broadcaster: "pusher",
//     // key: import.meta.env.VITE_REVERB_APP_KEY,
//     // wsHost: import.meta.env.VITE_REVERB_HOST,
//     // wsPort: import.meta.env.VITE_REVERB_PORT,
//     // wssPort: import.meta.env.VITE_REVERB_PORT,
//     // forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
//     // enabledTransports: ['ws', 'wss'],
// });