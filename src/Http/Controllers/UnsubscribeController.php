<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tok3\SectionEngine\Support\ThemePageResponse;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Services\UnsubscribeSubscription;
use Tok3\SectionEngineNewsletter\Support\SafeSourceUrl;

class UnsubscribeController
{
    public function show(Subscription $subscription, ThemePageResponse $themePage): Response
    {
        $title = __('section-engine-newsletter::newsletter.unsubscribe_title', [], $subscription->source_locale);
        $html = view('section-engine-newsletter::pages.unsubscribe', [
            'subscription' => $subscription,
            'title' => $title,
            'buttonLabel' => __('section-engine-newsletter::newsletter.unsubscribe', [], $subscription->source_locale),
        ])->render();

        return $themePage->make($title, $html, $subscription->source_locale, ['robots' => 'noindex, nofollow']);
    }

    public function unsubscribe(Request $request, Subscription $subscription, UnsubscribeSubscription $service, ThemePageResponse $themePage, SafeSourceUrl $safeSourceUrl): Response
    {
        $service->handle($subscription, $request);

        return $this->resultPage($subscription, $themePage, $safeSourceUrl);
    }

    public function oneClick(Request $request, Subscription $subscription, UnsubscribeSubscription $service, ThemePageResponse $themePage, SafeSourceUrl $safeSourceUrl): Response
    {
        $request->validate(['List-Unsubscribe' => ['required', 'in:One-Click']]);
        $service->handle($subscription, $request);

        return $this->resultPage($subscription, $themePage, $safeSourceUrl);
    }

    private function resultPage(Subscription $subscription, ThemePageResponse $themePage, SafeSourceUrl $safeSourceUrl): Response
    {
        $message = __('section-engine-newsletter::newsletter.unsubscribed', [], $subscription->source_locale);
        $returnUrl = $safeSourceUrl->get($subscription->source_url);
        $html = view('section-engine-newsletter::pages.result', [
            'message' => $message,
            'returnUrl' => $returnUrl,
            'returnLabel' => __('section-engine-newsletter::newsletter.return_to_site', [], $subscription->source_locale),
        ])->render();

        return $themePage->make($message, $html, $subscription->source_locale, ['robots' => 'noindex, nofollow']);
    }
}
