<?php

namespace Tests\Feature;

use App\Mail\ContactSubmissionMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactEndpointTest extends TestCase
{
    public function test_contact_submission_sends_email_when_payload_is_valid(): void
    {
        config()->set('services.contact.to_email', 'owner@example.com');
        config()->set('services.contact.to_name', 'Owner');
        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.secret', 'recaptcha-secret');
        config()->set('services.recaptcha.expected_action', 'contact');
        config()->set('services.recaptcha.min_score', 0.5);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'contact',
            ], 200),
        ]);

        Mail::fake();

        $payload = [
            'name' => 'Jesse Thompson',
            'email' => 'jesse@example.com',
            'message' => 'Need help with a listing review.',
            'recaptcha_token' => 'recaptcha-token',
        ];

        $this->postJson('/api/contact', $payload)
            ->assertStatus(202)
            ->assertJsonPath('message', 'Message sent.');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'recaptcha-secret'
                && $request['response'] === 'recaptcha-token';
        });

        Mail::assertSent(ContactSubmissionMail::class);
        Mail::assertSent(ContactSubmissionMail::class, function (ContactSubmissionMail $mail): bool {
            return $mail->name === 'Jesse Thompson'
                && $mail->email === 'jesse@example.com'
                && $mail->messageText === 'Need help with a listing review.';
        });
    }

    public function test_contact_submission_requires_valid_payload(): void
    {
        config()->set('services.recaptcha.enabled', false);

        $this->postJson('/api/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
            'recaptcha_token' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'message', 'recaptcha_token']);
    }

    public function test_contact_submission_fails_when_recaptcha_verification_fails(): void
    {
        config()->set('services.contact.to_email', 'owner@example.com');
        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.secret', 'recaptcha-secret');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'score' => 0.0,
                'action' => 'contact',
            ], 200),
        ]);

        Mail::fake();

        $this->postJson('/api/contact', [
            'name' => 'Jesse Thompson',
            'email' => 'jesse@example.com',
            'message' => 'Please contact me.',
            'recaptcha_token' => 'bad-token',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Failed bot verification.');

        Mail::assertNothingSent();
    }
}
