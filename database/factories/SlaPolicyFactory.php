<?php

namespace Database\Factories;

use App\Domain\Sla\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard SLA',
            'description' => null,
            'business_schedule_id' => null,
            'conditions' => null,
            'targets' => [
                'urgent' => ['first_response' => 60, 'next_reply' => 120, 'resolution' => 480],
                'high' => ['first_response' => 240, 'next_reply' => 480, 'resolution' => 1440],
                'normal' => ['first_response' => 480, 'next_reply' => 960, 'resolution' => 2880],
                'low' => ['first_response' => 1440, 'next_reply' => 2880, 'resolution' => 5760],
            ],
            'position' => 0,
            'is_active' => true,
        ];
    }
}
