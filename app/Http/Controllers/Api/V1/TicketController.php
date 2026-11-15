<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CloseTicket;
use App\Actions\Tickets\ListUserTickets;
use App\Actions\Tickets\OpenTicket;
use App\Actions\Tickets\ReplyToTicket;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ReplyToTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Resources\Ticket\TicketMessageResource;
use App\Http\Resources\Ticket\TicketResource;
use App\Http\Resources\Ticket\TicketThreadResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The member's support desk, for the mobile app — Settings\TicketController
 * with JSON answers, on the same Actions, Form Requests and TicketPolicy.
 *
 * Two differences from the web twin, both about the transport. `store`,
 * `show` and `close` answer with TicketThreadResource — the header and the
 * thread in one body — because the app draws the ticket screen from one
 * fetch where the web page gets two props. And `reply` answers with the
 * message it created, so the app can append it to the thread it already has
 * rather than reloading the ticket.
 *
 * `index` lists the caller's own rows and `store` opens a ticket for the
 * caller, so neither asks a policy (there is no second party); the three
 * routes that act on an existing ticket authorize against TicketPolicy.
 * Members-only, like the web: a guest's message is Api\V1\ContactController.
 */
class TicketController extends Controller
{
    use ResolvesRequestUser;

    public function index(Request $request, ListUserTickets $listUserTickets): AnonymousResourceCollection
    {
        return TicketResource::collection($listUserTickets->handle($this->actor($request)));
    }

    public function store(StoreTicketRequest $request, OpenTicket $openTicket): JsonResponse
    {
        $ticket = $openTicket->handle(
            sender: $this->actor($request),
            category: $request->category(),
            subject: $request->subject(),
            message: $request->message(),
        );

        return $this->thread($ticket)->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketThreadResource
    {
        $this->authorize('view', $ticket);

        return $this->thread($ticket);
    }

    public function reply(ReplyToTicketRequest $request, Ticket $ticket, ReplyToTicket $replyToTicket): JsonResponse
    {
        $this->authorize('reply', $ticket);

        $author  = $this->actor($request);
        $message = $replyToTicket->handle($ticket, $author, $request->body());

        // The Action creates the row through the relation, so the author is
        // not loaded; set it rather than let the resource lazy load it.
        $message->setRelation('author', $author);

        return TicketMessageResource::make($message)->response()->setStatusCode(201);
    }

    public function close(Request $request, Ticket $ticket, CloseTicket $closeTicket): TicketThreadResource
    {
        $this->authorize('close', $ticket);

        $closeTicket->handle($ticket, $this->actor($request));

        return $this->thread($ticket);
    }

    /**
     * The ticket with its thread loaded, as the app's ticket screen reads it.
     */
    private function thread(Ticket $ticket): TicketThreadResource
    {
        $ticket->loadMissing('messages.author')->loadCount('messages');

        return TicketThreadResource::make($ticket);
    }
}
