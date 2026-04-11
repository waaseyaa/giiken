import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';

// Read the brand colour from the --color-primary token so the Inertia
// progress bar stays in sync with the design system. The CSS import above
// is processed before this line executes, so the computed style is
// already populated.
const progressColor =
    getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() ||
    '#3d35c8';

createInertiaApp({
    progress: { color: progressColor },
    resolve: (name: string) => {
        const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        return page?.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
