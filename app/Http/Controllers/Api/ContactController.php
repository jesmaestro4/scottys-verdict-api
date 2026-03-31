<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactSubmissionMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(ContactRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! $this->verifyRecaptcha((string) $validated['recaptcha_token'])) {
            return response()->json([
                'message' => 'Failed bot verification.',
            ], 422);
        }

        $recipientEmail = (string) config('services.contact.to_email');
        $recipientName = (string) config('services.contact.to_name');

        if ($recipientEmail === '') {
            return response()->json([
                'message' => 'Contact recipient is not configured.',
            ], 500);
        }

        Mail::to($recipientEmail, $recipientName)->send(
            (new ContactSubmissionMail(
                name: (string) $validated['name'],
                email: (string) $validated['email'],
                messageText: (string) $validated['message'],
            ))->replyTo((string) $validated['email'], (string) $validated['name'])
        );

        return response()->json([
            'message' => 'Message sent.',
        ], 202);
    }

    private function verifyRecaptcha(string $token): bool
    {
        $enabled = (bool) config('services.recaptcha.enabled', true);

        if (! $enabled) {
            return true;
        }

        $secret = (string) config('services.recaptcha.secret', '');

        if ($secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
            ]);

            if (! $response->successful()) {
                return false;
            }

            $payload = $response->json();

            if (! is_array($payload) || ! ($payload['success'] ?? false)) {
                return false;
            }

            $expectedAction = (string) config('services.recaptcha.expected_action', 'contact');
            if ($expectedAction !== '' && ($payload['action'] ?? null) !== $expectedAction) {
                return false;
            }

            $score = isset($payload['score']) && is_numeric($payload['score']) ? (float) $payload['score'] : 0.0;
            $minScore = (float) config('services.recaptcha.min_score', 0.5);

            return $score >= $minScore;
        } catch (\Throwable) {
            return false;
        }
    }
}
