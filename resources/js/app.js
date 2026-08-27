import './bootstrap';
import './searchable-combobox';
import { createApp } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppSidebar from './components/navigation/AppSidebar.vue';

const inertiaPages = import.meta.glob('./Pages/**/*.vue');
if (document.querySelector('script[data-page="app"]') && document.getElementById('app')) {
    createInertiaApp({
        resolve: name => inertiaPages[`./Pages/${name}.vue`](),
        setup({ el, App, props, plugin }) {
            createApp(App, props).use(plugin).mount(el);
        },
        progress: { color: '#6366f1' },
    });
}

const legacySidebar = document.getElementById('legacy-vue-sidebar');
if (legacySidebar) {
    createApp(AppSidebar, {
        open: false,
        navigation: JSON.parse(legacySidebar.dataset.navigation || '[]'),
        user: JSON.parse(legacySidebar.dataset.user || '{}'),
        routes: JSON.parse(legacySidebar.dataset.routes || '{}'),
        currentUrl: legacySidebar.dataset.currentUrl,
        csrf: legacySidebar.dataset.csrf,
        inertiaEnabled: false,
        onClose: () => {
            document.getElementById('sidebar')?.classList.add('-translate-x-full');
            document.getElementById('overlay')?.classList.add('hidden');
        },
    }).mount(legacySidebar);
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
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button || button.dataset.allowRepeat === 'true') return;
        form.dataset.loading = 'true'; button.disabled = true; button.setAttribute('aria-busy', 'true');
    }));
});
