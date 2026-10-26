<?php

namespace App\Http\Controllers\Inbox;

use App\Actions\Inbox\ReceiveMessage;
use App\Actions\Inbox\UpdateMessageStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Message;
use App\Support\PhoneNumber;
use App\WhatsApp\Gateway;
use App\WhatsApp\InboundMessage;
use App\WhatsApp\SimulatorGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plays WhatsApp's part until a real number is connected: a supervisor
 * writes as a customer, and marks what the team sent as delivered, read or
 * failed. It disappears once the gateway is no longer the simulator.
 */
class SimulatorController extends Controller
{
    public function index(): Response
    {
        $this->ensureSimulator();

        return Inertia::render('inbox/simulator', [
            'contacts' => Contact::query()
                ->orderByDesc('last_message_at')
                ->orderBy('name')
                ->limit(40)
                ->get(['id', 'name', 'phone']),
            'sent' => Message::query()
                ->where('direction', MessageDirection::Outbound)
                ->where('type', '!=', MessageType::Note)
                ->whereNotNull('external_id')
                ->with(['conversation.contact', 'author:id,name'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (Message $message): array => [
                    'id' => $message->id,
                    'to' => $message->conversation->contact?->name ?? $message->conversation->contact?->phone,
                    'preview' => $message->preview(),
                    'author' => $message->author?->name,
                    'status' => $message->status?->value,
                    'at' => $message->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, ReceiveMessage $receive): RedirectResponse
    {
        $this->ensureSimulator();

        $request->merge(['phone' => PhoneNumber::normalize($request->string('phone')->toString())]);

        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:4096', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:'.config('whatsapp.max_upload_kb')],
        ], [
            'phone.required' => __('app.simulator.invalid_phone'),
        ]);

        $file = $request->file('file');
        $media = [];

        if ($file !== null) {
            $name = Str::uuid()->toString().'.'.($file->guessExtension() ?? $file->getClientOriginalExtension());
            $media = [
                'type' => MessageType::forMime((string) $file->getMimeType()),
                'mediaPath' => $file->storeAs('media/inbound', $name, config('whatsapp.media_disk')),
                'mediaMime' => (string) $file->getMimeType(),
                'mediaName' => $file->getClientOriginalName(),
                'mediaSize' => (int) $file->getSize(),
            ];
        }

        $receive->handle(new InboundMessage(...[
            'phone' => $validated['phone'],
            'body' => $validated['body'] ?? null,
            'profileName' => $validated['name'] ?? null,
            'externalId' => 'wamid.sim.in.'.Str::uuid()->toString(),
            ...$media,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.simulator.flash.received')]);

        return back();
    }

    public function status(Request $request, Message $message, UpdateMessageStatus $update): RedirectResponse
    {
        $this->ensureSimulator();

        $validated = $request->validate([
            'status' => ['required', 'in:delivered,read,failed'],
        ]);

        $status = MessageStatus::from($validated['status']);

        $update->handle(
            (string) $message->external_id,
            $status,
            $status === MessageStatus::Failed ? '131026' : null,
            $status === MessageStatus::Failed ? __('app.simulator.failure') : null,
        );

        return back();
    }

    private function ensureSimulator(): void
    {
        abort_unless(app(Gateway::class) instanceof SimulatorGateway, 404);
    }
}
