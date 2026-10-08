<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Ai\Mcp\KiteDeskServer;
use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Ai\Mcp\Tools\GetTicketTool;
use App\Domain\Ai\Mcp\Tools\ReplyToTicketTool;
use App\Domain\Ai\Mcp\Tools\UpdateTicketTool;
use App\Domain\Ai\Models\McpUser;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

beforeEach(function () {
    (new AiSettings(mcpEnabled: true))->save();
    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Printer on fire']);
});

/**
 * @param  array<string, mixed>  $params
 * @param  array<string, string>  $headers
 */
function mcpCall(string $method, array $params = [], array $headers = []): TestResponse
{
    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], $headers);
}

/**
 * The person signed in through an OAuth-connected MCP client.
 */
function connectedApp(User $user): McpUser
{
    return Passport::actingAs(McpUser::query()->findOrFail($user->id), ['mcp:use'], 'mcp');
}

/**
 * For calling tools directly: what ResolveMcpUser leaves behind after an OAuth sign-in.
 */
function signedInThroughOAuth(User $user): User
{
    request()->attributes->set(McpAccess::OAUTH, true);

    return $user;
}

/**
 * @return list<string>
 */
function toolNames(TestResponse $response): array
{
    return collect($response->json('result.tools'))->pluck('name')->sort()->values()->all();
}

test('the server needs a staff sign-in, and is hidden until switched on', function () {
    mcpCall('tools/list')->assertUnauthorized();

    $customerToken = User::factory()->create()->createToken('mcp', ['tickets:read'])->plainTextToken;
    mcpCall('tools/list', headers: ['Authorization' => 'Bearer '.$customerToken])->assertForbidden();

    (new AiSettings(mcpEnabled: false))->save();
    $token = $this->agent->createToken('mcp', ['tickets:read'])->plainTextToken;
    mcpCall('tools/list', headers: ['Authorization' => 'Bearer '.$token])->assertNotFound();
});

test('an API token only sees the tools its abilities allow', function () {
    $token = $this->agent->createToken('mcp', ['tickets:read'])->plainTextToken;

    $response = mcpCall('tools/list', headers: ['Authorization' => 'Bearer '.$token])->assertOk();

    expect(toolNames($response))->toBe(['get_ticket', 'search_tickets']);
});

test('an OAuth token can read and act as the person', function () {
    connectedApp($this->agent);

    $response = mcpCall('tools/list')->assertOk();
    expect(toolNames($response))->toContain('reply_to_ticket', 'update_ticket', 'search_articles');

    // Each HTTP request authenticates afresh; in a test the guard keeps the swapped-in User.
    connectedApp($this->agent);
    mcpCall('tools/call', ['name' => 'reply_to_ticket', 'arguments' => ['ticket' => $this->ticket->reference(), 'body' => "Hello\n\nWe're on it."]])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    $message = $this->ticket->messages()->latest('id')->first();
    expect($message->author_id)->toBe($this->agent->id)
        ->and($message->body)->toBe('<p>Hello</p><p>We\'re on it.</p>')
        ->and($message->is_internal)->toBeFalse();
});

test('the OAuth discovery document points clients at the authorization server', function () {
    $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJsonStructure(['resource', 'authorization_servers']);
});

test('tools respect ticket access and permissions', function () {
    $assignedOnly = User::factory()->withPermissions([Permission::ReplyToTickets], TicketAccess::Assigned)->create();
    $readOnly = User::factory()->withPermissions([], TicketAccess::All)->create();

    KiteDeskServer::actingAs(signedInThroughOAuth($assignedOnly))
        ->tool(GetTicketTool::class, ['ticket' => $this->ticket->reference()])
        ->assertHasErrors(['Ticket not found.']);

    KiteDeskServer::actingAs(signedInThroughOAuth($this->agent))
        ->tool(GetTicketTool::class, ['ticket' => $this->ticket->reference()])
        ->assertOk()
        ->assertSee('Printer on fire');

    KiteDeskServer::actingAs(signedInThroughOAuth($readOnly))
        ->tool(UpdateTicketTool::class, ['ticket' => $this->ticket->reference(), 'priority' => TicketPriority::Urgent->value])
        ->assertHasErrors(['You are not allowed to do that on this ticket.']);

    KiteDeskServer::actingAs(signedInThroughOAuth($this->agent))
        ->tool(ReplyToTicketTool::class, ['ticket' => $this->ticket->reference(), 'body' => 'Checking with the vendor.', 'internal' => true])
        ->assertOk();

    expect($this->ticket->messages()->latest('id')->first()->is_internal)->toBeTrue();
});
