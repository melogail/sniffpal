<?php

use App\Models\Admin;
use App\Models\Ticket;
use App\Models\User;

/**
 * The chat view on the ticket detail page (nova-components/ticket-thread)
 * reads one endpoint and writes through the resource's own actions, which
 * have their own tests. What is pinned here: the payload the component
 * renders, and that the endpoint sits behind Nova's admin authentication
 * rather than the unauthenticated `nova` middleware group.
 */
test('the thread endpoint returns the ticket header, the statuses and the messages in order', function () {
    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->answered('We fixed it.')->create(['subject' => 'Missing breed']);

    $this->actingAs($admin, 'admin')
        ->getJson("/nova-vendor/ticket-thread/tickets/{$ticket->getKey()}")
        ->assertOk()
        ->assertJsonPath('ticket.id', $ticket->getKey())
        ->assertJsonPath('ticket.subject', 'Missing breed')
        ->assertJsonPath('ticket.status', 'answered')
        ->assertJsonPath('ticket.member.name', $ticket->user->name)
        ->assertJsonPath('ticket.email', $ticket->email)
        ->assertJsonPath('statuses.*.value', ['open', 'answered', 'closed'])
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.fromSupport', false)
        ->assertJsonPath('messages.1.fromSupport', true)
        ->assertJsonPath('messages.1.body', 'We fixed it.')
        ->assertJsonPath('maxMessageLength', (int) config('petconnect.support.max_message_length'));
});

test('a ticket from a deleted account has no member and still names its sender', function () {
    $admin  = Admin::factory()->create();
    $ticket = Ticket::factory()->fromDeletedAccount()->create();

    $this->actingAs($admin, 'admin')
        ->getJson("/nova-vendor/ticket-thread/tickets/{$ticket->getKey()}")
        ->assertOk()
        ->assertJsonPath('ticket.member', null)
        ->assertJsonPath('messages.0.author', $ticket->name);
});

test('the thread endpoint is closed to guests and to members', function () {
    $ticket = Ticket::factory()->create();

    $this->getJson("/nova-vendor/ticket-thread/tickets/{$ticket->getKey()}")
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson("/nova-vendor/ticket-thread/tickets/{$ticket->getKey()}")
        ->assertUnauthorized();
});
