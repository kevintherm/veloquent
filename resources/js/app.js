import './bootstrap';
import { createApp } from 'vue';
import axios from 'axios';
import App from './App.vue';
import { clearAuthSession } from './lib/auth';
import { isOnboardingInitialized } from './lib/onboarding';
import router from './router';

axios.interceptors.response.use(
	(response) => response,
	async (error) => {
		if (error?.response?.status !== 401) {
			return Promise.reject(error);
		}

		clearAuthSession();

		const publicPaths = ['/login', '/register'];
		const currentPath = router.currentRoute.value.path;

		if (!publicPaths.includes(currentPath)) {
			const initialized = await isOnboardingInitialized();
			await router.replace(initialized ? '/login' : '/register');
		}

		return Promise.reject(error);
	}
);

const app = createApp(App);
app.use(router);
app.mount('#app');
