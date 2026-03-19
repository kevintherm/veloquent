import './bootstrap';
import { createApp } from 'vue';
import axios from 'axios';
import App from './App.vue';
import { clearAuthSession } from './lib/auth';
import { isOnboardingInitialized } from './lib/onboarding';
import { toast } from 'vue-sonner';
import router from './router';

axios.interceptors.response.use(
	(response) => response,
	async (error) => {
		if (error?.config && !error.config.__toastHandled) {
			const message = error?.response?.data?.message || error?.message || 'Something went wrong.';
			toast.error(message);
			error.config.__toastHandled = true;
		}

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
