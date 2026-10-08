<?php

namespace App\Domain\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Error pages in the app's own look: an Inertia page (inside the app shell for signed-in
 * people), with `resources/views/errors/minimal.blade.php` as the fallback when the app
 * itself can't render, e.g. while the database is down.
 */
class ErrorPages
{
    /**
     * Statuses that get the Inertia error page. Server errors only outside debug mode, so
     * developers keep the detailed exception page.
     */
    private const array PAGES = [403, 404, 429, 500, 503];

    public static function render(ExceptionResponse $error): ?Response
    {
        $status = $error->statusCode();

        if ($error->response instanceof JsonResponse) {
            return null;
        }

        // An expired CSRF token: back to the form, with a toast instead of a dead end.
        if ($status === 419) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The page expired, so nothing was saved. Please try again.')]);

            return back();
        }

        if (! in_array($status, self::PAGES, true) || ($status >= 500 && $status !== 503 && config('app.debug'))) {
            return null;
        }

        try {
            return $error->render('errors/show', [
                'status' => $status,
                ...self::content($status, $error->exception),
            ])->withSharedData()->toResponse($error->request);
        } catch (Throwable) {
            // Shared data needs the database and settings; fall back to the Blade page.
            return null;
        }
    }

    /**
     * The heading and explanation for a status, shared by the Inertia and Blade pages.
     *
     * @return array{title: string|null, description: string|null}
     */
    public static function content(int $status, ?Throwable $exception = null): array
    {
        return match ($status) {
            403 => [
                'title' => __("You don't have access to this page"),
                'description' => self::reason($exception) ?? __("You don't have permission to see this. If you think you should, ask an administrator."),
            ],
            404 => [
                'title' => __('Page not found'),
                'description' => __('The link may be broken, or the page may have been moved or deleted.'),
            ],
            419 => [
                'title' => __('Page expired'),
                'description' => __('Your session expired. Refresh the page and try again.'),
            ],
            429 => [
                'title' => __('Too many requests'),
                'description' => __("You're going a little fast. Wait a moment and try again."),
            ],
            500 => [
                'title' => __('Something went wrong'),
                'description' => __("We couldn't complete this request. Please try again in a moment."),
            ],
            503 => [
                'title' => __('Down for maintenance'),
                'description' => __("We're making some improvements. Please check back in a few minutes."),
            ],
            default => ['title' => null, 'description' => null],
        };
    }

    /**
     * The reason given to `abort(403, ...)` or a policy's `Response::deny(...)`, which is
     * written for people; Laravel's generic default is left out.
     */
    private static function reason(?Throwable $exception): ?string
    {
        if (! $exception instanceof AuthorizationException && ! $exception instanceof HttpExceptionInterface) {
            return null;
        }

        $message = $exception->getMessage();

        return in_array($message, ['', 'This action is unauthorized.', __('This action is unauthorized.')], true) ? null : $message;
    }
}
