<?php

use App\Domain\KnowledgeBase\Models\Article;
use Inertia\Testing\AssertableInertia as Assert;

test('the help center lists categories that have published articles', function () {
    $published = Article::factory()->create();
    Article::factory()->draft()->create();

    $this->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/index')
            ->has('categories', 1)
            ->where('categories.0.id', $published->section->category_id));
});

test('published articles are public and count views', function () {
    $article = Article::factory()->create();

    $this->get(route('help.articles.show', $article))->assertOk();

    expect($article->refresh()->view_count)->toBe(1);
});

test('draft articles are not public', function () {
    $this->get(route('help.articles.show', Article::factory()->draft()->create()))->assertNotFound();
});

test('search only returns published articles', function () {
    $published = Article::factory()->create(['title' => 'Resetting your password']);
    Article::factory()->draft()->create(['title' => 'Password policy (draft)']);

    $this->getJson(route('help.search', ['q' => 'password']))
        ->assertOk()
        ->assertJsonCount(1, 'articles')
        ->assertJsonPath('articles.0.id', $published->id);
});

test('readers can rate an article', function () {
    $article = Article::factory()->create();

    $this->postJson(route('help.articles.feedback', $article), ['helpful' => true])->assertOk();

    expect($article->refresh()->helpful_count)->toBe(1);
});
