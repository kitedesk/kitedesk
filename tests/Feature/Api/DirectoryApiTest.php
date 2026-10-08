<?php

use App\Domain\Accounts\Models\Organization;
use App\Domain\KnowledgeBase\Models\Article;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('customers can be listed, shown and created', function () {
    Sanctum::actingAs(User::factory()->admin()->create(), ['users:read', 'users:write']);
    $organization = Organization::factory()->create();
    $customer = User::factory()->create(['name' => 'Zed Customer']);

    $this->getJson(route('api.v1.users.index', ['filter' => ['search' => 'Zed']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $customer->id);

    $this->getJson(route('api.v1.users.show', $customer))->assertOk()->assertJsonPath('data.email', $customer->email);

    $this->postJson(route('api.v1.users.store'), ['name' => 'New One', 'email' => 'NEW@example.com', 'organization_id' => $organization->id])
        ->assertCreated()
        ->assertJsonPath('data.type', 'customer')
        ->assertJsonPath('data.role', null)
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.organization.id', $organization->id);

    $this->postJson(route('api.v1.users.store'), ['name' => 'Dup', 'email' => 'new@example.com'])
        ->assertJsonValidationErrors('email');
});

test('creating users needs a role that manages the team', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['users:read', 'users:write']);

    $this->getJson(route('api.v1.users.index'))->assertOk();
    $this->postJson(route('api.v1.users.store'), ['name' => 'New One', 'email' => 'new@example.com'])->assertForbidden();

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

test('organizations can be listed and shown', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['users:read']);
    $organization = Organization::factory()->create(['name' => 'Acme']);
    Organization::factory()->create(['name' => 'Zeta Corp']);

    $this->getJson(route('api.v1.organizations.index'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $organization->id);
    $this->getJson(route('api.v1.organizations.index', ['filter' => ['search' => 'Zeta']]))->assertJsonCount(1, 'data');
    $this->getJson(route('api.v1.organizations.show', $organization))->assertOk()->assertJsonPath('data.name', $organization->name);
});

test('only published articles are exposed', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['kb:read']);
    $published = Article::factory()->create(['title' => 'Exporting data']);
    $draft = Article::factory()->draft()->create();
    Article::factory()->create(['title' => 'Billing basics']);

    $this->getJson(route('api.v1.help-center.articles.index', ['filter' => ['search' => 'Export']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonMissingPath('data.0.body');

    $this->getJson(route('api.v1.help-center.articles.show', $published->id))->assertOk()->assertJsonPath('data.body', $published->body);
    $this->getJson(route('api.v1.help-center.articles.show', $draft->id))->assertNotFound();
});
