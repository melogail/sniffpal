<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import ProfilePrivacyPanel from "@/components/settings/ProfilePrivacyPanel.vue";
import { useTranslations } from "@/composables/useTranslations";
import type { ProfileFormData } from "@/types";

/**
 * The privacy screen. Its panel posts to `profile.update`, which answers
 * `back()`, so a save lands here rather than on the profile page.
 */
defineProps<{
    profile: ProfileFormData;
}>();
const { t } = useTranslations();

</script>

<template>
    <div class="flex flex-col space-y-12">
        <Head :title="t('settings.privacy_title')" />

        <!-- The document's h1 is the settings banner in `layouts/settings/Layout.vue`. -->
        <Heading
            variant="small"
            :title="t('settings.privacy')"
            :description="t('settings.privacy_heading_description')"
        />

        <ProfilePrivacyPanel :profile="profile" />
    </div>
</template>
