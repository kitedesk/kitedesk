<?php

namespace App\Domain\Tickets\Policies;

use App\Domain\Tickets\Models\SavedView;
use App\Models\User;

class SavedViewPolicy
{
    /**
     * Rename, reorder or delete: owners manage personal views, roles allowed to share manage shared ones.
     */
    public function update(User $user, SavedView $view): bool
    {
        return $view->isManageableBy($user);
    }

    public function delete(User $user, SavedView $view): bool
    {
        return $view->isManageableBy($user);
    }
}
