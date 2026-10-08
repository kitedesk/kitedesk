<?php

namespace Database\Factories;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\RoutingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutingRule>
 */
class RoutingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->word()).' '.fake()->word(),
            'is_active' => true,
            'match' => 'all',
            'conditions' => [],
            'actions' => fn (): array => ['group_id' => Group::factory()->create()->id, 'priority' => null, 'tags' => []],
            'position' => 0,
        ];
    }

    /**
     * @param  list<array{field: string, operator: string, value: string}>  $conditions
     */
    public function matching(array $conditions, string $match = 'all'): static
    {
        return $this->state(fn (array $attributes) => ['conditions' => $conditions, 'match' => $match]);
    }

    /**
     * @param  list<string>  $tags
     */
    public function routesTo(Group $group, ?string $priority = null, array $tags = []): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => ['group_id' => $group->id, 'priority' => $priority, 'tags' => $tags],
        ]);
    }
}
