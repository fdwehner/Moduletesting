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
        $name = fake()->unique()->words(3, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => OrgProject::uniqueSlug($name),
            'state' => OrgDesignerDocument::defaultState(),
            'config' => OrgDesignerDocument::defaultConfig(),
            'lock_version' => 1,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_at' => now(),
        ]);
    }
}
