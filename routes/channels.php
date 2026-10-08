<?php

use App\Domain\Support\Broadcasting\Channels;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Every channel name starts with the installation's scope (see Channels), checked first.
 */
Broadcast::channel(Channels::pattern('App.Models.User.{id}'), function (User $user, string $scope, int $id) {
    return Channels::matches($scope) && $user->id === $id;
});

/*
 * Staff-wide ticket activity (queues, dashboards).
 */
Broadcast::channel(Channels::pattern('staff.tickets'), function (User $user, string $scope) {
    return Channels::matches($scope) && $user->isStaff();
});

/*
 * Presence channel for a single ticket in the agent workspace: powers collision detection
 * ("Ana is viewing / replying") and internal-note activity.
 */
Broadcast::channel(Channels::pattern('staff.tickets.{ticket}'), function (User $user, string $scope, Ticket $ticket) {
    return Channels::matches($scope) && $user->isStaff() && $ticket->isVisibleTo($user) ? ['id' => $user->id, 'name' => $user->name] : false;
});

/*
 * Public activity on a single ticket, for the requester's portal view.
 */
Broadcast::channel(Channels::pattern('tickets.{ticket}'), function (User $user, string $scope, Ticket $ticket) {
    return Channels::matches($scope) && $user->can('view', $ticket);
});
