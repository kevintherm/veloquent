const getVeloConfig = () => {
    try {
        const meta = document.querySelector('meta[name="velo-config"]');
        if (!meta) {
            console.warn('Velo config meta tag not found. Using defaults.');
            return {
                api_prefix: 'api',
                admin_prefix: 'admin',
                logo_url: '/vendor/velo/logo.svg',
            };
        }
        return JSON.parse(meta.content);
    } catch (e) {
        console.error('Failed to parse Velo config', e);
        return {
            api_prefix: 'api',
            admin_prefix: 'admin',
            logo_url: '/vendor/velo/logo.svg',
        };
    }
};

export const VELO_CONFIG = getVeloConfig();

// Also make it available on window for components that still rely on it
window.VELO_CONFIG = VELO_CONFIG;
