<?php

namespace App\Models;

use App\Support\OrgDesigner\OrgDesignerDocument;
use Database\Factories\OrgProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'name', 'slug', 'state', 'config', 'lock_version', 'published_at'])]
class OrgProject extends Model
{
    /** @use HasFactory<OrgProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'config' => 'array',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrgProject $project): void {
            if (blank($project->slug)) {
                $project->slug = static::uniqueSlug($project->name ?: 'org-chart');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<OrgProject>  $query
     * @return Builder<OrgProject>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<OrgProject>  $query
     * @return Builder<OrgProject>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public static function createForUser(User $user, string $name): self
    {
        return static::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => static::uniqueSlug($name),
            'state' => OrgDesignerDocument::defaultState(),
            'config' => OrgDesignerDocument::defaultConfig(),
            'lock_version' => 1,
            'published_at' => null,
        ]);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org-chart';

        do {
            $slug = $base.'-'.Str::lower(Str::random(8));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $areas = is_array($this->state['iltAreas'] ?? null) ? $this->state['iltAreas'] : [];
        $teams = 0;
        $positions = 0;
        foreach ($areas as $area) {
            foreach ($area['teams'] ?? [] as $team) {
                $teams++;
                $positions += count($team['positions'] ?? []);
            }
        }

        return [
            'areas' => count($areas),
            'teams' => $teams,
            'positions' => $positions,
        ];
    }
}
