<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contact\SubmitContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * The guest channel, for the mobile app — Web\ContactController with a JSON
 * answer. Public, on the same Form Request, Action and `contact-messages`
 * limiter; a signed-in member is steered to Api\V1\TicketController by the
 * app, as the web steers them to the ticket dialog.
 *
 * No `$this->authorize()`: nothing here loads or exposes a model somebody
 * else owns, the one exemption .ai/rules/controllers.md allows.
 */
class ContactController extends Controller
{
    public function store(StoreContactRequest $request, SubmitContactMessage $submitContactMessage): JsonResponse
    {
        $submitContactMessage->handle(
            name: $request->senderName(),
            email: $request->senderEmail(),
            subject: $request->subject(),
            message: $request->message(),
        );

        return response()->json([
            'message' => __('Your message has been sent. We will get back to you by email shortly.'),
        ], 201);
    }
}
