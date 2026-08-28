import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

const applyDocumentTheme = appearance => {
    const theme = appearance?.theme === 'day' ? 'day' : 'night';
    document.documentElement.classList.toggle('theme-day', theme === 'day');
    document.documentElement.classList.toggle('theme-night', theme !== 'day');
    document.documentElement.dataset.theme = theme;
};

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    resolve: name => {
        const load = pages[`./Pages/${name}.vue`];
        if (! load) {
            throw new Error(`No existe la página Inertia ${name}`);
        }

        return load();
    },
    setup({ el, App, props, plugin }) {
        applyDocumentTheme(props.initialPage?.props?.appearance);
        router.on('navigate', event => applyDocumentTheme(event.detail.page.props.appearance));

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#5b8cff' },
});
