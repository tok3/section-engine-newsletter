<?php

namespace Tok3\SectionEngineNewsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Tok3\SectionEngineNewsletter\Models\Subscriber;

class SubscriberDeleted
{
    use Dispatchable;

    public function __construct(public readonly Subscriber $subscriber) {}
}
