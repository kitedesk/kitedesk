<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branding\BrandColor;
use App\Domain\Branding\Branding;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Support\ReplyParser;
use App\Domain\Mail\Support\TemplateRenderer;
use App\Domain\Tickets\Models\TicketMessage;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Validation\Rule;

/**
 * The real email views with sample content, for the previews on the branding page and the
 * email template editor. Unsaved values on those forms (colour, name, footer, template text)
 * come in the query, so the preview follows what the admin is typing.
 */
class EmailPreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $input = $request->validate([
            'type' => ['nullable', Rule::in(['ticket', 'notification'])],
            'color' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:100'],
            'footer' => ['nullable', 'string', 'max:1000'],
            'event' => ['nullable', Rule::enum(EmailTemplateEvent::class)],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        app()->instance(Branding::class, $this->branding($request));

        $html = ($input['type'] ?? 'ticket') === 'notification'
            ? (string) $this->notification()->render()
            : $this->ticket(
                isset($input['event']) ? EmailTemplateEvent::from($input['event']) : EmailTemplateEvent::AgentReply,
                $input['body'] ?? null,
            );

        // Shown in an iframe on the admin pages: framing by this site only, and no scripts.
        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; img-src * data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
        ]);
    }

    /**
     * The saved branding, with the form's unsaved colour, name and footer.
     */
    private function branding(Request $request): Branding
    {
        $saved = Branding::current();
        $color = $request->string('color')->toString();

        return new Branding(
            name: $request->filled('name') ? $request->string('name')->toString() : $saved->name,
            primaryColor: BrandColor::isValid($color) ? $color : $saved->primaryColor,
            logo: $saved->logo,
            logoDark: $saved->logoDark,
            emailFromName: $saved->emailFromName,
            emailFooter: $request->has('footer') ? ($request->filled('footer') ? $request->string('footer')->toString() : null) : $saved->emailFooter,
            version: $saved->version,
        );
    }

    private function ticket(EmailTemplateEvent $event, ?string $body): string
    {
        $timezone = (string) config('app.timezone');
        $agent = new User(['name' => 'Ana Souza']);
        $customer = new User(['name' => 'Jane Cooper']);

        $reply = $this->sampleMessage($agent, '<p>Thanks for the details, Jane. We found the cause: exports over 2&nbsp;GB timed out. A fix is live, so you can run the export again.</p><p>Let us know if anything still looks off.</p>', 0);
        $history = collect([
            $this->sampleMessage($customer, '<p>Hi! Exporting our yearly report fails after a few minutes with a timeout. Could you take a look?</p>', 30),
        ]);

        $variables = [
            'ticket.number' => '#1042',
            'ticket.id' => '1042',
            'ticket.subject' => __('Export fails for large files'),
            'ticket.status' => __('Open'),
            'ticket.url' => url('/'),
            'requester.name' => $customer->name,
            'requester.first_name' => 'Jane',
            'requester.email' => 'jane@example.com',
            'recipient.name' => $event->isForStaff() ? $agent->name : $customer->name,
            'author.name' => $agent->name,
            'app.name' => Branding::current()->name(),
        ];

        return view('mail.ticket', [
            'marker' => $event->isForStaff() ? null : __(ReplyParser::MARKER),
            'intro' => TemplateRenderer::html($body ?? $event->defaultBody(), $variables),
            'reply' => $event === EmailTemplateEvent::AgentReply ? $reply : null,
            'history' => $event === EmailTemplateEvent::AgentReply ? $history : collect(),
            'url' => url('/'),
            'buttonLabel' => $event->isForStaff() ? __('Open ticket') : __('View request'),
            'reference' => __('Request :number', ['number' => '#1042']),
            'subject' => __('Export fails for large files'),
            'preheader' => __('Thanks for the details, Jane. We found the cause…'),
            'reason' => $event->isForStaff()
                ? __('You get this as a member of the support team.')
                : __('You get this because you have a request with us.'),
            'timezone' => $timezone,
        ])->render();
    }

    private function notification(): MailMessage
    {
        return (new MailMessage)
            ->subject(__('[:number] Assigned to you: :subject', ['number' => '#1042', 'subject' => __('Export fails for large files')]))
            ->greeting(__('Export fails for large files'))
            ->line(__(':name assigned ticket :number to you.', ['name' => 'Ana Souza', 'number' => '#1042']))
            ->action(__('Open ticket'), url('/'));
    }

    private function sampleMessage(User $author, string $body, int $minutesAgo): TicketMessage
    {
        $message = new TicketMessage(['body' => $body, 'is_internal' => false]);
        $message->created_at = CarbonImmutable::now()->subMinutes($minutesAgo);
        $message->setRelation('author', $author);
        $message->setRelation('secrets', collect());

        return $message;
    }
}
