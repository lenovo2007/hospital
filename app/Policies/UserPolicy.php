<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determina si el usuario autenticado puede ver a otro usuario.
     */
    public function view(User $actor, User $target): bool
    {
        if ($target->is_root && !$actor->is_root) {
            return false;
        }
        return true;
    }

    /**
     * Determina si el usuario autenticado puede actualizar a otro usuario.
     */
    public function update(User $actor, User $target): bool
    {
        if ($target->is_root && !$actor->is_root) {
            return false;
        }
        return true;
    }

    /**
     * Determina si el usuario autenticado puede eliminar a otro usuario.
     */
    public function delete(User $actor, User $target): bool
    {
        if ($target->is_root && !$actor->is_root) {
            return false;
        }
        return true;
    }
}
