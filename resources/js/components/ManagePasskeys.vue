<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import type { Passkey } from '@/types/auth';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import { useTranslations } from '@/composables/useTranslations';
import { destroy } from '@/routes/passkey';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
const { t } = useTranslations();

</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <Heading
            variant="small"
            :title="t('security.passkeys')"
            :description="t('security.passkeys_description')"
        />

        <div class="border-border overflow-hidden rounded-[14px] border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="bg-secondary text-secondary-foreground mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl"
                >
                    <KeyRound class="h-7 w-7" />
                </div>
                <p class="font-medium">{{ t('security.no_passkeys') }}</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ t('security.no_passkeys_hint') }}
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
