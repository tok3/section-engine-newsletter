<?php

namespace Tok3\SectionEngineNewsletter\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Models\SubscriptionEvent;

/** @extends Factory<SubscriptionEvent> */
class SubscriptionEventFactory extends Factory
{
    protected $model = SubscriptionEvent::class;

    public function definition(): array
    {
        return ['subscription_id' => Subscription::factory(), 'type' => 'requested', 'ip' => '2001:db8::1234', 'user_agent' => 'Test browser', 'created_at' => now()];
    }
}
