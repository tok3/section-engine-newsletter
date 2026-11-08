<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Contracts\View\View;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Services\ManageSubscribers;

class ProofController
{
    public function __invoke(Subscriber $subscriber, ManageSubscribers $service): View
    {
        $service->authorize();

        return view('section-engine-newsletter::admin.proof', ['subscriber' => $subscriber->load('subscriptions.events', 'subscriptions.list')]);
    }
}
