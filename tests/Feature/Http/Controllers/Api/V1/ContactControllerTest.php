<?php

/**
 * The guest channel over the API, `POST api/v1/contact`: a one-off message
 * with a name and a reply address, public, stored for the back office.
 */
test('a guest sends a message with their name and address', function () {
    $this->postJson(route('api.v1.contact.store'), [
        'name'    => 'Mona Khalil',
        'email'   => 'mona@example.com',
        'subject' => 'Egyptian Mau is not in the cat breeds',
        'message' => 'I could not find the breed when listing my cat.',
    ])
        ->assertCreated()
        ->assertJsonStructure(['message']);

    $this->assertDatabaseHas('contact_messages', [
        'name'    => 'Mona Khalil',
        'email'   => 'mona@example.com',
        'subject' => 'Egyptian Mau is not in the cat breeds',
        'message' => 'I could not find the breed when listing my cat.',
    ]);
});

test('a name, a valid address, a subject and a bounded message are all required', function () {
    $this->postJson(route('api.v1.contact.store'), [
        'email'   => 'not-an-address',
        'subject' => '',
        'message' => str_repeat('x', 2001),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

    $this->assertDatabaseCount('contact_messages', 0);
});
