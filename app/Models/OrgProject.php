<?php

namespace App\Models;

use App\Support\OrgDesigner\OrgDesignerDocument;
use Database\Factories\OrgProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'name', 'state', 'config', 'lock_version'])]
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
        ];
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

    public static function firstOrCreateForUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Org Designer',
                'state' => OrgDesignerDocument::defaultState(),
                'config' => OrgDesignerDocument::defaultConfig(),
                'lock_version' => 1,
            ]
        );
    }
}
