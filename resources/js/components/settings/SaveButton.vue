<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

/** Submit plus the transient "Saved." every settings panel shows. */
const {
    processing,
    recentlySuccessful,
    disabled = false,
    label = undefined,
} = defineProps<{
    processing: boolean;
    recentlySuccessful: boolean;
    disabled?: boolean;
    label?: string;
}>();
const { t } = useTranslations();

</script>

<template>
    <div class="flex items-center gap-4">
        <Button
            type="submit"
            variant="default"
            :disabled="processing || disabled"
        >
            <Spinner v-if="processing" />
            {{ label ?? t('common.save') }}
        </Button>

        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <p
                v-show="recentlySuccessful"
                class="text-muted-foreground text-sm"
            >
                {{ t('settings.saved') }}
            </p>
        </Transition>
    </div>
</template>
