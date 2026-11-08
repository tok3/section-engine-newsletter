<?php

namespace Tok3\SectionEngineNewsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class SubscriptionUnsubscribed
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription) {}
}
