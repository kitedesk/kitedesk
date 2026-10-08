<?php

use App\Domain\Branding\Branding;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('a missing page renders the error page', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/show')
            ->where('status', 404)
            ->where('title', 'Page not found')
            ->where('auth.user', null));
});

test('a refused page renders the error page for the signed-in user, with the reason when one was given', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('admin.branding.edit'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/show')
            ->where('status', 403)
            ->where('description', "You don't have permission to see this. If you think you should, ask an administrator.")
            ->where('auth.user.id', $agent->id));

    Route::get('/test/refused', fn () => abort(403, 'Your plan does not include this feature.'))->middleware('web');

    $this->actingAs($agent)->get('/test/refused')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->where('description', 'Your plan does not include this feature.'));
});

test('server errors render the error page outside debug mode only', function () {
    Route::get('/test/broken', fn () => throw new RuntimeException('Boom'))->middleware('web');

    config(['app.debug' => false]);
    $this->get('/test/broken')
        ->assertInternalServerError()
        ->assertInertia(fn (Assert $page) => $page->component('errors/show')->where('status', 500));

    config(['app.debug' => true]);
    $this->get('/test/broken')
        ->assertInternalServerError()
        ->assertSee('RuntimeException');
});

test('JSON requests keep their JSON errors', function () {
    $this->getJson('/no-such-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('an expired form goes back with a toast', function () {
    Route::post('/test/expired', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from('/help')->post('/test/expired')
        ->assertRedirect('/help')
        ->assertInertiaFlash('toast.message', 'The page expired, so nothing was saved. Please try again.');
});

test('falls back to the Blade page when the app cannot render', function () {
    app()->bind(Branding::class, fn () => throw new RuntimeException('Database is down'));

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('The link may be broken, or the page may have been moved or deleted.')
        ->assertDontSee('data-page', false);
});
