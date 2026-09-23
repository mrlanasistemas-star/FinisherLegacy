<?php

namespace App\Actions\Social;

use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Exceptions\SocialActionNotAllowedException;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Report;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves a public identifier (username / moment uuid / comment uuid) to
 * the internal target and records the report. Reporting the same thing
 * twice just refreshes the reason — one open report per reporter/target.
 * You can only report what you can see.
 */
class CreateReport
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    public function handle(User $reporter, ReportTargetType $type, string $identifier, string $reason, ?string $details): Report
    {
        [$targetId, $ownerId] = match ($type) {
            ReportTargetType::Profile => $this->resolveProfile($identifier, $reporter),
            ReportTargetType::Moment => $this->resolveMoment($identifier, $reporter),
            ReportTargetType::Comment => $this->resolveComment($identifier, $reporter),
        };

        if ($ownerId === $reporter->id) {
            throw new SocialActionNotAllowedException('No puedes reportar tu propio contenido.');
        }

        return Report::query()->updateOrCreate(
            ['reporter_id' => $reporter->id, 'target_type' => $type, 'target_id' => $targetId],
            ['reason' => $reason, 'details' => $details, 'status' => ReportStatus::Open],
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveProfile(string $username, User $reporter): array
    {
        $profile = AthleteProfile::query()->where('username', $username)->first();

        if ($profile === null || ! $this->visibility->canViewProfile($profile, $reporter)) {
            throw (new ModelNotFoundException)->setModel(AthleteProfile::class);
        }

        return [$profile->id, $profile->user_id];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveMoment(string $uuid, User $reporter): array
    {
        $moment = LegacyMoment::query()->with('author.athleteProfile')->where('uuid', $uuid)->first();

        if ($moment === null || ! $this->visibility->canViewMoment($moment, $reporter)) {
            throw (new ModelNotFoundException)->setModel(LegacyMoment::class);
        }

        return [$moment->id, $moment->user_id];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveComment(string $uuid, User $reporter): array
    {
        $comment = LegacyMomentComment::query()->with('moment.author.athleteProfile')->where('uuid', $uuid)->first();

        if ($comment === null || $comment->moment === null || ! $this->visibility->canViewMoment($comment->moment, $reporter)) {
            throw (new ModelNotFoundException)->setModel(LegacyMomentComment::class);
        }

        return [$comment->id, $comment->user_id];
    }
}
