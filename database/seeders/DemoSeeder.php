<?php

namespace Database\Seeders;

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Category;
use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Sla\Models\BusinessSchedule;
use App\Domain\Sla\Models\SlaPolicy;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\MergeTickets;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\CannedResponse;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Support\WorkflowTemplates;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * A realistic helpdesk to explore locally. Every account uses the password "password".
 *
 *  - admin@example.com   (administrator)
 *  - agent@example.com   (agent)
 *  - light@example.com   (light agent)
 *  - customer@example.com (customer)
 */
class DemoSeeder extends Seeder
{
    public function run(CreateTicket $createTicket, AddMessage $addMessage, UpdateTicket $updateTicket, MergeTickets $mergeTickets): void
    {
        // Avoid sending mail, webhooks and broadcasts while seeding (model events still run, so the audit log is kept).
        Event::fake([TicketCreated::class, TicketUpdated::class, MessageCreated::class]);

        $admin = $this->staff('Alex Admin', 'admin@example.com', RoleCatalog::ADMINISTRATOR);
        $agent = $this->staff('Sam Agent', 'agent@example.com', RoleCatalog::AGENT);
        $priya = $this->staff('Priya Raman', 'priya@example.com', RoleCatalog::AGENT);
        $this->staff('Lee Light', 'light@example.com', RoleCatalog::LIGHT_AGENT);

        $support = Group::query()->create(['name' => 'Support', 'description' => 'First line support']);
        $billing = Group::query()->create(['name' => 'Billing', 'description' => 'Invoices, refunds and plans']);
        $support->agents()->attach([$agent->id, $priya->id, $admin->id]);
        $billing->agents()->attach([$priya->id]);

        $acme = Organization::query()->create(['name' => 'Acme Inc.', 'domains' => ['acme.test']]);
        $globex = Organization::query()->create(['name' => 'Globex', 'domains' => ['globex.test']]);

        $customer = User::factory()->create(['name' => 'Casey Customer', 'email' => 'customer@example.com']);
        $customers = collect([
            ['Jordan Blake', 'jordan@acme.test', $acme],
            ['Morgan Lee', 'morgan@acme.test', $acme],
            ['Taylor Kim', 'taylor@globex.test', $globex],
            ['Riley Chen', 'riley@example.org', null],
        ])->map(function (array $row): User {
            $user = User::factory()->create(['name' => $row[0], 'email' => $row[1]]);
            $user->organization()->associate($row[2])->save();

            return $user;
        })->prepend($customer);

        $schedule = BusinessSchedule::query()->create([
            'name' => 'Business hours',
            'timezone' => config('app.timezone'),
            'hours' => array_fill_keys([1, 2, 3, 4, 5], [['start' => '09:00', 'end' => '18:00']]),
        ]);

        SlaPolicy::factory()->create([
            'name' => 'VIP customers',
            'description' => 'Faster targets for key accounts',
            'conditions' => ['organization_ids' => [$acme->id]],
            'targets' => [
                'urgent' => ['first_response' => 30, 'next_reply' => 60, 'resolution' => 240],
                'high' => ['first_response' => 60, 'next_reply' => 120, 'resolution' => 480],
                'normal' => ['first_response' => 120, 'next_reply' => 240, 'resolution' => 960],
                'low' => ['first_response' => 240, 'next_reply' => 480, 'resolution' => 1920],
            ],
            'position' => 0,
        ]);
        SlaPolicy::factory()->create([
            'name' => 'Standard',
            'business_schedule_id' => $schedule->id,
            'position' => 1,
        ]);

        $categories = $this->seedTicketSetup($support, $billing);

        foreach ($this->scenarios($categories, $agent, $priya) as $index => $scenario) {
            ['subject' => $subject, 'priority' => $priority, 'category' => $category, 'fields' => $fields, 'assignee' => $assignee, 'status' => $status, 'hoursAgo' => $hoursAgo, 'tags' => $tags] = $scenario;
            $requester = $customers[$index % $customers->count()];

            Date::setTestNow(now()->subHours($hoursAgo));

            $ticket = $createTicket->handle($requester, [
                'subject' => $subject,
                'body' => '<p>Hi team,</p><p>'.fake()->paragraph(3).'</p><p>Thanks!</p>',
                'priority' => $priority,
                'category_id' => $category->id,
                'tags' => $tags,
                'custom_fields' => $fields,
            ], TicketChannel::Portal);

            if ($assignee !== null) {
                Date::setTestNow(now()->addMinutes(20));
                $updateTicket->handle($ticket, ['assignee_id' => $assignee->id], $assignee);
                $addMessage->handle($ticket, $assignee, '<p>Thanks for reaching out — I\'m looking into this now.</p>');
                $addMessage->handle($ticket, $assignee, '<p>Checked the logs, looks related to last week\'s deploy.</p>', isInternal: true);
            }

            if (in_array($status, [TicketStatus::Pending, TicketStatus::OnHold, TicketStatus::Solved], true) && $assignee !== null) {
                Date::setTestNow(now()->addHour());
                $addMessage->handle($ticket, $assignee, '<p>'.fake()->sentence(14).'</p>', statusAfter: $status);
            }

            if ($status === TicketStatus::Open && $assignee !== null) {
                Date::setTestNow(now()->addHours(2));
                $addMessage->handle($ticket, $requester, '<p>'.fake()->sentence(10).'</p>', channel: TicketChannel::Portal);
            }
        }

        Date::setTestNow();

        $this->seedAgentTools($admin, $agent, $support, $customers, $createTicket, $mergeTickets);
        $this->seedHelpCenter($admin);
    }

    /**
     * @param  array<string, TicketCategory>  $categories
     * @return list<array{subject: string, priority: string, category: TicketCategory, fields: array<string, string|bool>, assignee: User|null, status: TicketStatus, hoursAgo: int, tags: list<string>}>
     */
    private function scenarios(array $categories, User $agent, User $priya): array
    {
        return [
            ['subject' => 'I was charged twice this month', 'priority' => 'high', 'category' => $categories['refunds'], 'fields' => ['invoice_number' => 'INV-20431'], 'assignee' => null, 'status' => TicketStatus::New, 'hoursAgo' => 3, 'tags' => ['refund']],
            ['subject' => 'Cannot log in after password reset', 'priority' => 'urgent', 'category' => $categories['sign_in'], 'fields' => [], 'assignee' => $agent, 'status' => TicketStatus::Open, 'hoursAgo' => 30, 'tags' => ['login']],
            ['subject' => 'How do I export my invoices?', 'priority' => 'low', 'category' => $categories['invoices'], 'fields' => ['invoice_number' => 'INV-19870'], 'assignee' => $priya, 'status' => TicketStatus::Pending, 'hoursAgo' => 50, 'tags' => []],
            ['subject' => 'Webhook deliveries failing with 500', 'priority' => 'high', 'category' => $categories['integrations'], 'fields' => ['device' => 'Web browser', 'steps_to_reproduce' => 'Send a test delivery from the admin center.'], 'assignee' => null, 'status' => TicketStatus::New, 'hoursAgo' => 12, 'tags' => ['webhooks']],
            ['subject' => 'Feature request: dark mode for the mobile app', 'priority' => 'low', 'category' => $categories['mobile'], 'fields' => ['device' => 'iPhone / iPad'], 'assignee' => $agent, 'status' => TicketStatus::OnHold, 'hoursAgo' => 120, 'tags' => ['feature_request']],
            ['subject' => 'Add a new admin to our account', 'priority' => 'normal', 'category' => $categories['team'], 'fields' => [], 'assignee' => $agent, 'status' => TicketStatus::Solved, 'hoursAgo' => 200, 'tags' => []],
            ['subject' => 'App crashes when uploading a photo', 'priority' => 'normal', 'category' => $categories['mobile'], 'fields' => ['device' => 'Android', 'steps_to_reproduce' => 'Open a ticket, tap Attach, pick a large photo.'], 'assignee' => null, 'status' => TicketStatus::New, 'hoursAgo' => 1, 'tags' => []],
            ['subject' => 'Update the billing address on invoices', 'priority' => 'normal', 'category' => $categories['invoices'], 'fields' => ['invoice_number' => 'INV-20112'], 'assignee' => $priya, 'status' => TicketStatus::Open, 'hoursAgo' => 26, 'tags' => []],
        ];
    }

    /**
     * Fields, forms, categories and routing rules.
     *
     * @return array<string, TicketCategory>
     */
    private function seedTicketSetup(Group $support, Group $billing): array
    {
        $field = fn (string $key, string $label, TicketFieldType $type, ?array $options = null, bool $customers = true): TicketField => TicketField::query()->create([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'options' => $options,
            'is_visible_to_customers' => $customers,
        ]);

        $invoiceNumber = $field('invoice_number', 'Invoice number', TicketFieldType::Text);
        $device = $field('device', 'Device', TicketFieldType::Select, ['Web browser', 'iPhone / iPad', 'Android']);
        $steps = $field('steps_to_reproduce', 'Steps to reproduce', TicketFieldType::Textarea);
        $refundApproved = $field('refund_approved', 'Refund approved', TicketFieldType::Checkbox, customers: false);

        TicketForm::query()->create(['name' => 'General', 'is_default' => true]);

        $billingForm = TicketForm::query()->create(['name' => 'Billing']);
        $billingForm->syncFields([['id' => $invoiceNumber->id, 'is_required' => true], ['id' => $refundApproved->id, 'is_required' => false]]);

        $bugForm = TicketForm::query()->create(['name' => 'Bug report']);
        $bugForm->syncFields([['id' => $device->id, 'is_required' => true], ['id' => $steps->id, 'is_required' => false]]);

        $category = fn (string $name, string $description, ?TicketCategory $parent = null, ?TicketForm $form = null): TicketCategory => TicketCategory::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'description' => $description,
            'ticket_form_id' => $form?->id,
            'position' => TicketCategory::query()->where('parent_id', $parent?->id)->count(),
        ]);

        $billingCategory = $category('Billing & payments', 'Invoices, charges and refunds', form: $billingForm);
        $account = $category('My account', 'Signing in and managing your team');
        $technical = $category('Technical problem', 'Something is not working as expected', form: $bugForm);

        $categories = [
            'invoices' => $category('Invoices', 'Download or correct an invoice', $billingCategory),
            'refunds' => $category('Refunds', 'Duplicate or unexpected charges', $billingCategory),
            'sign_in' => $category('Signing in', 'Passwords, two-factor and passkeys', $account),
            'team' => $category('Team members', 'Invite or remove people', $account),
            'mobile' => $category('Mobile app', 'The iOS and Android apps', $technical),
            'integrations' => $category('Integrations & API', 'Webhooks, API tokens and connected apps', $technical),
        ];

        RoutingRule::query()->create([
            'name' => 'Refunds are urgent for Billing',
            'conditions' => [['field' => 'category', 'operator' => 'is', 'value' => (string) $categories['refunds']->id]],
            'actions' => ['group_id' => $billing->id, 'priority' => 'high', 'tags' => ['billing']],
            'position' => 0,
        ]);
        RoutingRule::query()->create([
            'name' => 'Billing questions',
            'conditions' => [['field' => 'category', 'operator' => 'is', 'value' => (string) $billingCategory->id]],
            'actions' => ['group_id' => $billing->id, 'priority' => null, 'tags' => ['billing']],
            'position' => 1,
        ]);
        RoutingRule::query()->create([
            'name' => 'Everything else goes to Support',
            'conditions' => [],
            'actions' => ['group_id' => $support->id, 'priority' => null, 'tags' => []],
            'position' => 2,
        ]);

        return $categories;
    }

    /**
     * A mailbox, canned responses, saved views, CCs, a merged duplicate and round-robin assignment.
     *
     * @param  Collection<int, User>  $customers
     */
    private function seedAgentTools(User $admin, User $agent, Group $support, Collection $customers, CreateTicket $createTicket, MergeTickets $mergeTickets): void
    {
        Mailbox::query()->create([
            'name' => 'KiteDesk Support',
            'address' => 'support@example.com',
            'is_default' => true,
            'driver' => MailboxDriver::Postmark,
            'default_group_id' => $support->id,
            'inbound_secret' => Str::random(32),
        ]);

        CannedResponse::query()->create([
            'title' => 'Looking into it',
            'body' => '<p>Hi {{requester.first_name}},</p><p>Thanks for reaching out about ticket #{{ticket.id}}. I\'m looking into it and will get back to you shortly.</p><p>{{agent.name}}</p>',
            'is_shared' => true,
        ]);
        CannedResponse::query()->create([
            'title' => 'Password reset steps',
            'body' => '<p>Hi {{requester.first_name}},</p><p>You can reset your password from the sign-in page: choose <strong>Forgot password</strong> and follow the link we email you.</p>',
            'is_shared' => true,
        ]);
        CannedResponse::query()->create([
            'title' => 'Closing for now',
            'body' => '<p>I\'ll mark this as solved for now. Just reply if anything else comes up!</p>',
            'user_id' => $agent->id,
        ]);

        (new SavedView(['name' => 'Urgent unassigned', 'is_shared' => true, 'filters' => ['view' => 'unassigned', 'priority' => 'urgent'], 'position' => 0]))
            ->forceFill(['user_id' => $admin->id])
            ->save();
        (new SavedView(['name' => 'My high priority', 'filters' => ['view' => 'all', 'assignee_id' => 'me', 'priority' => 'high'], 'position' => 0]))
            ->forceFill(['user_id' => $agent->id])
            ->save();

        // Example workflows, left off so the demo data doesn't change on its own.
        foreach (['auto_close_pending', 'escalate_urgent', 'nudge_customer'] as $key) {
            $template = WorkflowTemplates::get($key);
            Workflow::query()->create([
                'name' => $template['name'],
                'description' => $template['description'],
                'is_active' => false,
                'trigger' => GraphValidator::triggerOf($template['graph']),
                'graph' => $template['graph'],
                'created_by' => $admin->id,
            ]);
        }

        [$first, $second] = [$customers[1], $customers[2]];
        $original = Ticket::query()->where('subject', 'I was charged twice this month')->firstOrFail();
        $original->collaborators()->attach($second->id);

        $duplicate = $createTicket->handle($first, [
            'subject' => 'Double charge on my card',
            'body' => '<p>I see two charges for the same invoice.</p>',
            'category_id' => $original->category_id,
            'custom_fields' => $original->custom_fields ?? [],
        ], TicketChannel::Email);
        $mergeTickets->handle($duplicate, $original, $agent);

        Ticket::query()->where('subject', 'App crashes when uploading a photo')->firstOrFail()
            ->linkTo(Ticket::query()->where('subject', 'Feature request: dark mode for the mobile app')->firstOrFail());

        $guest = User::factory()->unverified()->create(['name' => 'Gabi Guest', 'email' => 'guest@example.org']);
        $createTicket->handle($guest, [
            'subject' => 'Question about your pricing',
            'body' => '<p>Do you offer discounts for non-profits?</p>',
            'category_id' => TicketCategory::query()->where('name', 'Invoices')->value('id'),
            'custom_fields' => ['invoice_number' => 'N/A'],
        ], TicketChannel::Portal);

        // Set after the demo tickets so they keep their hand-picked assignees.
        $support->update(['assignment_mode' => AssignmentMode::RoundRobin]);
    }

    private function staff(string $name, string $email, string $role): User
    {
        return User::factory()->withRole($role)->create(['name' => $name, 'email' => $email]);
    }

    private function seedHelpCenter(User $author): void
    {
        $catalog = [
            'Getting started' => ['Set up your account' => ['Creating your account', 'Inviting your team', 'Configuring notifications']],
            'Billing & plans' => ['Invoices' => ['Downloading invoices', 'Updating your billing address', 'Requesting a refund']],
            'Account & security' => ['Sign in' => ['Resetting your password', 'Enabling two-factor authentication', 'Signing in with a passkey']],
            'Integrations' => ['Webhooks & API' => ['Creating an API token', 'Verifying webhook signatures', 'Rate limits']],
        ];

        $position = 0;

        foreach ($catalog as $categoryName => $sections) {
            $category = Category::query()->create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'description' => fake()->sentence(10),
                'position' => $position++,
            ]);

            foreach ($sections as $sectionName => $articles) {
                $section = $category->sections()->create(['name' => $sectionName, 'slug' => Str::slug($sectionName)]);

                foreach ($articles as $articlePosition => $title) {
                    $section->articles()->create([
                        'author_id' => $author->id,
                        'title' => $title,
                        'slug' => Str::slug($title),
                        'excerpt' => fake()->sentence(14),
                        'body' => '<p>'.fake()->paragraph(4).'</p><h2>Step by step</h2><ol><li>'.fake()->sentence().'</li><li>'.fake()->sentence().'</li><li>'.fake()->sentence().'</li></ol><p>'.fake()->paragraph(3).'</p>',
                        'status' => ArticleStatus::Published,
                        'published_at' => now()->subDays(10),
                        'position' => $articlePosition,
                    ]);
                }
            }
        }
    }
}
