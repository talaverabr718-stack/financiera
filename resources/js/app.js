import './bootstrap';
import './searchable-combobox';
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
    const collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        const next = !document.body.classList.contains('sidebar-collapsed');
        document.body.classList.toggle('sidebar-collapsed', next);
        localStorage.setItem('sidebar-collapsed', String(next));
    });
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button || button.dataset.allowRepeat === 'true') return;
        form.dataset.loading = 'true'; button.disabled = true; button.setAttribute('aria-busy', 'true');
    }));
});
