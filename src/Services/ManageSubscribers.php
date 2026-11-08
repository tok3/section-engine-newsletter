<?php

namespace Tok3\SectionEngineNewsletter\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngine\Support\SectionEngineAccess;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class ManageSubscribers
{
    public function authorize(): void
    {
        abort_unless(app(SectionEngineAccess::class)->allows(), 403);
    }

    /** @param array<string, mixed> $data */
    public function add(array $data): Subscriber
    {
        $this->authorize();
        $data['email'] = is_string($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $data = Validator::make($data, ['email' => ['required', 'string', 'max:254', config('section-engine-newsletter.validate_dns') ? 'email:rfc,dns' : 'email:rfc'], 'list_id' => ['required', 'integer'], 'locale' => ['required', 'string', 'max:32'], 'tag' => ['nullable', 'string']])->validate();
        $list = NewsletterList::query()->where('active', true)->findOrFail($data['list_id']);

        return DB::transaction(function () use ($data, $list): Subscriber {
            $context = app(FormContext::class)->create($list, LiveTokenRenderContext::frontend($data['locale'], url: request()->url()), $data['tag'] ?? null);
            $subscription = app(RequestSubscription::class)->handle($data['email'], $context, request());
            $this->audit($subscription, 'request_confirmation');

            return $subscription->subscriber;
        });
    }

    public function unsubscribe(Subscriber $subscriber, int $subscriptionId): void
    {
        $this->authorize();
        DB::transaction(function () use ($subscriber, $subscriptionId): void {
            $subscription = $subscriber->subscriptions()->findOrFail($subscriptionId);
            app(UnsubscribeSubscription::class)->handle($subscription, request());
            $this->audit($subscription, 'unsubscribe');
        });
    }

    public function erase(Subscriber $subscriber): void
    {
        $this->authorize();
        DB::transaction(fn () => $subscriber->delete());
    }

    private function audit(Subscription $subscription, string $action): void
    {
        $subscription->events()->create(['type' => 'admin_action', 'tag' => $subscription->tag, 'source_page_id' => $subscription->source_page_id,
            'source_url' => $subscription->source_url, 'ip' => request()->ip(), 'user_agent' => request()->userAgent(),
            'meta' => ['action' => $action, 'actor_id' => Filament::auth()->id()]]);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Subscription>
     */
    public function filter(Builder $query, array $filters): Builder
    {
        foreach (['list_id', 'status', 'tag', 'source_page_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['from'] ?? null)) {
            $query->whereDate('requested_at', '>=', $filters['from']);
        }
        if (filled($filters['until'] ?? null)) {
            $query->whereDate('requested_at', '<=', $filters['until']);
        }

        return $query;
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @param  array<string, mixed>  $filters
     */
    public function csv(Builder $query, array $filters = []): StreamedResponse
    {
        $this->authorize();

        return response()->streamDownload(function () use ($query, $filters): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['email', 'list', 'status', 'tag', 'source_page_id', 'source_url', 'requested_at', 'confirmed_at', 'unsubscribed_at'], ',', '"', '');
            foreach ((clone $query)->lazyById(200) as $subscriber) {
                foreach ($this->filter($subscriber->subscriptions()->getQuery(), $filters)->with('list')->get() as $subscription) {
                    $row = [$subscriber->email, $subscription->list->key, $subscription->status->value, $subscription->tag, $subscription->source_page_id, $subscription->source_url, $subscription->requested_at->toIso8601String(), $subscription->confirmed_at?->toIso8601String(), $subscription->unsubscribed_at?->toIso8601String()];
                    $row = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $row);
                    fputcsv($stream, $row, ',', '"', '');
                }
            }
            fclose($stream);
        }, 'newsletter-subscribers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
