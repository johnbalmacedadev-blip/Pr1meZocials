<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    public const TEAM_KEY = 'team';

    protected $fillable = ['key', 'kind', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')
            ->withPivot('last_read_message_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public static function directKey(int $a, int $b): string
    {
        return 'direct:'.min($a, $b).':'.max($a, $b);
    }

    /**
     * The single group chat every active team member belongs to.
     */
    public static function team(): self
    {
        return static::query()->firstOrCreate(['key' => self::TEAM_KEY], ['kind' => 'team']);
    }

    public function isTeam(): bool
    {
        return $this->kind === 'team';
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * Adds an active user to the team chat on first visit; older history starts out read.
     */
    public function ensureTeamMember(User $user): void
    {
        if (! $this->isTeam() || ! $user->is_active || $this->hasParticipant($user)) {
            return;
        }

        $this->participants()->attach($user->id, ['last_read_message_id' => $this->messages()->max('id')]);
    }
}
