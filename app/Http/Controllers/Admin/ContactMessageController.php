<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Mensajes: what arrives through the public /contact
 * form (collaborations, suppliers, brands, photographers, support).
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $status = ContactMessageStatus::tryFrom($request->string('status')->toString());
        $type = ContactMessageType::tryFrom($request->string('type')->toString());

        $messages = ContactMessage::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($status === null, fn ($q) => $q->where('status', '!=', ContactMessageStatus::Archived))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'company' => $m->company,
                'email' => $m->email,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'message' => $m->message,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/contact-messages/Index', [
            'messages' => $messages,
            'filters' => ['status' => $status?->value, 'type' => $type?->value],
            'types' => ContactMessageType::options(),
            'counts' => [
                'new' => ContactMessage::query()->where('status', ContactMessageStatus::New)->count(),
            ],
        ]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ContactMessageStatus::class)],
        ]);

        $message->update([
            'status' => $data['status'],
            'read_at' => $message->read_at ?? ($data['status'] !== ContactMessageStatus::New->value ? now() : null),
        ]);

        return back();
    }
}
