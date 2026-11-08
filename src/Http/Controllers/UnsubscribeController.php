<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Services\UnsubscribeSubscription;

class UnsubscribeController
{
    public function show(Subscription $subscription): View
    {
        app()->setLocale($subscription->source_locale);

        return view('section-engine-newsletter::unsubscribe', compact('subscription'));
    }

    public function unsubscribe(Request $request, Subscription $subscription, UnsubscribeSubscription $service): View
    {
        $service->handle($subscription, $request);
        app()->setLocale($subscription->source_locale);

        return view('section-engine-newsletter::result', ['message' => __('section-engine-newsletter::newsletter.unsubscribed')]);
    }

    public function oneClick(Request $request, Subscription $subscription, UnsubscribeSubscription $service): Response
    {
        $request->validate(['List-Unsubscribe' => ['required', 'in:One-Click']]);
        $service->handle($subscription, $request);

        return response('', 200);
    }
}
