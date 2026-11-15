<template>
  <div>
    <!-- Nova's detail toolbar: the heading and the actions dropdown. -->
    <slot />

    <LoadingCard v-if="!ticket" :loading="loading" class="mt-3 min-h-[12rem]" />

    <div
      v-else
      class="mt-3 grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start"
    >
      <!-- Sidebar: the ticket's header. -->
      <Card class="divide-y divide-gray-100 dark:divide-gray-700">
        <div class="px-6 py-4">
          <p class="text-xxs uppercase tracking-wide font-bold text-gray-500">
            Ticket #{{ ticket.id }}
          </p>
          <h2
            class="mt-1 font-semibold text-gray-900 dark:text-gray-100 break-words"
          >
            {{ ticket.subject }}
          </h2>
          <Badge
            class="mt-3"
            :label="statusLabel"
            :extra-classes="statusClasses[ticket.status]"
          />
        </div>

        <dl class="px-6 divide-y divide-gray-100 dark:divide-gray-700">
          <div class="py-3">
            <dt class="text-xxs uppercase tracking-wide font-bold text-gray-500">
              Member
            </dt>
            <dd class="mt-1 text-sm">
              <Link
                v-if="ticket.member"
                :href="$url(`/resources/users/${ticket.member.id}`)"
                class="link-default"
              >
                {{ ticket.member.name }}
              </Link>
              <span v-else class="text-gray-500 italic">Account deleted</span>
            </dd>
          </div>

          <div class="py-3">
            <dt class="text-xxs uppercase tracking-wide font-bold text-gray-500">
              Sender
            </dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 break-words">
              {{ ticket.name }}
            </dd>
            <dd class="text-sm break-words">
              <a :href="`mailto:${ticket.email}`" class="link-default">
                {{ ticket.email }}
              </a>
            </dd>
          </div>

          <div v-for="row in rows" :key="row.label" class="py-3">
            <dt class="text-xxs uppercase tracking-wide font-bold text-gray-500">
              {{ row.label }}
            </dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
              {{ row.value }}
            </dd>
          </div>
        </dl>
      </Card>

      <!-- Main: the thread, the status above it, the reply below it. -->
      <Card class="flex flex-col overflow-hidden">
        <div
          class="flex flex-wrap items-center justify-between gap-4 px-6 py-3 border-b border-gray-100 dark:border-gray-700"
        >
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
              Conversation
            </h3>
            <p class="text-xs text-gray-500">
              {{ messages.length }} {{ messages.length === 1 ? 'message' : 'messages' }}
              <template v-if="ticket.lastActivity">
                · last activity {{ ticket.lastActivity }}
              </template>
            </p>
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-500">
            Status
            <SelectControl
              v-model="selectedStatus"
              :options="statuses"
              :disabled="changingStatus"
              size="sm"
              class="w-40"
              @selected="changeStatus"
            />
          </label>
        </div>

        <div
          ref="thread"
          class="flex flex-col gap-5 px-6 py-6 overflow-y-auto max-h-[60vh] bg-gray-50 dark:bg-gray-900"
        >
          <p
            v-if="messages.length === 0"
            class="py-12 text-sm text-center text-gray-500"
          >
            Nothing has been written yet.
          </p>

          <div
            v-for="message in messages"
            :key="message.id"
            class="flex flex-col max-w-[80%]"
            :class="message.fromSupport ? 'self-end items-end' : 'self-start items-start'"
          >
            <div class="flex items-baseline gap-2 mb-1 text-xs text-gray-500">
              <span class="font-semibold text-gray-700 dark:text-gray-300">
                {{ message.author }}
              </span>
              <span :title="message.sentAt">{{ message.sentAtHuman }}</span>
            </div>
            <div
              class="px-4 py-2 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap break-words shadow"
              :class="
                message.fromSupport
                  ? 'bg-primary-500 text-white rounded-br-sm'
                  : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-bl-sm'
              "
            >{{ message.body }}</div>
          </div>
        </div>

        <form
          class="px-6 py-4 border-t border-gray-100 dark:border-gray-700"
          @submit.prevent="sendReply"
        >
          <template v-if="ticket.status !== 'closed'">
            <textarea
              v-model="reply"
              rows="3"
              :maxlength="maxMessageLength"
              :disabled="sending"
              placeholder="Write a reply… the member sees it on their ticket page and by email."
              class="w-full h-auto py-3 form-control form-input form-control-bordered resize-none"
            />
            <div class="flex items-center justify-between gap-4 mt-3">
              <span class="text-xs text-gray-500">
                Sending marks the ticket Answered.
              </span>
              <Button type="submit" :loading="sending" :disabled="reply.trim() === ''">
                Send reply
              </Button>
            </div>
          </template>
          <p v-else class="text-sm text-center text-gray-500">
            This ticket is closed. Set it back to Open or Answered to reply.
          </p>
        </form>
      </Card>
    </div>
  </div>
</template>

<script>
import { Button } from 'laravel-nova-ui'

// Same classes Nova's Badge field uses for these types (Laravel\Nova\Badge::$types).
const POLL_MS = 10000

const STATUS_CLASSES = {
  open: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-300 dark:text-yellow-800',
  answered: 'bg-green-100 text-green-600 dark:bg-green-500 dark:text-green-900',
  closed: 'bg-sky-100 text-sky-600 dark:bg-sky-600 dark:text-sky-900',
}

export default {
  components: { Button },

  props: ['resourceName', 'resourceId', 'panel'],

  data: () => ({
    poll: null,
    loading: true,
    ticket: null,
    statuses: [],
    messages: [],
    maxMessageLength: 2000,
    selectedStatus: null,
    changingStatus: false,
    reply: '',
    sending: false,
    statusClasses: STATUS_CLASSES,
  }),

  computed: {
    statusLabel() {
      return this.statuses.find(s => s.value === this.ticket.status)?.label
    },

    rows() {
      return [
        { label: 'Category', value: this.ticket.category },
        { label: 'Opened', value: this.ticket.opened },
        { label: 'Closed', value: this.ticket.closed },
      ].filter(row => row.value)
    },
  },

  mounted() {
    this.load()
    // Nova has no Echo client, so the panel polls; replies and closes the
    // member makes from their ticket page appear within this interval.
    this.poll = setInterval(() => {
      if (!this.sending && !this.changingStatus) this.load(false)
    }, POLL_MS)
  },

  beforeUnmount() {
    clearInterval(this.poll)
  },

  methods: {
    /**
     * Read the thread. A poll (`announce` false) is silent on failure and only
     * scrolls to the end when a message was added, so an admin reading up the
     * thread is not yanked back down every ten seconds.
     */
    async load(announce = true) {
      this.loading = true
      const seen = this.messages.length

      try {
        const { data } = await Nova.request().get(
          `/nova-vendor/ticket-thread/tickets/${this.resourceId}`
        )

        this.ticket = data.ticket
        this.statuses = data.statuses
        this.messages = data.messages
        this.maxMessageLength = data.maxMessageLength
        this.selectedStatus = data.ticket.status

        if (announce || data.messages.length !== seen) {
          this.$nextTick(() => {
            const thread = this.$refs.thread
            if (thread) thread.scrollTop = thread.scrollHeight
          })
        }
      } catch (error) {
        if (announce) Nova.error('The ticket could not be loaded.')
      } finally {
        this.loading = false
      }
    },

    /**
     * Run one of the Ticket resource's own Nova actions against this ticket,
     * then re-read the thread. Returns whether it succeeded.
     */
    async runAction(action, fields) {
      try {
        const { data } = await Nova.request().post(
          `/nova-api/${this.resourceName}/action?action=${action}`,
          { resources: [this.resourceId], ...fields }
        )

        if (data.danger) {
          Nova.error(data.danger)
          return false
        }

        Nova.success(data.message)
        await this.load()
        return true
      } catch (error) {
        const errors = error.response?.data?.errors
        Nova.error(
          errors ? Object.values(errors).flat()[0] : 'Something went wrong.'
        )
        return false
      }
    },

    async changeStatus(option) {
      if (!option || option.value === this.ticket.status) return

      this.changingStatus = true
      const changed = await this.runAction('change-status', { status: option.value })
      if (!changed) this.selectedStatus = this.ticket.status
      this.changingStatus = false
    },

    async sendReply() {
      if (this.reply.trim() === '') return

      this.sending = true
      const sent = await this.runAction('reply-to-ticket', { reply: this.reply.trim() })
      if (sent) this.reply = ''
      this.sending = false
    },
  },
}
</script>
