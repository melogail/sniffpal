<?php

namespace Sniffpal\TicketThread;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Laravel\Nova\ResourceTool;

/**
 * The support ticket detail page as a chat: the ticket's header in a
 * sidebar, the thread in the main column, the status in a dropdown above it.
 *
 * Rendered by resources/js/components/Tool.vue, which reads
 * `GET nova-vendor/ticket-thread/tickets/{ticket}` (routes/api.php) and
 * writes through the resource's existing Nova actions, so the transitions,
 * transactions and notifications stay in App\Nova\Actions.
 */
class TicketThread extends ResourceTool
{
    /**
     * Get the displayable name of the resource tool.
     */
    public function name(): string
    {
        return 'Conversation';
    }

    /**
     * Get the component name for the resource tool.
     */
    public function component(): string
    {
        return 'ticket-thread';
    }

    /**
     * What the chat view renders, formatted here so the component stays dumb.
     *
     * @return array{
     *     ticket: array<string, mixed>,
     *     statuses: array<int, array{value: string, label: string}>,
     *     messages: array<int, array<string, mixed>>,
     *     maxMessageLength: int
     * }
     */
    public static function payload(Ticket $ticket): array
    {
        $ticket->load(['user', 'messages.author']);

        return [
            'ticket'           => [
                'id'           => $ticket->getKey(),
                'subject'      => $ticket->subject,
                'status'       => $ticket->status->value,
                'category'     => $ticket->category->label(),
                'name'         => $ticket->name,
                'email'        => $ticket->email,
                'member'       => $ticket->user === null ? null : [
                    'id'   => $ticket->user->getKey(),
                    'name' => $ticket->user->name,
                ],
                'opened'       => $ticket->created_at?->toDayDateTimeString(),
                'lastActivity' => $ticket->last_activity_at?->diffForHumans(),
                'closed'       => $ticket->closed_at?->toDayDateTimeString(),
            ],
            'statuses'         => TicketStatus::options(),
            'messages'         => $ticket->messages->map(fn (TicketMessage $message): array => [
                'id'          => $message->getKey(),
                'body'        => $message->body,
                'author'      => $message->author->name ?? ($message->isFromSupport() ? 'Support' : $ticket->name),
                'fromSupport' => $message->isFromSupport(),
                'sentAt'      => $message->created_at?->toDayDateTimeString(),
                'sentAtHuman' => $message->created_at?->diffForHumans(),
            ])->all(),
            'maxMessageLength' => (int) config('petconnect.support.max_message_length', 2000),
        ];
    }
}
