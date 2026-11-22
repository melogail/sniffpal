<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

/**
 * The launch badge next to a member's name. One accent, tokens only, like the
 * verified icon it sits beside; the text is the accessible name, so the icon
 * is decorative.
 */
const { t } = useTranslations();
</script>

<template>
    <span
        class="bg-primary/10 text-primary inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
    >
        <Sparkles class="size-3.5" aria-hidden="true" />
        {{ t('profile.founding_member') }}
    </span>
</template>
