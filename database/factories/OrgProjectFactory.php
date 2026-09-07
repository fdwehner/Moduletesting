<?php

namespace Database\Factories;

use App\Models\OrgProject;
use App\Models\User;
use App\Support\OrgDesigner\OrgDesignerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgProject>
 */
class OrgProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Org Designer',
            'state' => OrgDesignerDocument::defaultState(),
            'config' => OrgDesignerDocument::defaultConfig(),
            'lock_version' => 1,
        ];
    }
}
