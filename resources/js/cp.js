import Index from './pages/Index.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('newsletter::Index', Index);
});
