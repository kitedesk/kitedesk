<?php

namespace Database\Factories;

use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'url' => 'https://hooks.example.com/'.Str::random(8),
            'secret' => Str::random(40),
            'events' => array_map(fn (WebhookEvent $event): string => $event->value, WebhookEvent::cases()),
            'is_active' => true,
        ];
    }
}
