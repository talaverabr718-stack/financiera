import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth?.permissions ?? {});
    const canView = module => permissions.value[module]?.view === true;
    const canManage = module => permissions.value[module]?.manage === true;
    const canFull = module => permissions.value[module]?.full === true;

    return { permissions, canView, canManage, canFull };
}
