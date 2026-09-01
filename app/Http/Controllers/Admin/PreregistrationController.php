<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PreregistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\EventPreregistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreregistrationController extends Controller
{
    public function index(Request $request): Response
    {
        // Every filter below runs in SQL before paginate() — never on the
        // already-paginated ->data collection. Filtering the paged
        // collection instead is the exact bug the "PAGO PENDIENTE" report
        // described (paginator says 2 pages, 0 rows shown): it doesn't
        // reproduce against this controller's code today (there was no
        // status filter here at all until this change), but this is built
        // so that failure mode can't happen here regardless — see
        // tests/Feature/Admin/PreregistrationPaginationTest.php.
        $status = $request->string('status')->toString();

        $preregistrations = EventPreregistration::query()
            ->with(['eventEdition.event', 'eventRace'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('bib_number', 'like', "%{$search}%");
            }))
            ->when($status !== '' && PreregistrationStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $preregistrations->through(fn (EventPreregistration $preregistration) => [
            'id' => $preregistration->id,
            'name' => trim("{$preregistration->first_name} {$preregistration->last_name}"),
            'email' => $preregistration->email,
            'event' => $preregistration->eventEdition?->event?->name,
            'race' => $preregistration->eventRace?->name,
            'bib_number' => $preregistration->bib_number,
            'status' => $preregistration->status->value,
        ]);

        return Inertia::render('admin/preregistrations/Index', [
            'preregistrations' => $preregistrations,
            'statuses' => array_map(fn (PreregistrationStatus $s) => $s->value, PreregistrationStatus::cases()),
            'filters' => ['q' => $request->string('q')->toString(), 'status' => $status ?: null],
        ]);
    }
}
