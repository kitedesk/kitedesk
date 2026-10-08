<?php

namespace Database\Factories;

use App\Domain\Secrets\Enums\SecretKind;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Secrets\Support\SecretVault;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a shared secret whose content is "factory-secret".
 *
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    public const string CONTENT = 'factory-secret';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => SecretKind::Share,
            'ticket_id' => Ticket::factory(),
            'created_by' => User::factory()->agent(),
            'label' => fake()->words(2, true),
            'ciphertext' => app(SecretVault::class)->encrypt(self::CONTENT),
            'max_views' => 1,
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * A request the customer hasn't answered yet.
     */
    public function request(): static
    {
        return $this->state(fn (): array => [
            'kind' => SecretKind::Request,
            'ciphertext' => null,
        ]);
    }

    /**
     * A request the customer has answered.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'kind' => SecretKind::Request,
            'ciphertext' => app(SecretVault::class)->encrypt(self::CONTENT),
            'submitted_at' => now(),
        ]);
    }
}
