<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the interface uses the installation language, whatever the browser asks for', function () {
    app()->setLocale('pt_BR');

    $this->withHeader('Accept-Language', 'en-US,en')
        ->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'pt_BR')
            ->where('translations.Submit a request', 'Enviar uma solicitação'));
});

test('an English installation sends no translations', function () {
    $this->withHeader('Accept-Language', 'pt-BR')
        ->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('translations', []));
});

test('validation messages follow the installation language', function () {
    app()->setLocale('pt_BR');

    $this->actingAs(User::factory()->create())
        ->post(route('portal.tickets.store'), ['body' => '<p>Hi</p>'])
        ->assertSessionHasErrors(['subject' => 'O campo assunto é obrigatório.']);
});

test('people who picked a language in their profile get it, in the interface and their emails', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'en-US,en')
        ->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'pt_BR')
            ->where('translations.Submit a request', 'Enviar uma solicitação'));

    expect($user->preferredLocale())->toBe('pt_BR')
        ->and(User::factory()->make(['locale' => 'xx'])->preferredLocale())->toBeNull();
});
