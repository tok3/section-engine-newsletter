<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngine\Support\ThemePageResponse;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Events\SubscriptionConfirmed;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class ConfirmController
{
    public function show(Subscription $subscription, string $token, ThemePageResponse $themePage): Response
    {
        if (! $this->validToken($subscription, $token)) {
            return $this->resultPage(
                $themePage,
                __('section-engine-newsletter::newsletter.invalid_confirmation', [], $subscription->source_locale),
                $subscription->source_locale,
                404,
            );
        }

        $expired = $subscription->confirmation_expires_at?->isPast() ?? true;
        $form = null;
        if ($expired) {
            $context = app(FormContext::class);
            $data = $context->create($subscription->list, LiveTokenRenderContext::frontend($subscription->source_locale, url: request()->url()), $subscription->tag);
            $form = view('section-engine-newsletter::form.default', [
                'list' => $subscription->list, 'data' => $data, 'encryptedContext' => $context->encrypt($data), 'preview' => false,
                'variant' => 'default', 'includeScript' => false, 'consent' => FormContext::localized($subscription->list->consent_text, $subscription->source_locale),
                'checkboxLabel' => __('section-engine-newsletter::newsletter.checkbox', [], $subscription->source_locale), 'feedback' => [],
            ])->render();
        }

        $html = view('section-engine-newsletter::pages.confirm', [
            'subscription' => $subscription,
            'token' => $token,
            'expired' => $expired,
            'form' => $form,
            'title' => $expired
                ? __('section-engine-newsletter::newsletter.expired_title', [], $subscription->source_locale)
                : __('section-engine-newsletter::newsletter.confirm_title', [], $subscription->source_locale),
            'expiredMessage' => __('section-engine-newsletter::newsletter.expired', [], $subscription->source_locale),
            'confirmLabel' => __('section-engine-newsletter::newsletter.confirm_button', [], $subscription->source_locale),
        ])->render();

        return $themePage->make(
            $expired
                ? __('section-engine-newsletter::newsletter.expired_title', [], $subscription->source_locale)
                : __('section-engine-newsletter::newsletter.confirm_title', [], $subscription->source_locale),
            $html,
            $subscription->source_locale,
            ['status' => $expired ? 410 : 200, 'robots' => 'noindex, nofollow'],
        );
    }

    public function confirm(Request $request, Subscription $subscription, string $token, ThemePageResponse $themePage): Response
    {
        return DB::transaction(function () use ($request, $subscription, $token, $themePage): Response {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if (! $this->validToken($subscription, $token)) {
                return $this->resultPage(
                    $themePage,
                    __('section-engine-newsletter::newsletter.invalid_confirmation', [], $subscription->source_locale),
                    $subscription->source_locale,
                    404,
                );
            }
            if ($subscription->confirmation_expires_at?->isPast() ?? true) {
                return $this->resultPage(
                    $themePage,
                    __('section-engine-newsletter::newsletter.expired', [], $subscription->source_locale),
                    $subscription->source_locale,
                    410,
                );
            }
            if ($subscription->status === SubscriptionStatus::Pending) {
                $subscription->update(['status' => SubscriptionStatus::Confirmed, 'confirmed_at' => now(), 'confirmed_ip' => $request->ip(), 'confirmed_user_agent' => $request->userAgent()]);
                $subscription->events()->create([
                    'type' => 'confirmed', 'tag' => $subscription->tag, 'source_page_id' => $subscription->source_page_id,
                    'source_url' => $subscription->source_url, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
                ]);
                DB::afterCommit(fn () => SubscriptionConfirmed::dispatch($subscription));
            }

            return $this->resultPage(
                $themePage,
                __('section-engine-newsletter::newsletter.confirmed', [], $subscription->source_locale),
                $subscription->source_locale,
            );
        });
    }

    private function validToken(Subscription $subscription, string $token): bool
    {
        return hash_equals($subscription->confirmation_token_hash ?? '', hash('sha256', $token));
    }

    private function resultPage(ThemePageResponse $themePage, string $message, string $locale, int $status = 200): Response
    {
        return $themePage->make(
            $message,
            view('section-engine-newsletter::pages.result', compact('message'))->render(),
            $locale,
            ['status' => $status, 'robots' => 'noindex, nofollow'],
        );
    }
}
