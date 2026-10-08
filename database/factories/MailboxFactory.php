<?php

namespace Database\Factories;

use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Support',
            'address' => fake()->unique()->userName().'@support.test',
            'is_default' => false,
            'driver' => MailboxDriver::Imap,
            'imap_host' => 'imap.support.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'support',
            'imap_password' => 'secret',
            'imap_folder' => 'INBOX',
            'inbound_secret' => Str::random(32),
            'is_active' => true,
        ];
    }

    public function driver(MailboxDriver $driver): static
    {
        return $this->state(fn (array $attributes) => ['driver' => $driver]);
    }
}
