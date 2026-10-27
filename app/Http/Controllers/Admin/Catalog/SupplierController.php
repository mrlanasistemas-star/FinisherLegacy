<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventario → Proveedores: who manufactures the tags. Deliberately simple
 * (no purchase orders). A supplier with lots is deactivated, not deleted.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => SupplierStatus::tryFrom($request->string('status')->toString())?->value,
        ];

        $suppliers = Supplier::query()
            ->withCount('lots')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")->orWhere('contact_name', 'like', "%{$filters['q']}%")->orWhere('email', 'like', "%{$filters['q']}%")))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(24)->withQueryString()
            ->through(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'contact_name' => $s->contact_name,
                'email' => $s->email,
                'phone' => $s->phone,
                'country' => $s->country,
                'lead_time_days' => $s->lead_time_days,
                'moq' => $s->moq,
                'products_supplied' => $s->products_supplied,
                'notes' => $s->notes,
                'status' => $s->status->value,
                'lots_count' => $s->lots_count,
            ]);

        return Inertia::render('admin/inventory/suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'statuses' => SupplierStatus::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));
        activity()->causedBy($request->user())->performedOn($supplier)->log('suppliers.created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor creado.']);

        return back();
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));
        activity()->causedBy($request->user())->performedOn($supplier)->log('suppliers.updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor actualizado.']);

        return back();
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        if ($supplier->lots()->exists()) {
            $supplier->update(['status' => SupplierStatus::Inactive]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'El proveedor tiene lotes: se desactivó en lugar de eliminarse.']);

            return back();
        }

        activity()->causedBy($request->user())->withProperties(['name' => $supplier->name])->log('suppliers.deleted');
        $supplier->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor eliminado.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'moq' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'products_supplied' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(SupplierStatus::class)],
        ]);
    }
}
