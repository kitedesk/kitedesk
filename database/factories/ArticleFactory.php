<?php

namespace Database\Factories;

use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(5), '.');

        return [
            'section_id' => fn (): int => $this->newSection()->id,
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'excerpt' => fake()->sentence(12),
            'body' => collect(range(1, 3))->map(fn (): string => '<p>'.fake()->paragraph().'</p>')->implode(''),
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
    }

    private function newSection(): Section
    {
        $name = fake()->unique()->word().' '.fake()->word();

        $category = Category::query()->create([
            'name' => Str::title($name),
            'slug' => Str::slug($name),
        ]);

        return $category->sections()->create([
            'name' => 'General',
            'slug' => 'general',
        ]);
    }
}
