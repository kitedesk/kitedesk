<?php

use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can create categories with unique slugs', function () {
    Category::query()->create(['name' => 'Billing', 'slug' => 'billing']);

    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.categories.store'), ['name' => 'Billing & Plans'])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.categories.store'), ['name' => 'Billing', 'slug' => 'billing'])
        ->assertSessionHasErrors('slug');

    expect(Category::query()->latest('id')->first()->slug)->toBe('billing-plans')
        ->and(Category::query()->count())->toBe(2);
});

test('admins can rename and delete categories, cascading to their content', function () {
    $article = Article::factory()->create();
    $category = $article->section->category;

    $this->actingAs($this->admin)
        ->patch(route('admin.knowledge-base.categories.update', $category), ['name' => 'Account', 'slug' => 'account', 'description' => 'All about accounts'])
        ->assertRedirect();
    expect($category->refresh()->only(['name', 'slug', 'description']))->toBe(['name' => 'Account', 'slug' => 'account', 'description' => 'All about accounts']);

    $this->actingAs($this->admin)->delete(route('admin.knowledge-base.categories.destroy', $category))->assertRedirect();

    expect(Category::query()->count())->toBe(0)
        ->and(Article::query()->count())->toBe(0);
});

test('admins can create, rename and delete sections', function () {
    $category = Category::query()->create(['name' => 'Billing', 'slug' => 'billing']);

    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.sections.store'), ['category_id' => $category->id, 'name' => 'Invoices'])
        ->assertRedirect();

    $section = Section::query()->sole();
    expect($section->slug)->toBe('invoices');

    $this->actingAs($this->admin)
        ->patch(route('admin.knowledge-base.sections.update', $section), ['name' => 'Receipts'])
        ->assertRedirect();
    expect($section->refresh()->name)->toBe('Receipts')
        ->and($section->slug)->toBe('receipts');

    $this->actingAs($this->admin)->delete(route('admin.knowledge-base.sections.destroy', $section))->assertRedirect();
    expect(Section::query()->count())->toBe(0);
});

test('sections require an existing category', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.sections.store'), ['category_id' => 999, 'name' => 'Orphan'])
        ->assertSessionHasErrors('category_id');
});

test('categories can be reordered', function () {
    $first = Category::query()->create(['name' => 'First', 'slug' => 'first', 'position' => 0]);
    $second = Category::query()->create(['name' => 'Second', 'slug' => 'second', 'position' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.categories.move', $second), ['direction' => 'up'])
        ->assertRedirect();

    expect(Category::query()->ordered()->pluck('id')->all())->toBe([$second->id, $first->id]);
});

test('non admins cannot manage categories', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('admin.knowledge-base.categories.store'), ['name' => 'Nope'])
        ->assertForbidden();
});
