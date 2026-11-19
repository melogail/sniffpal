<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import PetListingCard from '@/components/pets/PetListingCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated, PetCard } from '@/types';

/**
 * The profile's listings as a **visitor** sees them: the card grid, nine to a
 * page (`petconnect.profiles.listings_per_page`). The owner sees
 * `ProfileListingsTable` instead; `pages/profile/Show.vue` decides on
 * `profile.is_self`.
 *
 * Paged under the `listings` page name, so the links have to be scoped to the
 * `listings` prop or turning a page would reset the reviews paginator beside
 * it.
 */
defineProps<{
    listings: Paginated<PetCard>;
    name: string;
}>();
const { t } = useTranslations();

</script>

<template>
    <section class="space-y-4">
        <EmptyState
            v-if="listings.data.length === 0"
            :icon="PawPrint"
            :title="t('profile.no_listings')"
            :description="t('profile.no_listings_hint', { name })"
        />

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <PetListingCard
                    v-for="pet in listings.data"
                    :key="pet.id"
                    :pet="pet"
                />
            </div>

            <Pagination :links="listings.meta.links" :only="['listings']" />
        </template>
    </section>
</template>
