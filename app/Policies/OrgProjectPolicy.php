<?php

namespace App\Policies;

use App\Models\OrgProject;
use App\Models\User;

class OrgProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, OrgProject $project): bool
    {
        return $this->owns($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, OrgProject $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, OrgProject $project): bool
    {
        return $this->owns($user, $project);
    }

    public function export(User $user, OrgProject $project): bool
    {
        return $this->view($user, $project);
    }

    public function import(User $user, OrgProject $project): bool
    {
        return $this->update($user, $project);
    }

    private function owns(User $user, OrgProject $project): bool
    {
        return (int) $user->id === (int) $project->user_id;
    }
}
