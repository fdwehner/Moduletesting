<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_projects')) {
            Schema::create('org_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name')->default('Org Designer');
                $table->json('state');
                $table->json('config');
                $table->unsignedInteger('lock_version')->default(1);
                $table->timestamps();

                $table->unique('user_id', 'org_projects_user_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_projects');
    }
};
