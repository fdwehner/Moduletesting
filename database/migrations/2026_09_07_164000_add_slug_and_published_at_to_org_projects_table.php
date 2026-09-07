<?php

use App\Models\OrgProject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL uses org_projects_user_id_unique as the index for the users
        // foreign key, so DROP INDEX fails until the FK is removed.
        Schema::table('org_projects', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('org_projects', function (Blueprint $table) {
            $table->dropUnique('org_projects_user_id_unique');
        });

        Schema::table('org_projects', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('slug')->nullable()->after('name');
            $table->timestamp('published_at')->nullable()->after('lock_version');
            $table->index(['user_id', 'updated_at']);
            $table->index('published_at');
        });

        OrgProject::query()->orderBy('id')->each(function (OrgProject $project): void {
            if (filled($project->slug)) {
                return;
            }

            $project->forceFill([
                'slug' => OrgProject::uniqueSlug($project->name ?: 'org-chart'),
            ])->save();
        });

        Schema::table('org_projects', function (Blueprint $table) {
            $table->unique('slug');
        });
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
};
