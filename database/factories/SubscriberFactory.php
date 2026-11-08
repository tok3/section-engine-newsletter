<?php

namespace Tok3\SectionEngineNewsletter\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tok3\SectionEngineNewsletter\Models\Subscriber;

/** @extends Factory<Subscriber> */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return ['email' => fake()->unique()->safeEmail(), 'locale' => 'en'];
    }
}
