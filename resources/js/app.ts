import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';

// Read the brand colour from the --color-primary token so the Inertia
// progress bar stays in sync with the design system. Called at app
// creation rather than module eval time — in production builds the CSS
// is loaded via a linked stylesheet, and stylesheet parsing races with
// JS execution. Calling inside createInertiaApp's argument gives the
// browser a beat to finish the parse; the `|| '#3d35c8'` fallback
// handles the race losing anyway, matching the token default.
function readBrandColor(): string {
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-primary')
        .trim();
    return value || '#3d35c8';
}

createInertiaApp({
    progress: { color: readBrandColor() },
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
