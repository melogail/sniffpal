<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ExternalLink, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import CreatePetButton from '@/components/pets/CreatePetButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { register } from '@/routes';
import type { LaunchProps } from '@/types';

/**
 * The honest "we're new" notice at the top of the feed (marketing decision
 * 2026-11-22, §6.7): says the site just launched and offers the founding
 * badge for a real listing. Renders nothing once the offer closes, which
 * `HomeController::launchProps()` decides so the rule lives in one place.
 *
 * A signed-in reader gets the feed's own publish control; a guest gets a
 * link to register, since `pets.create` sits behind `auth` + `verified`.
 */
const { launch } = defineProps<{ launch: LaunchProps }>();

const page = usePage();
const { t } = useTranslations();

const isSignedIn = computed(() => (page.props.auth.user ?? null) !== null);
</script>

<template>
    <Card v-if="launch.founding_open" class="mb-6">
        <CardContent
            class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <p
                    class="flex items-center gap-2 font-serif text-xl font-medium"
                >
                    <Sparkles class="text-primary size-5" aria-hidden="true" />
                    {{ t('launch.title') }}
                </p>
                <p class="text-muted-foreground text-sm">
                    {{
                        t('launch.body', {
                            remaining: launch.founding_remaining,
                        })
                    }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <CreatePetButton v-if="isSignedIn" />
                <Button v-else as-child variant="default">
                    <Link :href="register()">{{
                        t('launch.register_cta')
                    }}</Link>
                </Button>
                <Button
                    v-if="launch.facebook_group_url"
                    as-child
                    variant="outline"
                >
                    <a
                        :href="launch.facebook_group_url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ t('launch.join_group') }}
                        <ExternalLink class="size-4" aria-hidden="true" />
                    </a>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
