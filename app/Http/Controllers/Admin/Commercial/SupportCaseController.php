<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Actions\LegacyTags\ManageTag;
use App\Actions\SupportCases\OpenSupportCase;
use App\Enums\LegacyTagStatus;
use App\Enums\SupportCaseStatus;
use App\Enums\SupportCaseType;
use App\Http\Controllers\Controller;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\LegacyTagEvent;
use App\Models\Order;
use App\Models\SupportCase;
use App\Models\User;
use App\Support\LegacyTags\TagCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

/**
 * Soporte → Incidencias: activation help, NFC/QR failures, defects,
 * returns, replacements and ownership disputes. Tag operations always go
 * through App\Actions\LegacyTags\ManageTag (locked, audited, unit history);
 * refunds are only RECORDED here (refund_minor) — no payment provider call.
 */
class SupportCaseController extends Controller
{
    public const PRIORITIES = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];

    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'type' => SupportCaseType::tryFrom((string) $request->query('type'))?->value,
            'status' => SupportCaseStatus::tryFrom((string) $request->query('status'))?->value,
        ];

        $cases = SupportCase::query()
            ->with(['tag:id,uuid,serial,public_code', 'order:id,uuid,order_number', 'ally:id,name', 'assignee:id,first_name,last_name'])
            ->when($filters['q'], fn (Builder $q, string $term) => $this->search($q, $term))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("case when status in ('open', 'in_progress') then 0 else 1 end")
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $openCounts = SupportCase::query()->toBase()
            ->whereIn('status', [SupportCaseStatus::Open->value, SupportCaseStatus::InProgress->value])
            ->groupBy('status')->pluck(DB::raw('count(*)'), 'status');

        return Inertia::render('admin/support/Index', [
            'cases' => $cases->through(fn (SupportCase $c) => [
                'uuid' => $c->uuid,
                'number' => $c->number,
                'subject' => $c->subject,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'priority' => $c->priority,
                'priority_label' => self::PRIORITIES[$c->priority] ?? $c->priority,
                'tag' => $c->tag ? ['uuid' => $c->tag->uuid, 'display_code' => $c->tag->displayCode(), 'serial' => $c->tag->serial] : null,
                'order' => $c->order ? ['uuid' => $c->order->uuid, 'number' => $c->order->order_number] : null,
                'ally' => $c->ally?->name,
                'assignee' => $c->assignee?->name,
                'contact_email' => $c->contact_email,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'counts' => [
                'open' => (int) ($openCounts[SupportCaseStatus::Open->value] ?? 0),
                'in_progress' => (int) ($openCounts[SupportCaseStatus::InProgress->value] ?? 0),
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function show(Request $request, SupportCase $supportCase): Response
    {
        $supportCase->load([
            'tag.productVariant.product', 'tag.owner', 'tag.location:id,name',
            'replacementTag', 'order', 'ally:id,uuid,name', 'assignee:id,first_name,last_name', 'reporter:id,first_name,last_name,email',
        ]);

        $tag = $supportCase->tag;

        $timeline = Activity::query()
            ->with('causer')
            ->where('subject_type', $supportCase->getMorphClass())
            ->where('subject_id', $supportCase->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'label' => self::activityLabel($a->description),
                'details' => self::activityDetails($a),
                'causer' => $a->causer instanceof User ? $a->causer->name : 'Sistema',
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values();

        return Inertia::render('admin/support/Show', [
            'supportCase' => [
                'uuid' => $supportCase->uuid,
                'number' => $supportCase->number,
                'subject' => $supportCase->subject,
                'description' => $supportCase->description,
                'type' => $supportCase->type->value,
                'type_label' => $supportCase->type->label(),
                'status' => $supportCase->status->value,
                'status_label' => $supportCase->status->label(),
                'priority' => $supportCase->priority,
                'resolution' => $supportCase->resolution,
                'refund_minor' => $supportCase->refund_minor,
                'contact_email' => $supportCase->contact_email,
                'assigned_user_id' => $supportCase->assigned_user_id,
                'assignee' => $supportCase->assignee?->name,
                'reporter' => $supportCase->reporter?->name,
                'resolved_at' => $supportCase->resolved_at?->toIso8601String(),
                'created_at' => $supportCase->created_at?->toIso8601String(),
                'ally' => $supportCase->ally ? ['uuid' => $supportCase->ally->uuid, 'name' => $supportCase->ally->name] : null,
                'order' => $supportCase->order ? [
                    'uuid' => $supportCase->order->uuid,
                    'number' => $supportCase->order->order_number,
                    'total_minor' => (int) $supportCase->order->total_minor,
                    'currency' => $supportCase->order->currency,
                ] : null,
                'tag' => $tag ? [
                    'uuid' => $tag->uuid,
                    'serial' => $tag->serial,
                    'display_code' => $tag->displayCode(),
                    'status' => $tag->status->value,
                    'status_label' => $tag->status->label(),
                    'product' => $tag->productVariant?->product?->name,
                    'sku' => $tag->productVariant?->sku,
                    'owner' => $tag->owner?->full_name,
                    'location' => $tag->location?->name,
                    'can_replace' => ! $tag->status->isTerminal(),
                    'can_return' => $tag->status->isSold(),
                    'can_revoke' => $tag->status === LegacyTagStatus::Activated,
                ] : null,
                'replacement_tag' => $supportCase->replacementTag ? [
                    'uuid' => $supportCase->replacementTag->uuid,
                    'serial' => $supportCase->replacementTag->serial,
                    'display_code' => $supportCase->replacementTag->displayCode(),
                ] : null,
            ],
            'timeline' => $timeline,
            'tagHistory' => $tag ? LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)->latest('id')->limit(15)->get()
                ->map(fn (LegacyTagEvent $e) => [
                    'id' => $e->id,
                    'type' => $e->type->value,
                    'to_status' => $e->to_status?->value,
                    'notes' => $e->notes,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values() : [],
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request, OpenSupportCase $open): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(SupportCaseType::class)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(array_keys(self::PRIORITIES))],
            'tag_code' => ['nullable', 'string', 'max:300'],
            'order_number' => ['nullable', 'string', 'max:40'],
            'ally_id' => ['nullable', 'integer', 'exists:allies,id'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);

        $tag = $this->tagFromInput($data['tag_code'] ?? null, 'tag_code');
        $order = null;

        if (filled($data['order_number'] ?? null)) {
            $order = Order::query()->where('order_number', trim($data['order_number']))->first()
                ?? throw ValidationException::withMessages(['order_number' => 'No encontramos ese número de pedido.']);
        }

        $case = $open->handle([
            'type' => SupportCaseType::from($data['type']),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'legacy_tag_id' => $tag?->id,
            'order_id' => $order->id ?? $tag?->order_id,
            'ally_id' => $data['ally_id'] ?? $tag?->ally_id,
            'contact_email' => $data['contact_email'] ?? $order?->customer_snapshot['email'] ?? null,
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Incidencia {$case->number} creada."]);

        return redirect()->route('admin.support.show', $case);
    }

    public function update(Request $request, SupportCase $supportCase): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(SupportCaseStatus::class)],
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => [Rule::requiredIf($request->input('status') === SupportCaseStatus::Resolved->value), 'nullable', 'string', 'max:5000'],
        ], ['resolution.required' => 'Describe cómo se resolvió la incidencia.']);

        $status = SupportCaseStatus::from($data['status']);
        $closing = in_array($status, [SupportCaseStatus::Resolved, SupportCaseStatus::Closed], true);

        $supportCase->update([
            'status' => $status,
            'priority' => $data['priority'],
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'resolution' => $data['resolution'] ?? $supportCase->resolution,
            'resolved_at' => $closing ? ($supportCase->resolved_at ?? now()) : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Incidencia actualizada.']);

        return back();
    }

    public function replace(Request $request, SupportCase $supportCase, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate([
            'replacement_code' => ['required', 'string', 'max:300'],
        ]);

        $old = $this->caseTag($supportCase);
        $new = $this->tagFromInput($data['replacement_code'], 'replacement_code');

        $replacement = $this->attempt(fn () => $manage->replace($old, $new, $request->user(), "{$supportCase->number}: {$supportCase->subject}"), 'replacement_code');

        $supportCase->update([
            'replacement_tag_id' => $replacement->id,
            'status' => $supportCase->status === SupportCaseStatus::Open ? SupportCaseStatus::InProgress : $supportCase->status,
        ]);

        activity()->causedBy($request->user())->performedOn($supportCase)
            ->withProperties(['old' => $old->serial, 'new' => $replacement->serial])
            ->log('support.replaced');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pieza reemplazada por {$replacement->serial}. El recuerdo pasó a la nueva pieza."]);

        return back();
    }

    public function returnTag(Request $request, SupportCase $supportCase, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'refund_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'restock_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
        ]);

        $tag = $this->caseTag($supportCase);
        $restock = isset($data['restock_location_id']) ? InventoryLocation::query()->whereKey($data['restock_location_id'])->firstOrFail() : null;

        $this->attempt(fn () => $manage->return($tag, $request->user(), $data['reason'], $restock), 'reason');

        $supportCase->update([
            'refund_minor' => $data['refund_minor'] ?? $supportCase->refund_minor,
            'status' => $supportCase->status === SupportCaseStatus::Open ? SupportCaseStatus::InProgress : $supportCase->status,
        ]);

        activity()->causedBy($request->user())->performedOn($supportCase)
            ->withProperties(['serial' => $tag->serial, 'refund_minor' => $data['refund_minor'] ?? null, 'restocked_at' => $restock?->name])
            ->log('support.returned');

        Inertia::flash('toast', ['type' => 'success', 'message' => $restock ? "Devolución registrada; la pieza volvió a {$restock->name}." : 'Devolución registrada.']);

        return back();
    }

    public function revoke(Request $request, SupportCase $supportCase, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $tag = $this->caseTag($supportCase);
        $this->attempt(fn () => $manage->revokeOwnership($tag, $request->user(), $data['reason']), 'reason');

        activity()->causedBy($request->user())->performedOn($supportCase)
            ->withProperties(['serial' => $tag->serial, 'reason' => $data['reason']])
            ->log('support.ownership_revoked');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Propiedad revocada; la pieza puede activarse de nuevo.']);

        return back();
    }

    /** @param  Builder<SupportCase>  $query */
    private function search(Builder $query, string $term): void
    {
        $code = TagCode::normalize($term);

        $query->where(fn (Builder $w) => $w
            ->where('number', 'like', "%{$term}%")
            ->orWhere('subject', 'like', "%{$term}%")
            ->orWhere('contact_email', 'like', "%{$term}%")
            ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
            ->orWhereHas('tag', fn ($t) => $t->where('serial', 'like', "%{$term}%")
                ->when($code, fn ($c) => $c->orWhere('public_code', $code))));
    }

    private function tagFromInput(?string $input, string $field): ?LegacyTag
    {
        if (blank($input)) {
            return null;
        }

        $code = TagCode::normalize($input);
        $tag = LegacyTag::query()
            ->when($code, fn ($q) => $q->where('public_code', $code), fn ($q) => $q->where('serial', trim($input)))
            ->first();

        return $tag ?? throw ValidationException::withMessages([$field => 'No encontramos una pieza con ese código o serie.']);
    }

    private function caseTag(SupportCase $supportCase): LegacyTag
    {
        return $supportCase->tag
            ?? throw ValidationException::withMessages(['case' => 'Liga primero una pieza (tag) a esta incidencia.']);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function attempt(callable $operation, string $field): mixed
    {
        try {
            return $operation();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'types' => SupportCaseType::options(),
            'statuses' => SupportCaseStatus::options(),
            'priorities' => collect(self::PRIORITIES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'allies' => Ally::query()->orderBy('name')->get(['id', 'name']),
            'assignees' => User::query()
                ->where(fn ($q) => $q->permission('support.manage')->orWhereHas('roles', fn ($r) => $r->where('name', 'super_admin')))
                ->orderBy('first_name')->limit(100)->get(['id', 'first_name', 'last_name'])
                ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values(),
            'locations' => InventoryLocation::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('support.manage'),
        ];
    }

    private static function activityLabel(string $description): string
    {
        return match ($description) {
            'support.opened' => 'Incidencia abierta',
            'support.replaced' => 'Pieza reemplazada',
            'support.returned' => 'Devolución registrada',
            'support.ownership_revoked' => 'Propiedad revocada',
            'created' => 'Registro creado',
            'updated' => 'Incidencia actualizada',
            default => $description,
        };
    }

    /** @return list<string> */
    private static function activityDetails(Activity $activity): array
    {
        $props = $activity->properties?->toArray() ?? [];
        $new = $props['attributes'] ?? [];
        $lines = [];

        if (isset($new['status'])) {
            $lines[] = 'Estado: '.(SupportCaseStatus::tryFrom((string) $new['status'])?->label() ?? $new['status']);
        }

        if (array_key_exists('assigned_user_id', $new)) {
            $assignee = $new['assigned_user_id'] ? User::query()->whereKey($new['assigned_user_id'])->first() : null;
            $lines[] = $assignee !== null ? 'Asignada a '.$assignee->name : 'Sin asignar';
        }

        if (isset($props['old'], $props['new'])) {
            $lines[] = "{$props['old']} → {$props['new']}";
        }

        if (isset($props['refund_minor'])) {
            $lines[] = 'Reembolso registrado: $'.number_format(((int) $props['refund_minor']) / 100, 2);
        }

        if (! empty($props['restocked_at'])) {
            $lines[] = "Regresó a inventario en {$props['restocked_at']}";
        }

        if (! empty($props['reason'])) {
            $lines[] = 'Motivo: '.$props['reason'];
        }

        return $lines;
    }
}
