<?php

namespace App\Http\Controllers;

use App\Domain\Branding\Branding;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\SubmitGuestTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Support\TicketCatalog;
use App\Domain\Widget\WidgetSettings;
use App\Http\Requests\Widget\StoreWidgetTicketRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The support widget websites embed with `<script src="…/widget.js" async></script>`. The script
 * draws a button that opens the frame page in an iframe. The frame sends requests without
 * cookies (browsers block them in third-party frames), so its form is protected by the
 * CAPTCHA and a rate limit instead of a CSRF token.
 */
class WidgetController extends Controller
{
    private const string LOADER = 'resources/js/widget/loader.ts';

    /**
     * The embed script: the widget's settings followed by the built loader. Its URL never
     * changes, so it is cached briefly and picks up new releases and settings within minutes.
     */
    public function script(): Response
    {
        $settings = $this->settings();
        $config = 'window.KiteDeskWidgetConfig = '.json_encode($settings->loaderConfig(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).";\n";

        // With `npm run dev` the loader comes from the Vite server.
        $loader = Vite::isRunningHot()
            ? 'import('.json_encode(Vite::asset(self::LOADER), JSON_UNESCAPED_SLASHES).');'
            : Vite::content(self::LOADER);

        return response($config.$loader, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function frame(Request $request): SymfonyResponse
    {
        $settings = $this->settings();

        $response = Inertia::render('widget/frame', [
            'greeting' => $settings->greeting,
            'brandName' => Branding::current()->name(),
            'categories' => TicketCatalog::categories(customerFacing: true),
            'forms' => TicketCatalog::forms(customerFacing: true),
            'defaultFormId' => TicketCatalog::defaultFormId(),
        ])->toResponse($request);

        // Only the allowed websites may show it; AddSecurityHeaders leaves this policy alone.
        $response->headers->set('Content-Security-Policy', 'frame-ancestors '.$settings->frameAncestors());

        return $response;
    }

    public function store(StoreWidgetTicketRequest $request, SubmitGuestTicket $submitGuestTicket): JsonResponse
    {
        $this->settings();

        $submission = $submitGuestTicket->handle([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'subject' => $request->string('subject')->toString(),
            'body' => RichText::fromPlainText($request->string('body')->toString()),
            'category_id' => $request->validated('category_id'),
            'custom_fields' => $request->customFields(),
            'attachments' => $request->attachments(),
        ], TicketChannel::Widget);

        // The visitor always follows the request by email, so nobody learns whether the address
        // belongs to an account (or a deactivated one).
        return response()->json(['reference' => $submission?->ticket->reference()], 201);
    }

    private function settings(): WidgetSettings
    {
        $settings = WidgetSettings::current();
        abort_unless($settings->isAvailable(), 404);

        return $settings;
    }
}
