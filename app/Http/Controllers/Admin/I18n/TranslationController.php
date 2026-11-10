<?php

namespace App\Http\Controllers\Admin\I18n;

use App\Http\Controllers\Controller;
use App\Jobs\I18n\RunTranslationSync;
use App\Models\I18nSyncRun;
use App\Models\ModelTranslation;
use App\Models\TranslationString;
use App\Services\I18n\CatalogBuilder;
use App\Services\I18n\MachineTranslation;
use App\Support\I18n\CatalogCoverage;
use App\Support\I18n\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin › Configuración › Idiomas y traducciones (`/admin/idiomas`,
 * permission `i18n.manage`; docs/renovacion/idiomas.md §Panel).
 *
 * Languages with their coverage, every string searchable and editable (an
 * edit marks it `reviewed`: the machine never touches it again), the
 * translations of admin-entered content, the public on/off switch of each
 * language and the "Sincronizar y traducir ahora" button (queued).
 */
class TranslationController extends Controller
{
    private const array STATUS_FILTERS = ['machine', 'stale', 'missing', 'reviewed', 'needs_review'];

    public function index(Request $request, CatalogCoverage $coverage, MachineTranslation $machine): Response
    {
        $targets = Locales::targets();

        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['strings', 'content'])],
            'locale' => ['nullable', Rule::in($targets)],
            'group' => ['nullable', Rule::in(TranslationString::GROUPS)],
            'status' => ['nullable', Rule::in(self::STATUS_FILTERS)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $tab = $filters['tab'] ?? 'strings';
        $locale = $filters['locale'] ?? ($targets[0] ?? null);
        $status = $filters['status'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Inertia::render('admin/translations/Index', [
            'locales' => $coverage->summary(),
            'filters' => [
                'tab' => $tab,
                'locale' => $locale,
                'group' => $filters['group'] ?? null,
                'status' => $status,
                'q' => $search,
            ],
            'groups' => TranslationString::GROUPS,
            'translator' => [
                'driver' => $machine->driver(),
                'available' => $machine->available(),
                'model' => $machine->driver() === 'anthropic' ? config('i18n.anthropic.model') : null,
            ],
            'runs' => I18nSyncRun::query()->latest('id')->limit(6)->get()->map(fn (I18nSyncRun $run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'trigger' => $run->trigger,
                'stats' => $run->stats,
                'model' => $run->model,
                'input_tokens' => $run->input_tokens,
                'output_tokens' => $run->output_tokens,
                'cache_read_tokens' => $run->cache_read_tokens,
                'cache_write_tokens' => $run->cache_write_tokens,
                'estimated_cost_usd' => $run->estimated_cost_usd !== null ? (float) $run->estimated_cost_usd : null,
                'error' => $run->error,
                'created_at' => $run->created_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])->all(),
            'strings' => $tab !== 'strings' || $locale === null ? null : TranslationString::query()
                ->where('locale', $locale)
                ->whereNull('obsolete_at')
                ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('group', $group))
                ->when($status === 'needs_review', fn ($query) => $query->where('needs_review', true))
                ->when($status !== null && $status !== 'needs_review', fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('source_text', 'like', $like)
                    ->orWhere('translated_text', 'like', $like)))
                ->orderBy('source_text')
                ->paginate(40)
                ->withQueryString()
                ->through(fn (TranslationString $string): array => [
                    'id' => $string->id,
                    'group' => $string->group,
                    'key' => $string->key,
                    'source_text' => $string->source_text,
                    'translated_text' => $string->translated_text,
                    'status' => $string->status,
                    'needs_review' => $string->needs_review,
                    'origins' => $string->origins ?? [],
                ]),
            'content' => $tab !== 'content' || $locale === null ? null : ModelTranslation::query()
                ->with('model')
                ->where('locale', $locale)
                ->when($status !== null && $status !== 'missing', fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where('value', 'like', $like))
                ->orderBy('model_type')->orderBy('model_id')->orderBy('field')
                ->paginate(40)
                ->withQueryString()
                ->through(fn (ModelTranslation $row): array => [
                    'id' => $row->id,
                    'model' => class_basename($row->model_type),
                    'model_id' => $row->model_id,
                    'field' => $row->field,
                    'source_text' => is_string($source = $row->model?->getAttribute($row->field)) ? $source : null,
                    'value' => $row->value,
                    'status' => $row->status,
                ]),
        ]);
    }

    public function update(Request $request, TranslationString $translationString, CatalogBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'translated_text' => ['required', 'string', 'max:20000'],
        ]);

        $translationString->update([
            'translated_text' => $data['translated_text'],
            'status' => TranslationString::REVIEWED,
            'needs_review' => false,
            'translated_at' => now(),
            'reviewed_by' => $request->user()?->getAuthIdentifier(),
        ]);

        // Live immediately: the files are rebuilt from the table.
        $builder->build([$translationString->locale]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Traducción guardada. La traducción automática ya no la cambiará.']);

        return back();
    }

    public function updateContent(Request $request, ModelTranslation $modelTranslation): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:20000'],
        ]);

        $modelTranslation->update(['value' => $data['value'], 'status' => ModelTranslation::REVIEWED]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Traducción guardada. La traducción automática ya no la cambiará.']);

        return back();
    }

    public function toggleLocale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, Locales::targets(), true), 404);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        Locales::setEnabled($locale, (bool) $data['enabled']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['enabled'] ? 'Idioma visible para el público.' : 'Idioma oculto: el sitio y la app dejan de ofrecerlo.',
        ]);

        return back();
    }

    public function sync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locales' => ['nullable', 'array'],
            'locales.*' => [Rule::in(Locales::targets())],
        ]);

        $busy = I18nSyncRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '>', now()->subMinutes(30))
            ->exists();

        if ($busy) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Ya hay una sincronización en curso.']);

            return back();
        }

        $run = I18nSyncRun::query()->create([
            'trigger' => 'admin',
            'status' => 'queued',
            'user_id' => $request->user()?->getAuthIdentifier(),
            'locales' => array_values($data['locales'] ?? []),
        ]);

        RunTranslationSync::dispatch($run->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sincronización iniciada. Los resultados aparecen aquí al terminar.']);

        return back();
    }
}
