import './bootstrap';
import './searchable-combobox';
import { createApp } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

const applyDocumentTheme = appearance => {
    const theme = appearance?.theme === 'day' ? 'day' : 'night';
    document.documentElement.classList.toggle('theme-day', theme === 'day');
    document.documentElement.classList.toggle('theme-night', theme !== 'day');
    document.documentElement.dataset.theme = theme;
};

const inertiaPages = import.meta.glob('./Pages/**/*.vue');
if (document.querySelector('script[data-page="app"]') && document.getElementById('app')) {
    createInertiaApp({
        resolve: name => inertiaPages[`./Pages/${name}.vue`](),
        setup({ el, App, props, plugin }) {
            applyDocumentTheme(props.initialPage?.props?.appearance);
            router.on('navigate', event => applyDocumentTheme(event.detail.page.props.appearance));
            createApp(App, props).use(plugin).mount(el);
        },
        progress: { color: '#5b8cff' },
    });
}

import {
    ArrowLeft, ArrowLeftRight, ArrowUpRight, Banknote, Bell, BookOpen, Briefcase, BriefcaseBusiness, Building2, Calendar, CalendarDays, ChartNoAxesCombined, CircleCheck, Download, EllipsisVertical,
    FileCheck2, HandCoins, Landmark, LayoutDashboard, Map, MapPinCheck,
    ContactRound, FilePlus2, Inbox, LoaderCircle, LogOut, Mail, MapPin, Menu, PanelLeftClose, Pencil, Plus, Route, Save, Search, Settings, SlidersHorizontal, UserPlus, UserRound, UserRoundX, Users, WalletCards, X,
    createIcons,
} from 'lucide';

document.addEventListener('DOMContentLoaded', () => createIcons({
    icons: {
        ArrowLeft, ArrowLeftRight, ArrowUpRight, Banknote, Bell, BookOpen, Briefcase, BriefcaseBusiness, Building2, Calendar, CalendarDays, ChartNoAxesCombined, CircleCheck, Download, EllipsisVertical,
        FileCheck2, HandCoins, Landmark, LayoutDashboard, Map, MapPinCheck,
        ContactRound, FilePlus2, Inbox, LoaderCircle, LogOut, Mail, MapPin, Menu, PanelLeftClose, Pencil, Plus, Route, Save, Search, Settings, SlidersHorizontal, UserPlus, UserRound, UserRoundX, Users, WalletCards, X,
    },
}));

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const closeDrawer = () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); };
    document.getElementById('menu')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
    document.getElementById('sidebar-close')?.addEventListener('click', closeDrawer);
    overlay?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeDrawer(); });
    document.body.classList.remove('sidebar-collapsed');
    localStorage.removeItem('sidebar-collapsed');
    document.querySelectorAll('form').forEach(form => {
        if (form.closest('#app')) return;
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (!button || button.dataset.allowRepeat === 'true') return;
            form.dataset.loading = 'true'; button.disabled = true; button.setAttribute('aria-busy', 'true');
        });
    });
});
