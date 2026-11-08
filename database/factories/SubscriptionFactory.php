<?php

namespace Tok3\SectionEngineNewsletter\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return ['subscriber_id' => Subscriber::factory(), 'list_id' => NewsletterList::factory(), 'status' => 'pending', 'source_locale' => 'en', 'consent_text_snapshot' => 'I consent', 'consent_hash' => hash('sha256', 'I consent'), 'requested_at' => now()];
    }
}
