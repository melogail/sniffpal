<?php

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * The member's support desk over the API, `api/v1/me/tickets`: the same
 * boundaries the web pages pin (tests/Feature/Settings/TicketControllerTest),
 * plus the two things only the API does — the thread rides on the ticket
 * body, and a reply answers with the message it created.
 */
describe('index', function () {
    test('requires a token', function () {
        $this->getJson(route('api.v1.tickets.index'))->assertUnauthorized();
    });

    test('lists the caller own tickets as headers, most recently active first', function () {
        $member   = User::factory()->create(['email' => 'omar@example.com']);
        $older    = Ticket::factory()->for($member)->create(['last_activity_at' => now()->subDay()]);
        $answered = Ticket::factory()->for($member)->answered()->create();
        Ticket::factory()->create();
        Ticket::factory()->fromDeletedAccount()->create(['email' => 'omar@example.com']);
        Sanctum::actingAs($member);

        $this->getJson(route('api.v1.tickets.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $answered->getKey())
            ->assertJsonPath('data.0.status', TicketStatus::Answered->value)
            ->assertJsonPath('data.0.messages_count', 2)
            ->assertJsonPath('data.1.id', $older->getKey())
            ->assertJsonMissingPath('data.0.messages')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonPath('meta.current_page', 1);
    });

    test('an unverified account may still read and open tickets', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson(route('api.v1.tickets.index'))->assertOk();

        $this->postJson(route('api.v1.tickets.store'), [
            'category' => TicketCategory::AccountIssue->value,
            'subject'  => 'No verification mail',
            'message'  => 'It never arrived.',
        ])->assertCreated();
    });
});

describe('store', function () {
    test('opens a ticket for the caller and answers with the thread', function () {
        $member = User::factory()->create(['name' => 'Omar Said', 'email' => 'omar@example.com']);
        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.tickets.store'), [
            'category' => TicketCategory::MissingBreed->value,
            'subject'  => 'Egyptian Mau',
            'message'  => 'The breed is missing from the cat list.',
        ])
            ->assertCreated()
            ->assertJsonPath('category', 'missing_breed')
            ->assertJsonPath('subject', 'Egyptian Mau')
            ->assertJsonPath('status', TicketStatus::Open->value)
            ->assertJsonPath('messages_count', 1)
            ->assertJsonPath('can_reply', true)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'The breed is missing from the cat list.')
            ->assertJsonPath('messages.0.is_from_support', false)
            ->assertJsonPath('messages.0.author_name', 'Omar Said');

        $this->assertDatabaseHas('tickets', [
            'user_id' => $member->getKey(),
            'name'    => 'Omar Said',
            'email'   => 'omar@example.com',
            'subject' => 'Egyptian Mau',
        ]);
    });

    test('validates the category, subject and bounded message', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.tickets.store'), [
            'category' => 'billing',
            'subject'  => '',
            'message'  => str_repeat('x', 2001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'subject', 'message']);
    });
});

describe('show', function () {
    test('returns the ticket with its thread, each side named', function () {
        $member = User::factory()->create(['name' => 'Omar Said']);
        $ticket = Ticket::factory()->for($member)->answered('We added the breed.')->create();
        Sanctum::actingAs($member);

        $this->getJson(route('api.v1.tickets.show', $ticket))
            ->assertOk()
            ->assertJsonPath('id', $ticket->getKey())
            ->assertJsonPath('status', TicketStatus::Answered->value)
            ->assertJsonPath('messages_count', 2)
            ->assertJsonPath('can_reply', true)
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.is_from_support', false)
            ->assertJsonPath('messages.0.author_name', 'Omar Said')
            ->assertJsonPath('messages.1.is_from_support', true)
            ->assertJsonPath('messages.1.body', 'We added the breed.');
    });

    test('another member ticket and an orphaned ticket are forbidden', function () {
        $member = User::factory()->create(['email' => 'omar@example.com']);
        $other  = Ticket::factory()->create();
        $orphan = Ticket::factory()->fromDeletedAccount()->create(['email' => 'omar@example.com']);
        Sanctum::actingAs($member);

        $this->getJson(route('api.v1.tickets.show', $other))->assertForbidden();
        $this->getJson(route('api.v1.tickets.show', $orphan))->assertForbidden();
    });
});

describe('reply', function () {
    test('appends the member message, reopens the ticket and notifies nobody', function () {
        Notification::fake();
        $member = User::factory()->create(['name' => 'Omar Said']);
        $ticket = Ticket::factory()->for($member)->answered()->create();
        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.tickets.reply', $ticket), ['body' => 'Still not there.'])
            ->assertCreated()
            ->assertJsonPath('body', 'Still not there.')
            ->assertJsonPath('is_from_support', false)
            ->assertJsonPath('author_name', 'Omar Said');

        expect($ticket->fresh()->status)->toBe(TicketStatus::Open)
            ->and($ticket->messages()->count())->toBe(3);
        Notification::assertNothingSent();
    });

    test('a closed ticket takes no reply', function () {
        $member = User::factory()->create();
        $ticket = Ticket::factory()->for($member)->closed()->create();
        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.tickets.reply', $ticket), ['body' => 'One more thing.'])
            ->assertForbidden();
    });

    test('another member may not write to the ticket', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.tickets.reply', Ticket::factory()->create()), ['body' => 'Hi'])
            ->assertForbidden();
    });
});

describe('close', function () {
    test('closes the ticket and answers with the closed thread', function () {
        Notification::fake();
        $member = User::factory()->create();
        $ticket = Ticket::factory()->for($member)->answered()->create();
        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.tickets.close', $ticket))
            ->assertOk()
            ->assertJsonPath('status', TicketStatus::Closed->value)
            ->assertJsonPath('can_reply', false)
            ->assertJsonCount(2, 'messages');

        expect($ticket->fresh()->closed_at)->not->toBeNull();
        Notification::assertNotSentTo($member, TicketRepliedNotification::class);
    });

    test('a closed ticket cannot be closed again', function () {
        $member = User::factory()->create();
        $ticket = Ticket::factory()->for($member)->closed()->create();
        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.tickets.close', $ticket))->assertForbidden();
    });
});
