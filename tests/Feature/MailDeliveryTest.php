<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportException;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Support\FormContext;
use Tok3\SectionEngineNewsletter\Tests\Fixtures\FailingMailTransport;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class MailDeliveryTest extends TestCase
{
    private function payload(): array
    {
        $list = NewsletterList::factory()->create(['key' => 'news']);
        $context = app(FormContext::class);
        $data = $context->create($list, LiveTokenRenderContext::frontend('en', url: 'http://localhost/example'), 'topic');
        $this->travel(4)->seconds();

        return ['email' => 'new@example.org', 'context' => $context->encrypt($data), 'consent' => '1', 'website' => ''];
    }

    private function handle(array $payload, bool $json = true): array
    {
        $request = Request::create('http://localhost/newsletter/subscribe', 'POST', $payload, server: [
            'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html',
            'HTTP_USER_AGENT' => 'Deferred confirmation browser',
            'REMOTE_ADDR' => '2001:db8::12',
        ]);
        $kernel = app(Kernel::class);

        return [$kernel, $request, $kernel->handle($request)];
    }

    public function test_without_queue_delivery_and_proof_wait_until_after_the_response(): void
    {
        $transport = Mail::mailer()->getSymfonyTransport();
        [$kernel, $request, $response] = $this->handle($this->payload());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $transport->messages(), 'Handling the HTTP request must never contact the mail transport');
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'requested']);
        $this->assertDatabaseMissing('section_engine_newsletter_events', ['type' => 'confirmation_sent']);

        $kernel->terminate($request, $response);

        $this->assertCount(1, $transport->messages());
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'confirmation_sent', 'ip' => '2001:db8::12', 'user_agent' => 'Deferred confirmation browser']);
    }

    public static function failureModes(): array
    {
        return ['no queue JSON' => [null, true], 'no queue redirect' => [null, false], 'configured queue JSON' => ['sync', true], 'configured queue redirect' => ['sync', false]];
    }

    #[DataProvider('failureModes')]
    public function test_transport_failures_never_reveal_subscription_state_in_the_response(?string $connection, bool $json): void
    {
        Exceptions::fake();
        $transport = new FailingMailTransport;
        Mail::extend('failure', fn () => $transport);
        config(['mail.default' => 'failure', 'mail.mailers.failure' => ['transport' => 'failure'], 'queue.connections.sync' => ['driver' => 'sync'], 'section-engine-newsletter.queue' => $connection,
            'section-engine-newsletter.rate_limits.per_ip' => 100, 'section-engine-newsletter.rate_limits.per_email' => 100]);
        $payload = $this->payload();
        $list = NewsletterList::firstOrFail();
        $confirmed = Subscription::factory()->create(['list_id' => $list->id, 'status' => 'confirmed']);
        $pending = Subscription::factory()->create(['list_id' => $list->id]);
        $resend = Subscription::factory()->create(['list_id' => $list->id, 'requested_at' => now()->subMinutes(11)]);
        $responseSignature = null;

        foreach ([$confirmed->subscriber->email, 'new@example.org', $pending->subscriber->email, $resend->subscriber->email] as $email) {
            [$kernel, $request, $response] = $this->handle(array_replace($payload, ['email' => $email]), $json);
            $signature = $this->signature($response);
            $this->assertSame($json ? 200 : 302, $response->getStatusCode());
            $responseSignature ??= $signature;
            $this->assertSame($responseSignature, $signature);
            $kernel->terminate($request, $response);
            $this->assertSame($signature, $this->signature($response));
        }

        config(['section-engine-newsletter.rate_limits.per_ip' => 1]);
        [$kernel, $request, $response] = $this->handle(array_replace($payload, ['email' => 'limited@example.org']), $json);
        $this->assertSame($responseSignature, $this->signature($response));
        $kernel->terminate($request, $response);
        $this->assertSame($responseSignature, $this->signature($response));
        $this->assertDatabaseMissing('section_engine_newsletter_subscribers', ['email' => 'limited@example.org']);
        $new = Subscriber::where('email', 'new@example.org')->firstOrFail()->subscriptions()->firstOrFail();
        $this->assertSame('pending', $new->status->value);
        $this->assertSame(1, $new->events()->where('type', 'requested')->count());
        $this->assertDatabaseHas('section_engine_newsletter_events', ['subscription_id' => $new->id, 'type' => 'requested', 'ip' => '2001:db8::12', 'user_agent' => 'Deferred confirmation browser']);
        $this->assertDatabaseMissing('section_engine_newsletter_events', ['type' => 'confirmation_sent']);
        $this->assertSame(2, $transport->attempts);
        Exceptions::assertReportedCount(2);
        Exceptions::assertReported(fn (TransportException $exception): bool => $exception->getMessage() === 'Confirmation transport unavailable');
    }

    public function test_queue_backend_failure_is_reported_without_changing_the_response_or_request_proof(): void
    {
        Exceptions::fake();
        app('queue')->addConnector('unavailable', fn () => throw new RuntimeException('Confirmation queue unavailable'));
        config(['queue.connections.unavailable' => ['driver' => 'unavailable'], 'section-engine-newsletter.queue' => 'unavailable']);
        [$kernel, $request, $response] = $this->handle($this->payload());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', json_decode($response->getContent(), true)['status']);
        $kernel->terminate($request, $response);
        $this->assertSame('pending', Subscription::firstOrFail()->status->value);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'requested']);
        $this->assertDatabaseMissing('section_engine_newsletter_events', ['type' => 'confirmation_sent']);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Confirmation queue unavailable');
    }

    private function signature(Response $response): array
    {
        $headers = $response->headers->all();
        unset($headers['set-cookie']);
        // Date is request-specific HTTP metadata. Validate it separately instead of comparing wall-clock seconds.
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $headers['date'][0]);
        $headers['date'] = ['<valid HTTP date>'];

        return [$response->getStatusCode(), $response->getContent(), $headers];
    }
}
