<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { support } from '@/routes';

/**
 * The community guidelines. `Route::inertia('guidelines', 'Guidelines')`, so
 * no props beyond the shared ones, and nothing to guard — public, in
 * `PublicLayout`, with `auth.user` null for the guest it is also shown to.
 *
 * Moderation notifications (`moderation_warning`,
 * `moderation_content_removed`) and the deactivation mail deep-link here, so
 * this page is what a member reads right after a decision about them. The
 * strike ladder below describes what the moderator's Nova action enforces;
 * copy edits are a lang change, ladder changes are a backend change first.
 */
const { t } = useTranslations();

const sections = computed(() => [
    { title: t('guidelines.listings'), rules: rulesOf('listings', 6) },
    { title: t('guidelines.profiles'), rules: rulesOf('profiles', 3) },
    { title: t('guidelines.comments'), rules: rulesOf('comments', 3) },
]);

const strikes = computed(() => rulesOf('strike', 3));

function rulesOf(group: string, count: number): string[] {
    return Array.from({ length: count }, (_, i) =>
        t(`guidelines.${group}_${i + 1}`),
    );
}
</script>

<template>
    <div class="mx-auto w-full max-w-3xl space-y-10 p-4 sm:p-6 lg:p-8">
        <Head :title="t('guidelines.title')" />

        <!-- `Heading` renders an h2, so the page still owes the document an h1. -->
        <h1 class="sr-only">{{ t('guidelines.title') }}</h1>

        <Heading
            :title="t('guidelines.title')"
            :description="t('guidelines.intro')"
        />

        <section
            v-for="section in sections"
            :key="section.title"
            class="space-y-3"
        >
            <h2
                class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
            >
                {{ section.title }}
            </h2>
            <ul class="text-foreground list-disc space-y-1.5 ps-5 text-sm">
                <li v-for="rule in section.rules" :key="rule">{{ rule }}</li>
            </ul>
        </section>

        <section class="border-border space-y-3 border-t pt-8">
            <h2
                class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
            >
                {{ t('guidelines.enforcement') }}
            </h2>
            <p class="text-muted-foreground text-sm">
                {{ t('guidelines.enforcement_intro') }}
            </p>
            <ol class="text-foreground list-decimal space-y-1.5 ps-5 text-sm">
                <li v-for="strike in strikes" :key="strike">{{ strike }}</li>
            </ol>
        </section>

        <section class="border-border space-y-3 border-t pt-8">
            <h2
                class="font-serif text-[26px] leading-[1.15] font-medium tracking-[-0.01em]"
            >
                {{ t('guidelines.appeals') }}
            </h2>
            <p class="text-muted-foreground text-sm">
                {{ t('guidelines.appeals_body') }}
            </p>

            <Button as-child variant="secondary">
                <Link :href="support()">
                    <MessageSquare class="size-4" />
                    {{ t('guidelines.appeals_link') }}
                </Link>
            </Button>
        </section>
    </div>
</template>
