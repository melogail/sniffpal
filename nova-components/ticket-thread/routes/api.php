<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Route;
use Sniffpal\TicketThread\TicketThread;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| What the chat view reads. Writes go through the Ticket resource's own Nova
| actions (reply-to-ticket, change-status), so there is nothing to post here.
|
*/

Route::get('/tickets/{ticket}', fn (Ticket $ticket): array => TicketThread::payload($ticket));
