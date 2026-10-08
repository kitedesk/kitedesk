<?php

namespace App\Domain\Ai\Mcp;

use App\Domain\Ai\Mcp\Tools\GetArticleTool;
use App\Domain\Ai\Mcp\Tools\GetTicketTool;
use App\Domain\Ai\Mcp\Tools\ListTeamTool;
use App\Domain\Ai\Mcp\Tools\ReplyToTicketTool;
use App\Domain\Ai\Mcp\Tools\SearchArticlesTool;
use App\Domain\Ai\Mcp\Tools\SearchTicketsTool;
use App\Domain\Ai\Mcp\Tools\UpdateTicketTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * KiteDesk for AI clients: everything runs as the signed-in staff member, so their role and
 * ticket access apply, and an API token's abilities hide the tools it can't use.
 */
#[Name('KiteDesk')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    KiteDesk is a customer support helpdesk. You act as the signed-in support agent.
    Tickets are referenced like #1042. Status categories: new, open, pending (waiting on the customer), on_hold (waiting on a third party), solved, closed.
    Conversations include internal notes, which customers never see: don't quote them in public replies.
    Public replies are emailed to the customer, so confirm the wording with the user before sending one.
    Prefer linking help center articles (search_articles) over rewriting them.
    TEXT)]
class KiteDeskServer extends Server
{
    protected array $tools = [
        SearchTicketsTool::class,
        GetTicketTool::class,
        ReplyToTicketTool::class,
        UpdateTicketTool::class,
        ListTeamTool::class,
        SearchArticlesTool::class,
        GetArticleTool::class,
    ];
}
