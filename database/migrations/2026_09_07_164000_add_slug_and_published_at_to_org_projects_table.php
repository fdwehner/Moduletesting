<?php

use App\Models\OrgProject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Default lock_wait_timeout is ~1 year; DROP INDEX would wait until
            // Laravel Cloud kills the deploy (~10 minutes) if the live app holds a metadata lock.
            DB::statement('SET SESSION lock_wait_timeout = 15');
        }

        // MySQL uses the unique index as the users FK index. Add a non-unique
        // index first so the FK can switch to it — do not drop the FK (that waits
        // for an exclusive metadata lock on a live environment).
        if ($this->hasIndex('org_projects_user_id_unique') && ! $this->hasIndex('org_projects_user_id_index')) {
            Schema::table('org_projects', function (Blueprint $table) {
                $table->index('user_id', 'org_projects_user_id_index');
            });
        }

        if ($this->hasIndex('org_projects_user_id_unique')) {
            Schema::table('org_projects', function (Blueprint $table) {
                $table->dropUnique('org_projects_user_id_unique');
            });
        }

        if (! Schema::hasColumn('org_projects', 'slug')) {
            Schema::table('org_projects', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
                $table->timestamp('published_at')->nullable()->after('lock_version');
                $table->index(['user_id', 'updated_at']);
                $table->index('published_at');
            });
        }

        OrgProject::query()->orderBy('id')->each(function (OrgProject $project): void {
            if (filled($project->slug)) {
                return;
            }

            $project->forceFill([
                'slug' => OrgProject::uniqueSlug($project->name ?: 'org-chart'),
            ])->save();
        });

        if (! $this->hasIndex('org_projects_slug_unique')) {
            Schema::table('org_projects', function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        Schema::table('org_projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropIndex(['published_at']);
            $table->dropColumn(['slug', 'published_at']);
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('org_projects'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === $name);
    }
};
