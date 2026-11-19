<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('settings.appearance_title')" />

    <!-- The document's h1 is the settings banner in `layouts/settings/Layout.vue`. -->

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance_title')"
            :description="t('settings.appearance_heading_description')"
        />
        <AppearanceTabs />
    </div>
</template>
