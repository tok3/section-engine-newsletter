<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class ConfirmController
{
    public function show(Subscription $subscription, string $token): View
    {
        $this->validateToken($subscription, $token);
        $expired = $subscription->confirmation_expires_at?->isPast() ?? true;
        $form = null;
        if ($expired) {
            $context = app(FormContext::class);
            $data = $context->create($subscription->list, LiveTokenRenderContext::frontend($subscription->source_locale, url: request()->url()), $subscription->tag);
            $form = view('section-engine-newsletter::form.default', [
                'list' => $subscription->list, 'data' => $data, 'encryptedContext' => $context->encrypt($data), 'preview' => false,
                'variant' => 'default', 'includeScript' => false, 'consent' => FormContext::localized($subscription->list->consent_text, $subscription->source_locale),
                'checkboxLabel' => __('section-engine-newsletter::newsletter.checkbox'), 'feedback' => [],
            ])->render();
        }

        return view('section-engine-newsletter::confirm', compact('subscription', 'token', 'expired', 'form'));
    }

    public function confirm(Request $request, Subscription $subscription, string $token): View
    {
        return DB::transaction(function () use ($request, $subscription, $token): View {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $this->validateToken($subscription, $token);
            abort_if($subscription->confirmation_expires_at?->isPast() ?? true, 410, __('section-engine-newsletter::newsletter.expired'));
            if ($subscription->status === SubscriptionStatus::Pending) {
                $subscription->update(['status' => SubscriptionStatus::Confirmed, 'confirmed_at' => now(), 'confirmed_ip' => $request->ip(), 'confirmed_user_agent' => $request->userAgent()]);
                $subscription->events()->create([
                    'type' => 'confirmed', 'tag' => $subscription->tag, 'source_page_id' => $subscription->source_page_id,
                    'source_url' => $subscription->source_url, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
                ]);
            }

            return view('section-engine-newsletter::result', ['message' => __('section-engine-newsletter::newsletter.confirmed')]);
        });
    }

    private function validateToken(Subscription $subscription, string $token): void
    {
        abort_unless(hash_equals($subscription->confirmation_token_hash ?? '', hash('sha256', $token)), 404);
        app()->setLocale($subscription->source_locale);
    }
}
