import './bootstrap';
import './searchable-combobox';
import { createApp } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppSidebar from './components/navigation/AppSidebar.vue';
import ProductCreateModal from './components/products/ProductCreateModal.vue';
import RouteClientSelector from './components/routes/RouteClientSelector.vue';
import ClientCoordinates from './components/clients/ClientCoordinates.vue';
import RouteOperationsPanel from './components/routes/RouteOperationsPanel.vue';

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

const productCreate = document.getElementById('vue-product-create');
if (productCreate) {
    createApp(ProductCreateModal, {
        endpoint: productCreate.dataset.endpoint,
        csrf: productCreate.dataset.csrf,
        selectId: 'product',
    }).mount(productCreate);
}

const routeClientData = document.getElementById('route-clients-data');
if (routeClientData) {
    const legacyList = document.getElementById('client-list');
    const routeSection = legacyList?.closest('section');
    const routeClients = document.createElement('div');
    routeClients.id = 'vue-route-clients';
    routeSection?.replaceChildren(routeClients);
    createApp(RouteClientSelector, JSON.parse(routeClientData.textContent || '{}')).mount(routeClients);
}

const clientCoordinates = document.getElementById('vue-client-coordinates');
if (clientCoordinates) {
    document.querySelector('#client-form textarea[name="address"]')?.closest('section')?.append(clientCoordinates);
    createApp(ClientCoordinates, {
        initialLatitude: clientCoordinates.dataset.latitude,
        initialLongitude: clientCoordinates.dataset.longitude,
    }).mount(clientCoordinates);
}

const routeOperationsData = document.getElementById('route-operations-data');
if (routeOperationsData) {
    const props = JSON.parse(routeOperationsData.textContent || '{}');
    const root = document.getElementById('vue-route-operations');
    const main = root?.closest('main');
    const metrics = main?.querySelector('section.mb-5.grid');
    const dashboardTarget = document.createElement('div');
    dashboardTarget.id = 'route-dashboard-target';
    metrics?.replaceWith(dashboardTarget);
    const clientsHeading = [...(main?.querySelectorAll('h2') || [])].find(node => node.textContent.trim() === 'Clientes de esta ruta');
    const clientsCard = clientsHeading?.closest('article');
    clientsCard?.replaceWith(root);
    createApp(RouteOperationsPanel, { ...props, dashboardTarget: '#route-dashboard-target' }).mount(root);
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
