<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ReportDialog from '@/components/reports/ReportDialog.vue';
import RatingStars from '@/components/reviews/RatingStars.vue';
import ReviewEditDialog from '@/components/reviews/ReviewEditDialog.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/datetime';
import { show as showProfile } from '@/routes/profile';
import { destroy as destroyReview } from '@/routes/reviews';
import type { ReportReasonsByType, Review, ReviewBounds } from '@/types';

const { review, reportReasons } = defineProps<{
    review: Review;
    /** The page's `reviewBounds` prop — the stars read their scale from it. */
    bounds: ReviewBounds;
    reportReasons: ReportReasonsByType;
}>();

const { tag } = useLocale();
const { t } = useTranslations();


const author = computed(() => review.author);
const writtenAt = computed(() => formatRelative(review.created_at, tag.value));
</script>

<template>
    <article class="border-border flex gap-3 border-b py-4 last:border-b-0">
        <UserAvatar
            :name="author?.name ?? t('reviews.someone')"
            :avatar="author?.avatar ?? null"
            class="size-9 shrink-0"
        />

        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <Link
                    v-if="author"
                    :href="showProfile(author.id)"
                    class="font-medium hover:underline"
                >
                    {{ author.name }}
                </Link>
                <span v-else class="font-medium">{{
                    t('reviews.someone')
                }}</span>
                <RatingStars :rate="review.rate" :max="bounds.max_rate" />
                <span class="text-muted-foreground text-xs">{{
                    writtenAt
                }}</span>
            </div>

            <p
                v-if="review.comment"
                class="text-sm leading-relaxed whitespace-pre-line"
            >
                {{ review.comment }}
            </p>
            <p v-else class="text-muted-foreground text-sm italic">
                {{ t('reviews.no_comment') }}
            </p>

            <div class="flex flex-wrap items-center gap-1">
                <ReviewEditDialog
                    v-if="review.can_edit"
                    :review="review"
                    :bounds="bounds"
                />

                <Button
                    v-if="review.can_delete"
                    as-child
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                >
                    <Link
                        :href="destroyReview(review.id)"
                        as="button"
                        preserve-scroll
                    >
                        <Trash2 class="size-4" />
                        {{ t('common.delete') }}
                    </Link>
                </Button>

                <ReportDialog
                    v-if="!review.is_author"
                    reportable-type="review"
                    :reportable-id="review.id"
                    :reasons="reportReasons"
                    :reported="review.has_reported"
                />
            </div>
        </div>
    </article>
</template>
