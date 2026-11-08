<?php

namespace Tok3\SectionEngineNewsletter\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;

/** @extends Factory<NewsletterList> */
class NewsletterListFactory extends Factory
{
    protected $model = NewsletterList::class;

    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'name' => 'News', 'consent_text' => ['en' => 'I would like newsletter updates.', 'de' => 'Ich möchte den Newsletter erhalten.'], 'active' => true];
    }
}
