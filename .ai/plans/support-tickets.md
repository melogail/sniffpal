# Support tickets

## Purpose
Authenticated users open a ticket, admins answer from Nova, users reply or close from settings; replies email the user.

## Data
`tickets` (user_id nullable, name, email, category, subject, status, last_activity_at, closed_at), `ticket_messages` (ticket_id, author_type/author_id morph to User or Admin, body). Enums TicketCategory, TicketStatus.

## Backend
- Actions (`app/Actions/Tickets/`): OpenTicket, ReplyToTicket, CloseTicket, ListUserTickets.
- `TicketPolicy`, `TicketValidationRules`, requests under `Tickets/`, resources under `Ticket/`.
- `TicketRepliedNotification` (database + broadcast + mail for members, mail only for guests) when support answers or closes; the broadcast is what makes the bell and the member's ticket page live. `TicketMessage::isFromSupport()` drives the bubble style.
- Controller `Settings/TicketController` (index, store, show, reply, close); API twin `Api/V1/TicketController` on the same Actions, answering `TicketThreadResource` (header + `messages` + `can_reply`) for store/show/close and the created `TicketMessageResource` for a reply.

## Routes
`GET|POST settings/tickets`, `GET settings/tickets/{ticket}`, `POST settings/tickets/{ticket}/reply`, `POST settings/tickets/{ticket}/close`. Auth (not verified). API: `GET|POST api/v1/me/tickets`, `GET api/v1/me/tickets/{ticket}`, `POST .../reply`, `POST .../close`, same guard and limiters.

## Frontend
`pages/settings/{Tickets,Ticket}.vue`, status badge map in `components/support/TICKET_STATUS_VARIANT`. `Ticket.vue` listens for `TicketRepliedNotification` on the member's private channel and partially reloads `ticket`, `messages`, `canReply`. Mobile: `lib/features/support/` (tickets list, new ticket, ticket thread with reply and close) reached from Settings and Help.

## Guards
Throttle `ticket-messages` (5/min). Users only see their own tickets; closed tickets reject replies.

## Admin
Nova `Ticket` and `TicketMessage` resources; the `ticket-thread` chat panel polls its payload every 10s (Nova has no Echo client); actions `ReplyToTicket`, `ChangeTicketStatus`; `TicketPolicy` / `TicketMessagePolicy` under `app/Nova/Policies`.

## Tests
`tests/Feature/Settings/`, `tests/Feature/Nova/Actions/`, `tests/Feature/Http/Controllers/Api/V1/TicketControllerTest.php`.

## Open work
- No SLA or auto-close of stale answered tickets.
