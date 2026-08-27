import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

export function useResourceFilters(initial, endpoint) {
    const filters = reactive({ ...initial });
    let timer;
    watch(filters, () => {
        clearTimeout(timer);
        timer = setTimeout(() => router.get(endpoint, filters, { preserveState: true, preserveScroll: true, replace: true }), 250);
    });
    const clear = () => Object.keys(filters).forEach(key => filters[key] = '');
    return { filters, clear };
}
