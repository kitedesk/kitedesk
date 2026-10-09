<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the interface uses the installation language, whatever the browser asks for', function () {
    config(['kitedesk.default_locale' => 'pt_BR']);

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
    config(['kitedesk.default_locale' => 'pt_BR']);

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
        ->and(User::factory()->make(['locale' => 'xx'])->preferredLocale())->toBe('en');
});

test('admins pick the default language for visitors and everyone without a choice', function () {
    $admin = User::factory()->admin()->create(['locale' => 'en']);
    $customer = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.branding.update'), ['name' => 'Acme Support', 'locale' => 'pt_BR'])
        ->assertSessionHasNoErrors();

    $this->actingAs($customer)->get(route('help.index'))->assertInertia(fn (Assert $page) => $page->where('locale', 'pt_BR'));
    expect($customer->preferredLocale())->toBe('pt_BR')
        ->and($admin->preferredLocale())->toBe('en');

    $this->actingAs($admin)
        ->post(route('admin.branding.update'), ['name' => 'Acme Support', 'locale' => 'xx'])
        ->assertSessionHasErrors('locale');
});
