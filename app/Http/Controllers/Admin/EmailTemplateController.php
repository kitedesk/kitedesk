<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEmailTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/email-templates/index', [
            'templates' => array_map(fn (EmailTemplateEvent $event): array => [
                ...$this->serialize($event),
            ], EmailTemplateEvent::cases()),
        ]);
    }

    public function edit(EmailTemplateEvent $event): Response
    {
        return Inertia::render('admin/email-templates/edit', [
            'template' => $this->serialize($event),
        ]);
    }

    /**
     * Without custom wording in the plan, only switching the email on or off is saved.
     */
    public function update(SaveEmailTemplateRequest $request, EmailTemplateEvent $event): RedirectResponse
    {
        $template = EmailTemplate::query()->firstOrNew(['event' => $event->value], [
            'subject' => $event->defaultSubject(),
            'body' => $event->defaultBody(),
        ]);

        if (PlanLimits::allows(Feature::CustomEmailTemplates)) {
            $template->fill([
                'subject' => $request->string('subject')->toString(),
                'body' => $request->string('body')->toString(),
            ]);
        }

        $template->fill(['is_active' => $request->boolean('is_active', true)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email template saved.')]);

        return to_route('admin.email-templates.index');
    }

    /**
     * Go back to the default wording (in the installation language).
     */
    public function destroy(EmailTemplateEvent $event): RedirectResponse
    {
        EmailTemplate::query()->where('event', $event->value)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email template reset to the default.')]);

        return to_route('admin.email-templates.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(EmailTemplateEvent $event): array
    {
        $template = EmailTemplate::for($event);

        return [
            'event' => $event->value,
            'label' => $event->label(),
            'description' => $event->description(),
            'subject' => $template->subject,
            'body' => $template->body,
            'is_active' => $template->is_active,
            'is_customized' => $template->exists,
            'placeholders' => $event->placeholders(),
            'default_subject' => $event->defaultSubject(),
            'default_body' => $event->defaultBody(),
        ];
    }
}
