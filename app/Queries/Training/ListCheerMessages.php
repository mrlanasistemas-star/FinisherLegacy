<?php

namespace App\Queries\Training;

use App\Models\CheerMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The athlete's training playlist (Entrenamientos): visible cheers, newest
 * first, filtered by all | unheard | favorites. Shared by the web page and
 * GET /api/v1/training/messages.
 */
class ListCheerMessages
{
    public const FILTERS = ['all', 'unheard', 'favorites'];

    /**
     * @return LengthAwarePaginator<int, CheerMessage>
     */
    public function paginate(User $user, string $filter = 'all', int $perPage = 20): LengthAwarePaginator
    {
        $query = CheerMessage::query()
            ->visibleFor($user)
            ->with(['link:id,uuid,title', 'audio'])
            ->latest('created_at')
            ->latest('id');

        match ($filter) {
            'unheard' => $query->whereNull('listened_at'),
            'favorites' => $query->where('favorite', true),
            default => null,
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function unheardCount(User $user): int
    {
        return CheerMessage::query()->visibleFor($user)->whereNull('listened_at')->count();
    }

    public function favoritesCount(User $user): int
    {
        return CheerMessage::query()->visibleFor($user)->where('favorite', true)->count();
    }

    public function totalCount(User $user): int
    {
        return CheerMessage::query()->visibleFor($user)->count();
    }
}
