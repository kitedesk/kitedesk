<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Sla\SlaTracker;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Support\Webhooks;
use App\Domain\Widget\WidgetSettings;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Listeners\StartWorkflows;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/**
 * A plan without the given features and with the given limits, as the hosted edition binds.
 *
 * @param  list<Feature>  $without
 * @param  array<string, int>  $limits  keyed by Limit value
 */
function plan(array $without = [], array $limits = []): void
{
    app()->instance(Entitlements::class, new class($without, $limits) implements Entitlements
    {
        /**
         * @param  list<Feature>  $without
         * @param  array<string, int>  $limits
         */
        public function __construct(private array $without, private array $limits) {}

        public function allows(Feature $feature): bool
        {
            return ! in_array($feature, $this->without, true);
        }

        public function limit(Limit $limit): ?int
        {
            return $this->limits[$limit->value] ?? null;
        }
    });
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the open-source edition includes everything without limits', function () {
    $this->actingAs($this->admin)->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entitlements.features.workflows', true)
            ->where('entitlements.features.custom_branding', true)
            ->where('entitlements.limits.agent_seats', null));
});

test('the plan caps team members, but customers and existing staff are unaffected', function () {
    plan(limits: ['agent_seats' => 1]);
    $agentRole = Role::findByName(RoleCatalog::AGENT)->id;

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'New Agent', 'email' => 'new@example.com', 'type' => 'staff', 'role_id' => $agentRole,
    ])->assertSessionHasErrors(['role_id' => 'Your plan includes 1 team member.']);

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'A Customer', 'email' => 'customer@example.com', 'type' => 'customer',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'name' => 'Renamed', 'email' => $this->admin->email, 'type' => 'staff', 'role_id' => RoleCatalog::administrator()->id,
    ])->assertSessionHasNoErrors();

    $customer = User::query()->where('email', 'customer@example.com')->sole();
    $this->actingAs($this->admin)->put(route('admin.users.update', $customer), [
        'name' => $customer->name, 'email' => $customer->email, 'type' => 'staff', 'role_id' => $agentRole,
    ])->assertSessionHasErrors('role_id');

    expect($customer->refresh()->isStaff())->toBeFalse();
});

test('deactivated team members free their seat, and reactivating one needs a free seat', function () {
    $agent = User::factory()->agent()->create();
    plan(limits: ['agent_seats' => 2]);

    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $agent))->assertSessionHasNoErrors();
    expect(Limit::AgentSeats->usage())->toBe(1);

    User::factory()->agent()->create();

    $this->actingAs($this->admin)->post(route('admin.users.reactivate', $agent))
        ->assertSessionHasErrors(['user' => 'Your plan includes 2 team members.']);
    expect($agent->refresh()->isDeactivated())->toBeTrue();
});

test('the plan caps mailboxes and active workflows', function () {
    plan(limits: ['mailboxes' => 1, 'active_workflows' => 1]);
    Mailbox::factory()->create();
    Workflow::factory()->create(['is_active' => true]);
    $inactive = Workflow::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin)->post(route('admin.mailboxes.store'), [
        'name' => 'Sales', 'address' => 'sales@acme.test', 'driver' => 'postmark',
    ])->assertSessionHasErrors(['address' => 'Your plan includes 1 mailbox.']);

    $this->actingAs($this->admin)->patch(route('admin.workflows.toggle', $inactive))
        ->assertInertiaFlash('toast.message', 'Your plan includes 1 active workflow.');

    expect($inactive->refresh()->is_active)->toBeFalse()
        ->and(Mailbox::query()->count())->toBe(1);
});

test('workflows stop running and their pages close when the plan leaves them out', function () {
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [['update_ticket', ['priority' => 'urgent']]])->create();
    plan(without: [Feature::Workflows]);

    app(StartWorkflows::class)->handle(new TicketCreated(Ticket::factory()->create()));

    expect(WorkflowRun::query()->where('workflow_id', $workflow->id)->exists())->toBeFalse()
        ->and(Workflow::runnableBy($this->admin))->toBeEmpty();

    $this->actingAs($this->admin)->get(route('admin.workflows.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.routing-rules.index'))->assertOk();
});

test('the AI assistant, MCP server and API close when the plan leaves them out', function () {
    (new AiSettings(enabled: true, baseUrl: 'https://ai.example.test/v1', model: 'test-model', mcpEnabled: true))->save();
    plan(without: [Feature::Ai, Feature::Mcp, Feature::Api]);

    expect(AiSettings::current()->isAvailable())->toBeFalse();

    $this->actingAs($this->admin)->get(route('admin.ai.edit'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.api-tokens.index'))->assertForbidden();

    Sanctum::actingAs($this->admin, ['tickets:read']);
    $this->getJson(route('api.v1.tickets.index'))->assertForbidden();
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertNotFound();
});

test('custom branding needs the plan', function () {
    plan(without: [Feature::CustomBranding]);

    $this->actingAs($this->admin)->post(route('admin.branding.update'), [
        'name' => 'Acme', 'show_powered_by' => false, 'custom_css' => 'body { color: red; }',
    ])->assertSessionHasErrors(['show_powered_by', 'custom_css']);

    $this->actingAs($this->admin)->post(route('admin.branding.update'), [
        'name' => 'Acme', 'show_powered_by' => true, 'custom_css' => '',
    ])->assertSessionHasNoErrors();
});

test('the plan caps custom fields and ticket forms', function () {
    plan(limits: ['custom_fields' => 1, 'ticket_forms' => 1]);
    TicketField::factory()->create();
    TicketForm::factory()->default()->create();

    $this->actingAs($this->admin)->post(route('admin.ticket-fields.store'), ['label' => 'Order number', 'type' => 'text'])
        ->assertSessionHasErrors(['label' => 'Your plan includes 1 custom field.']);

    $this->actingAs($this->admin)->post(route('admin.ticket-forms.store'), ['name' => 'Billing', 'fields' => []])
        ->assertSessionHasErrors(['name' => 'Your plan includes 1 ticket form.']);

    expect(TicketField::query()->count())->toBe(1)
        ->and(TicketForm::query()->count())->toBe(1);
});

test('SLA targets stop applying and their pages close when the plan leaves them out', function () {
    SlaPolicy::factory()->create();
    plan(without: [Feature::Sla]);

    expect(app(SlaTracker::class)->policyFor(Ticket::factory()->create()))->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.sla-policies.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.business-schedules.index'))->assertForbidden();
});

test('without custom roles, the built-in ones stay editable and custom ones can only be deleted', function () {
    $custom = Role::create(['name' => 'Billing desk']);
    plan(without: [Feature::CustomRoles]);

    $this->actingAs($this->admin)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.roles.edit', $custom))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.roles.edit', Role::findByName(RoleCatalog::AGENT)))->assertOk();

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $custom))->assertRedirect(route('admin.roles.index'));
    expect(Role::query()->whereKey($custom->id)->exists())->toBeFalse();
});

test('the satisfaction survey, webhooks and reports turn off when the plan leaves them out', function () {
    (new SatisfactionSurvey(enabled: true, enabledSince: now()->toImmutable()))->save();
    Webhook::factory()->create(['events' => ['ticket.created']]);
    plan(without: [Feature::Satisfaction, Feature::Webhooks, Feature::Reports]);

    Webhooks::send(WebhookEvent::TicketCreated, ['ticket' => []]);

    expect(SatisfactionSurvey::current()->enabled)->toBeFalse()
        ->and(Webhooks::subscribed(WebhookEvent::TicketCreated))->toBeFalse()
        ->and(Webhook::query()->sole()->deliveries()->exists())->toBeFalse();

    $this->actingAs($this->admin)->get(route('admin.satisfaction.edit'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.webhooks.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('agent.reports.index'))->assertForbidden();
});

test('without custom email templates, the default wording is sent and only turning an email off is saved', function () {
    EmailTemplate::query()->create(['event' => EmailTemplateEvent::TicketReceived, 'subject' => 'Custom', 'body' => 'Custom body', 'is_active' => true]);
    plan(without: [Feature::CustomEmailTemplates]);

    expect(EmailTemplate::for(EmailTemplateEvent::TicketReceived)->subject)->toBe(EmailTemplateEvent::TicketReceived->defaultSubject());

    $this->actingAs($this->admin)->put(route('admin.email-templates.update', 'ticket_received'), [
        'subject' => 'Changed', 'body' => 'Changed body', 'is_active' => false,
    ])->assertRedirect(route('admin.email-templates.index'));

    $saved = EmailTemplate::query()->sole();
    expect($saved->subject)->toBe('Custom')
        ->and($saved->is_active)->toBeFalse()
        ->and(EmailTemplate::for(EmailTemplateEvent::TicketReceived)->is_active)->toBeFalse();
});

test('the website widget disappears from websites when the plan leaves it out', function () {
    (new WidgetSettings(enabled: true))->save();
    plan(without: [Feature::Widget]);

    $this->get(route('widget.script'))->assertNotFound();
    $this->get(route('widget.frame'))->assertNotFound();
    $this->actingAs($this->admin)->get(route('admin.widget.edit'))->assertForbidden();
});

test('a plan without internal requests stops new ones but keeps existing ones open to their requester', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create(['channel' => TicketChannel::Internal, 'requester_id' => $agent->id]);
    plan(without: [Feature::InternalRequests]);

    $this->actingAs($agent)->get(route('agent.requests.create'))->assertForbidden();
    $this->actingAs($agent)->post(route('agent.requests.store'), ['group_id' => Group::factory()->create()->id, 'subject' => 'Hi', 'body' => '<p>Hi</p>'])->assertForbidden();

    $this->actingAs($agent)->get(route('agent.requests.index'))->assertOk();
    $this->actingAs($agent)->get(route('agent.requests.show', $ticket))->assertOk();
    $this->actingAs($agent)->post(route('agent.requests.replies.store', $ticket), ['body' => '<p>Still there?</p>'])->assertSessionHasNoErrors();
});
