<?php

use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\KnowledgeBase\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $category = Category::query()->create(['name' => 'Billing', 'slug' => 'billing']);
    $this->section = Section::query()->create(['category_id' => $category->id, 'name' => 'Invoices', 'slug' => 'invoices']);
});

/**
 * @return array<string, mixed>
 */
function articlePayload(Section $section, array $overrides = []): array
{
    return [
        'section_id' => $section->id,
        'title' => 'Downloading invoices',
        'slug' => '',
        'excerpt' => 'Where to find your invoices.',
        'body' => '<p>Go to <strong>Billing</strong>.</p>',
        'status' => 'draft',
        ...$overrides,
    ];
}

test('non admins cannot manage the help center', function () {
    $article = Article::factory()->create();

    $this->actingAs(User::factory()->agent()->create())->get(route('admin.knowledge-base.index'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->post(route('admin.knowledge-base.articles.store'), articlePayload($this->section))->assertForbidden();
    $this->actingAs(User::factory()->create())->delete(route('admin.knowledge-base.articles.destroy', $article))->assertForbidden();

    expect(Article::query()->count())->toBe(1);
});

test('the index shows the category tree with articles', function () {
    Article::factory()->create(['section_id' => $this->section->id, 'title' => 'Refunds']);

    $this->actingAs($this->admin)
        ->get(route('admin.knowledge-base.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/knowledge-base/index')
            ->has('categories', 1)
            ->where('categories.0.sections.0.articles.0.title', 'Refunds')
            ->where('categories.0.sections.0.articles.0.is_published', true));
});

test('admins can create a draft article with a slug derived from the title and a sanitized body', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.articles.store'), articlePayload($this->section, [
            'body' => '<p>Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
        ]))
        ->assertRedirect();

    $article = Article::query()->sole();
    expect($article->slug)->toBe('downloading-invoices')
        ->and($article->author_id)->toBe($this->admin->id)
        ->and($article->status)->toBe(ArticleStatus::Draft)
        ->and($article->published_at)->toBeNull()
        ->and($article->body)->not->toContain('<script')
        ->and($article->body)->not->toContain('javascript:');
});

test('the create and edit pages render', function () {
    $article = Article::factory()->create(['section_id' => $this->section->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.knowledge-base.articles.create', ['section_id' => $this->section->id]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/knowledge-base/articles/form')->where('article', null)->where('sectionId', $this->section->id));

    $this->actingAs($this->admin)
        ->get(route('admin.knowledge-base.articles.edit', $article))
        ->assertInertia(fn (Assert $page) => $page->where('article.id', $article->id)->has('sections', 1));
});

test('publishing a draft stamps the publication date once', function () {
    $this->travelTo('2026-10-05 12:00:00');
    $article = Article::factory()->draft()->create(['section_id' => $this->section->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.knowledge-base.articles.update', $article), articlePayload($this->section, ['title' => $article->title, 'slug' => $article->slug, 'status' => 'published']))
        ->assertRedirect();

    $this->travel(1)->day();

    expect($article->refresh()->published_at->toDateTimeString())->toBe('2026-10-05 12:00:00')
        ->and($article->isPublished())->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('admin.knowledge-base.articles.update', $article), articlePayload($this->section, ['title' => 'Renamed', 'slug' => $article->slug, 'status' => 'published']));

    expect($article->refresh()->published_at->toDateTimeString())->toBe('2026-10-05 12:00:00')
        ->and($article->title)->toBe('Renamed');
});

test('article slugs must be unique', function () {
    Article::factory()->create(['slug' => 'downloading-invoices']);

    $this->actingAs($this->admin)
        ->post(route('admin.knowledge-base.articles.store'), articlePayload($this->section))
        ->assertSessionHasErrors('slug');
});

test('an article keeps its own slug when updated', function () {
    $article = Article::factory()->create(['section_id' => $this->section->id, 'slug' => 'my-slug']);

    $this->actingAs($this->admin)
        ->patch(route('admin.knowledge-base.articles.update', $article), articlePayload($this->section, ['slug' => 'my-slug']))
        ->assertSessionHasNoErrors();
});

test('admins can delete articles', function () {
    $article = Article::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.knowledge-base.articles.destroy', $article))
        ->assertRedirect(route('admin.knowledge-base.index'));

    expect(Article::query()->find($article->id))->toBeNull();
});
