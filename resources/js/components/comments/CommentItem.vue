<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import '@/lib/echo';
import { computed, ref } from 'vue';
import CommentBody from '@/components/comments/CommentBody.vue';
import CommentComposer from '@/components/comments/CommentComposer.vue';
import CommentComposerGate from '@/components/comments/CommentComposerGate.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { replies as commentReplies } from '@/routes/comments';
import type {
    Comment,
    CommentableType,
    CommentPreview,
    CommentThreadChanged,
    Paginated,
    ReportReasonsByType,
} from '@/types';

/**
 * A thread root, its reply preview and the composer that adds to it.
 *
 * The page ships at most `petconnect.pets.detail_reply_preview` replies per
 * comment while `replies_count` is the true total, so anything beyond the
 * preview is paged in from `comments.replies` — a **JSON** endpoint, not a
 * page, so it goes through `useHttp` rather than a visit. Replies cannot be
 * paginated per parent inside a single eager load, which is why expanding one
 * comment is a request of its own. A feed card carries no replies at all, only
 * the count, so inside the comments dialog every root starts collapsed and the
 * first "show replies" is that comment's page one.
 *
 * Both sources are merged by id: page 1 of the endpoint overlaps the preview by
 * design, and posting a reply shifts every page boundary by one.
 *
 * Replies are indented behind a rule (`border-s-2 ps-4`, both logical, so it
 * flips in Arabic) rather than by margin alone — legacy's own treatment, and
 * the thing that makes a two-level thread readable. There is no third level to
 * draw: `ValidateParentBelongsToCommentable` refuses a reply to a reply, so
 * `CommentBody` is rendered flat here and never recursively.
 *
 * The reply composer sits behind `CommentComposerGate` for the same reason the
 * thread composer does: `comments.store` is `auth` **+ `verified`**, so an
 * unverified reader who opened the reply box is told what is missing rather
 * than handed a form whose submit navigates the page away.
 */
const { comment, commentableType, commentableId } = defineProps<{
    comment: Comment;
    commentableType: CommentableType;
    commentableId: number;
    /** `petconnect.comments.max_length`, when the page was shipped it. */
    maxLength?: number | null;
    canInteract: boolean;
    reportReasons?: ReportReasonsByType;
}>();

/**
 * What this root and its replies added to or removed from the discussion, for a
 * container whose comment counter is not refreshed by the write itself.
 */
const emit = defineEmits<{
    posted: [];
    deleted: [count: number];
}>();

const { t } = useTranslations();

const replying = ref(false);
const fetched = ref<CommentPreview[]>([]);
const loadedPage = ref(0);
const lastPage = ref<number | null>(null);
/** The newest `meta.total` seen — a fresher count than the prop's snapshot. */
const fetchedTotal = ref<number | null>(null);
const loading = ref(false);
/** Whether the preview prop is still part of the list; a replace drops it. */
const seeded = ref(true);

const http = useHttp<Record<string, never>, Paginated<CommentPreview>>();

/** Newest-first everywhere it comes from, flipped into reading order here. */
const replies = computed<CommentPreview[]>(() => {
    const byId = new Map<number, CommentPreview>();

    for (const reply of [
        ...(seeded.value ? (comment.replies ?? []) : []),
        ...fetched.value,
    ]) {
        byId.set(reply.id, reply);
    }

    return [...byId.values()].sort(
        (a, b) => a.created_at.localeCompare(b.created_at) || a.id - b.id,
    );
});

const hasMore = computed(() =>
    lastPage.value === null
        ? replies.value.length < comment.replies_count
        : loadedPage.value < lastPage.value,
);

/**
 * What the button is allowed to advertise: the replies that are **not** on
 * screen already, and never a number below one.
 *
 * It used to advertise `replies_count`, which counts the replies the reader is
 * looking at as well as the ones they are not, so every page loaded left the
 * button repeating the same total.
 *
 * Subtracting `replies.length` from it is not enough on its own. `replies_count`
 * is a snapshot from when the payload was built while `replies` is the thread as
 * it is now, so the two can legitimately disagree — a reply posted by someone
 * else after the snapshot arrives in a fetched page the snapshot never counted —
 * and the raw difference goes negative, rendering `Show replies (-1)`. The feed
 * card's comment button carries a floor for the same snapshot-versus-now
 * mismatch; see `pets/card/PetCardCommentButton.vue`.
 *
 * Two things keep it honest. Every fetched page carries `meta.total`, a fresher
 * count of the same quantity, so the newest one wins and the prop is only the
 * starting value. And the result is floored at one rather than at zero, because
 * it is only ever read under `hasMore` — which past the first fetch is the
 * paginator's own `current_page < last_page`, not arithmetic over a total — so a
 * `(0)` on a button that does load more replies misstates the thread in the
 * other direction. Before the first fetch the two agree by construction:
 * `hasMore` is that same difference being positive.
 *
 * The number is the whole of the button's accessible name, so there is no
 * second copy of it to fall out of step. `comments.show_replies` parenthesises
 * the count rather than inflecting a noun by it, so there is no singular form
 * to get wrong either.
 */
const advertisedReplies = computed(() =>
    Math.max(
        1,
        (fetchedTotal.value ?? comment.replies_count) - replies.value.length,
    ),
);

/**
 * One page of replies. `replace` swaps what is on screen for page one, preview
 * included, which is how a reply someone else edited or deleted leaves the
 * list; `loadMore` extends it instead.
 */
async function requestReplies(page: number, replace: boolean): Promise<void> {
    loading.value = true;

    try {
        const response = await http.get(
            commentReplies.url(comment.id, { query: { page } }),
        );

        if (replace) {
            seeded.value = false;
            fetched.value = response.data;
        } else {
            fetched.value = [...fetched.value, ...response.data];
        }

        loadedPage.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
        fetchedTotal.value = response.meta.total;
    } finally {
        loading.value = false;
    }
}

function loadMore(): Promise<void> {
    return loading.value || !hasMore.value
        ? Promise.resolve()
        : requestReplies(loadedPage.value + 1, false);
}

/**
 * Realtime: a reply to this root written, edited or deleted by anyone lands as
 * a page one reload. Every row on the page shares the one channel subscription
 * (`@laravel/echo-vue` refcounts it) and keeps only the events about itself.
 * shortcut: a reload while "show replies" is fetching is dropped, reopen to catch up.
 */
// Echo subscribes at setup; the SSR server must not open a socket.
if (!import.meta.env.SSR) {
    useEchoPublic<CommentThreadChanged>(
        `comments.${commentableType}.${commentableId}`,
        '.CommentThreadChanged',
        (change) => {
            if (change.parent_id === comment.id && !loading.value) {
                void requestReplies(1, true);
            }
        },
    );
}
</script>

<template>
    <li class="space-y-3">
        <CommentBody
            :comment="comment"
            :max-length="maxLength"
            :can-interact="canInteract"
            can-reply
            :report-reasons="reportReasons"
            @reply="replying = !replying"
            @deleted="emit('deleted', $event)"
        />

        <div
            v-if="replies.length > 0 || hasMore || replying"
            class="border-border ms-12 space-y-3 border-s-2 ps-4"
        >
            <CommentBody
                v-for="reply in replies"
                :key="reply.id"
                :comment="reply"
                :max-length="maxLength"
                :can-interact="canInteract"
                :report-reasons="reportReasons"
                @deleted="emit('deleted', $event)"
            />

            <Button
                v-if="hasMore"
                variant="ghost"
                size="sm"
                :disabled="loading"
                @click="loadMore"
            >
                <Spinner v-if="loading" />
                {{
                    t('comments.show_replies', {
                        count: advertisedReplies,
                    })
                }}
            </Button>

            <CommentComposerGate v-if="replying">
                <CommentComposer
                    :commentable-type="commentableType"
                    :commentable-id="commentableId"
                    :max-length="maxLength"
                    :parent-id="comment.id"
                    :placeholder="t('comments.write_reply_placeholder')"
                    autofocus
                    @posted="
                        replying = false;
                        emit('posted');
                    "
                />
            </CommentComposerGate>
        </div>
    </li>
</template>
