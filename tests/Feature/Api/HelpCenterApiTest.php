<?php

use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

test('help center managers also see drafts', function () {
    $draft = Article::factory()->draft()->create();
    Article::factory()->create();

    Sanctum::actingAs(User::factory()->agent()->create(), ['kb:read']);
    $this->getJson(route('api.v1.help-center.articles.index'))->assertJsonCount(1, 'data');
    $this->getJson(route('api.v1.help-center.articles.show', $draft->id))->assertNotFound();

    Sanctum::actingAs(User::factory()->admin()->create(), ['kb:read']);
    $this->getJson(route('api.v1.help-center.articles.index', ['filter' => ['status' => 'draft']]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
    $this->getJson(route('api.v1.help-center.articles.show', $draft->id))->assertOk();
});

test('categories, sections and articles can be written and published', function () {
    Http::fake();
    Webhook::factory()->create(['events' => ['article.published']]);
    Sanctum::actingAs(User::factory()->admin()->create(), ['kb:read', 'kb:write']);

    $category = $this->postJson(route('api.v1.help-center.categories.store'), ['name' => 'Getting started'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'getting-started')
        ->json('data.id');

    $section = $this->postJson(route('api.v1.help-center.sections.store'), ['category_id' => $category, 'name' => 'Accounts'])
        ->assertCreated()
        ->json('data.id');

    $article = $this->postJson(route('api.v1.help-center.articles.store'), [
        'section_id' => $section,
        'title' => 'Resetting your password',
        'body' => '<p>Use the link.</p><script>x</script>',
        'status' => 'draft',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'resetting-your-password')
        ->assertJsonPath('data.body', '<p>Use the link.</p>')
        ->json('data.id');

    expect(WebhookDelivery::query()->count())->toBe(0);

    $this->postJson(route('api.v1.help-center.articles.publish', $article))->assertOk()->assertJsonPath('data.status', 'published');
    $this->postJson(route('api.v1.help-center.articles.publish', $article))->assertOk();

    expect(WebhookDelivery::query()->sole()->payload['data']['article']['id'])->toBe($article);

    $this->postJson(route('api.v1.help-center.articles.unpublish', $article))->assertOk()->assertJsonPath('data.status', 'draft');
    $this->putJson(route('api.v1.help-center.categories.update', $category), ['name' => 'Start here'])->assertOk()->assertJsonPath('data.slug', 'start-here');
    $this->deleteJson(route('api.v1.help-center.categories.destroy', $category))->assertNoContent();

    expect(Article::query()->find($article))->toBeNull();
});

test('writing articles needs the help center permission', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['kb:read', 'kb:write']);

    $this->postJson(route('api.v1.help-center.categories.store'), ['name' => 'Nope'])->assertForbidden();
});
