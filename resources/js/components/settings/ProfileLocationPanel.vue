<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SaveButton from '@/components/settings/SaveButton.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { fromCoordinateInput, toCoordinateInput } from '@/lib/coordinates';
import { changedProfileFields, profileIdentity } from '@/lib/profileForm';
import { update as updateProfile } from '@/routes/profile';
import type { ProfileFormData } from '@/types';

/**
 * Where the member is.
 *
 * `lat` and `lng` arrive as `number | string | null` — uncast decimal columns —
 * and are coerced to strings once here, at the input boundary. They are also
 * declared as a group, because each carries `required_with` on the other: a
 * PATCH holding one of them alone is a 422.
 */
const { profile, timezones } = defineProps<{
    profile: ProfileFormData;
    timezones: string[];
}>();

const original = {
    address: profile.address ?? '',
    city: profile.city ?? '',
    state: profile.state ?? '',
    country: profile.country ?? '',
    lat: toCoordinateInput(profile.lat),
    lng: toCoordinateInput(profile.lng),
    timezone: profile.timezone ?? '',
};

const form = useForm({ ...original });
const { t } = useTranslations();


function submit(): void {
    form.transform((data) => {
        const changed = changedProfileFields(data, original, [['lat', 'lng']]);

        if ('lat' in changed) {
            changed.lat = fromCoordinateInput(data.lat);
            changed.lng = fromCoordinateInput(data.lng);
        }

        return { ...profileIdentity(profile), ...changed };
    }).submit(updateProfile(), { preserveScroll: true });
}
</script>

<template>
    <SettingsPanel
        :title="t('profile.location')"
        :description="t('settings.location_description')"
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="address">{{ t('settings.address') }}</Label>
                <Input
                    id="address"
                    v-model="form.address"
                    autocomplete="street-address"
                />
                <InputError :message="form.errors.address" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="city">{{ t('profile.city') }}</Label>
                    <Input
                        id="city"
                        v-model="form.city"
                        autocomplete="address-level2"
                    />
                    <InputError :message="form.errors.city" />
                </div>
                <div class="grid gap-2">
                    <Label for="state">{{ t('settings.state') }}</Label>
                    <Input
                        id="state"
                        v-model="form.state"
                        autocomplete="address-level1"
                    />
                    <InputError :message="form.errors.state" />
                </div>
                <div class="grid gap-2">
                    <Label for="country">{{ t('profile.country') }}</Label>
                    <Input
                        id="country"
                        v-model="form.country"
                        autocomplete="country-name"
                    />
                    <InputError :message="form.errors.country" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="lat">{{ t('profile.latitude') }}</Label>
                    <Input
                        id="lat"
                        v-model="form.lat"
                        inputmode="decimal"
                        placeholder="31.2001"
                    />
                    <InputError :message="form.errors.lat" />
                </div>
                <div class="grid gap-2">
                    <Label for="lng">{{ t('profile.longitude') }}</Label>
                    <Input
                        id="lng"
                        v-model="form.lng"
                        inputmode="decimal"
                        placeholder="29.9187"
                    />
                    <InputError :message="form.errors.lng" />
                </div>
            </div>
            <p class="text-muted-foreground -mt-3 text-xs">
                {{ t('settings.coordinates_hint') }}
            </p>

            <div class="grid gap-2">
                <Label for="timezone">{{ t('settings.timezone') }}</Label>
                <Input
                    id="timezone"
                    v-model="form.timezone"
                    list="timezone-options"
                    placeholder="Africa/Cairo"
                />
                <datalist id="timezone-options">
                    <option
                        v-for="zone in timezones"
                        :key="zone"
                        :value="zone"
                    />
                </datalist>
                <InputError :message="form.errors.timezone" />
            </div>

            <SaveButton
                :processing="form.processing"
                :recently-successful="form.recentlySuccessful"
            />
        </form>
    </SettingsPanel>
</template>
