<!--
    Tools → Newsletter: the list, searched and filtered by status, and
    downloaded as CSV (the subscribed list, or whatever's filtered).
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import StatusBadge from '../components/StatusBadge.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Head, Header, Input, Link, Panel, Select, StatusBadge, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        search: { type: String, default: null },
        status: { type: String, default: null },
        statuses: { type: Array, required: true },
        subscribers: { type: Array, required: true },
        pagination: { type: Object, required: true },
        counts: { type: Object, required: true },
        askName: { type: Boolean, default: false },
        canExport: { type: Boolean, default: false },
        canManage: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', filter: this.status ?? 'all', timer: null };
    },

    computed: {
        icon: () => icon,

        filterOptions() {
            return [{ value: 'all', label: __('Everyone') }, ...this.statuses];
        },

        filtered() {
            return Boolean(this.search) || Boolean(this.status);
        },

        exportUrl() {
            const params = new URLSearchParams();
            if (this.status) params.set('status', this.status);
            if (this.search) params.set('search', this.search);
            const query = params.toString();
            return this.urls.export + (query ? `?${query}` : '');
        },

        emptyHelp() {
            return __('Add :tag to a template for a sign-up form. People show here as soon as they sign up, and count once they’ve confirmed by email.', { tag: '{{ newsletter }}' });
        },

        exportLabel() {
            return this.filtered ? __('Download these (CSV)') : __('Download subscribed (CSV)');
        },
    },

    watch: {
        query() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.reload(), 300);
        },

        filter() {
            this.reload();
        },
    },

    methods: {
        params(page = 1) {
            const params = {};
            if (this.query) params.search = this.query;
            if (this.filter && this.filter !== 'all') params.status = this.filter;
            if (page > 1) params.page = page;
            return params;
        },

        reload() {
            router.get(this.urls.index, this.params(), { preserveState: true, replace: true });
        },

        pageUrl(page) {
            const query = new URLSearchParams(this.params(page)).toString();
            return this.urls.index + (query ? `?${query}` : '');
        },

        remove(subscriber) {
            if (confirm(__('Delete :email from the list? This can’t be undone.', { email: subscriber.email }))) {
                router.delete(subscriber.delete_url, { preserveScroll: true });
            }
        },
    },
};
</script>

<template>
    <Head :title="__('Newsletter')" />

    <div class="mx-auto max-w-6xl">
        <Header :title="__('Newsletter')" :icon="icon">
            <Button v-if="urls.settings" :href="urls.settings" :text="__('Settings')" icon="cog" />
            <Button v-if="canExport" :href="exportUrl" :text="exportLabel" icon="download" variant="primary" />
        </Header>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel v-for="s in statuses" :key="s.value" class="mb-0!">
                <div class="px-4 py-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400!">{{ s.label }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ counts[s.value] }}</div>
                </div>
            </Panel>
        </div>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row">
            <div class="grow">
                <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by email or name')" clearable :aria-label="__('Search')" />
            </div>
            <div class="sm:w-60">
                <Select v-model="filter" :options="filterOptions" option-label="label" option-value="value" :aria-label="__('Show')" />
            </div>
        </div>

        <Panel>
            <div v-if="subscribers.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Email') }}</TableColumn>
                        <TableColumn v-if="askName">{{ __('Name') }}</TableColumn>
                        <TableColumn>{{ __('Status') }}</TableColumn>
                        <TableColumn>{{ __('Signed up') }}</TableColumn>
                        <TableColumn>{{ __('Confirmed') }}</TableColumn>
                        <TableColumn v-if="canManage"><span class="sr-only">{{ __('Actions') }}</span></TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="subscriber in subscribers" :key="subscriber.id">
                            <TableCell class="font-medium">{{ subscriber.email }}</TableCell>
                            <TableCell v-if="askName">{{ subscriber.name ?? '' }}</TableCell>
                            <TableCell><StatusBadge :status="subscriber.status" :label="subscriber.status_label" /></TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ subscriber.signed_up }}</TableCell>
                            <TableCell class="whitespace-nowrap text-gray-600 dark:text-gray-400!">{{ subscriber.status === 'unsubscribed' ? __('Left :date', { date: subscriber.unsubscribed }) : (subscriber.confirmed ?? '—') }}</TableCell>
                            <TableCell v-if="canManage" class="text-right">
                                <Button size="sm" variant="ghost" icon="trash" :aria-label="__('Delete :email', { email: subscriber.email })" @click="remove(subscriber)" />
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>

            <div v-else class="px-6 py-10 text-center">
                <p v-if="filtered" class="text-gray-600 dark:text-gray-400!">{{ __('Nobody matches.') }}</p>
                <template v-else>
                    <p class="font-medium">{{ __('Nobody’s signed up yet') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">{{ emptyHelp }}</p>
                </template>
            </div>
        </Panel>

        <nav v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm" :aria-label="__('Pages')">
            <Link v-if="pagination.page > 1" :href="pageUrl(pagination.page - 1)" class="hover:underline!">← {{ __('Previous') }}</Link>
            <span v-else />
            <span class="text-gray-600 dark:text-gray-400!">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="pageUrl(pagination.page + 1)" class="hover:underline!">{{ __('Next') }} →</Link>
            <span v-else />
        </nav>
    </div>
</template>
