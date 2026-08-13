<?php

namespace App\Policies;

use App\Models\CodeAcces;
use App\Models\User;

/**
 * Les codes d'accès sont l'outil de délégation du patron : lui seul les
 * délivre, les consulte et les révoque. Un gérant ne voit jamais la liste —
 * il reçoit son code par un autre canal (SMS, appel) et le saisit.
 */
class CodeAccesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPatron();
    }

    public function view(User $user, CodeAcces $code): bool
    {
        return $user->isPatron() && $user->tenant_id === $code->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isPatron();
    }

    public function revoquer(User $user, CodeAcces $code): bool
    {
        return $this->view($user, $code) && $code->estValide();
    }
}
