<?php

namespace App\Http\Controllers\Admin\LegacyNfc;

use App\Actions\Nfc\AssignNfcTag;
use App\Actions\Nfc\GenerateNfcTags;
use App\Actions\Nfc\SetNfcTagStatus;
use App\Enums\NfcTagStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nfc\GenerateNfcTagsRequest;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Support\Nfc\NfcToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Legacy + NFC → "Etiquetas NFC": list/filter, bulk generate for
 * the encoder (CSV of URLs), disable/re-enable, see assignment + last tap.
 * The chip's hardware UID is never shown.
 */
class NfcTagController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(NfcTagStatus::class)],
            'batch' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:300'],
        ]);

        $tags = $this->filtered($filters)
            ->with(['activeAssignment.plate', 'activeAssignment.product', 'activeAssignment.event', 'activeAssignment.athlete'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (NfcTag $tag) => $this->row($tag));

        return Inertia::render('admin/legacy-nfc/Tags', [
            'tags' => $tags,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'batch' => $filters['batch'] ?? null,
                'q' => $filters['q'] ?? null,
            ],
            'batches' => NfcTag::query()->whereNotNull('batch_code')->select('batch_code')->distinct()->orderByDesc('batch_code')->limit(50)->pluck('batch_code'),
            'counts' => collect(NfcTagStatus::cases())->mapWithKeys(fn (NfcTagStatus $s) => [
                $s->value => NfcTag::query()->where('status', $s)->count(),
            ]),
            'maxBatch' => (int) config('nfc.max_batch', 500),
            'can' => ['manage' => (bool) $request->user()->can('nfc.manage')],
        ]);
    }

    public function generate(GenerateNfcTagsRequest $request, GenerateNfcTags $generate): RedirectResponse
    {
        $tags = $generate->handle($request->integer('count'), $request->user(), $request->input('batch_code'));
        $batch = $tags->first()?->batch_code;

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se generaron {$tags->count()} etiquetas en el lote {$batch}."]);

        return to_route('admin.legacy-nfc.tags.index', ['batch' => $batch]);
    }

    /**
     * Encoder export: one URL per row — what gets written to each chip.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(NfcTagStatus::class)],
            'batch' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:300'],
        ]);

        activity()->causedBy($request->user())->withProperties($filters)->log('nfc.tags.exported');

        $query = $this->filtered($filters)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'url', 'lote', 'estado']);

            $query->chunkById(500, function ($tags) use ($out) {
                foreach ($tags as $tag) {
                    fputcsv($out, [$tag->id, NfcToken::url($tag->token), $tag->batch_code, $tag->status->label()]);
                }
            });

            fclose($out);
        }, 'etiquetas-nfc-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function disable(Request $request, NfcTag $tag, SetNfcTagStatus $status): RedirectResponse
    {
        $status->disable($tag, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiqueta desactivada.']);

        return back();
    }

    public function enable(Request $request, NfcTag $tag, SetNfcTagStatus $status): RedirectResponse
    {
        $status->enable($tag, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiqueta reactivada.']);

        return back();
    }

    public function release(Request $request, NfcTag $tag, AssignNfcTag $assign): RedirectResponse
    {
        $assignment = NfcAssignment::query()->open()->where('nfc_tag_id', $tag->id)->first();

        if ($assignment !== null) {
            $assign->release($assignment, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La etiqueta volvió a estar disponible.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<NfcTag>
     */
    private function filtered(array $filters): Builder
    {
        $token = isset($filters['q']) ? NfcToken::extract((string) $filters['q']) : null;

        return NfcTag::query()
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['batch'] ?? null, fn (Builder $q, string $b) => $q->where('batch_code', $b))
            ->when(isset($filters['q']), fn (Builder $q) => $token !== null
                ? $q->where('token', $token)
                : $q->where('token', 'like', '%'.preg_replace('/[^A-Za-z0-9]/', '', (string) $filters['q'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(NfcTag $tag): array
    {
        $assignment = $tag->activeAssignment;

        return [
            'id' => $tag->id,
            'label' => $tag->shortToken(),
            'url' => NfcToken::url($tag->token),
            'status' => $tag->status->value,
            'status_label' => $tag->status->label(),
            'batch' => $tag->batch_code,
            'tap_count' => $tag->tap_count,
            'last_tapped_at' => $tag->last_tapped_at?->toIso8601String(),
            'assignment' => $assignment !== null ? [
                'type' => $assignment->target_type->value,
                'type_label' => $assignment->target_type->label(),
                'title' => match ($assignment->target_type->value) {
                    'legacy_plate' => trim(($assignment->plate?->engraving_display_name ?: $assignment->plate?->athlete_name).' · '.$assignment->plate?->serial_number, ' ·'),
                    'product' => $assignment->product?->name,
                    'event' => $assignment->event?->name,
                    default => 'Perfil',
                },
                'owner' => $assignment->athlete?->full_name,
                'connected' => $assignment->isClaimed(),
                'connected_at' => $assignment->activated_at?->toIso8601String(),
            ] : null,
        ];
    }
}
