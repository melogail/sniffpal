<?php

namespace App\Http\Resources\Ticket;

use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * One ticket with its thread, for the mobile app's ticket screen.
 *
 * The web ships the header and the thread as two Inertia props
 * (Settings\TicketController::show). A native client wants the page in one
 * fetch, so this widens TicketResource with the thread and the one
 * viewer-relative flag the composer needs — the same arrangement as
 * Pet\PetOwnerResource over UserSummaryResource. Served by
 * Api\V1\TicketController only; the list endpoint keeps the plain header so
 * a page of tickets never carries a thread.
 *
 * `messages` is `whenLoaded`, so the controller eager loads
 * `messages.author` (one query per author type) before handing the ticket
 * over; a missing key here is a controller bug, not an optional field.
 *
 * @mixin Ticket
 */
class TicketThreadResource extends TicketResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'messages'  => TicketMessageResource::collection($this->whenLoaded('messages')),
            'can_reply' => (bool) $request->user()?->can('reply', $this->resource),
        ];
    }
}
