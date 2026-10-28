<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationEventType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\User;
use App\Rizq\ClientCard;
use App\Rizq\RizqClient;

/**
 * A member handing the customer to Rizq's sales team as a lead. Sales puts
 * them in its pool for the sales manager to assign; a customer sales
 * already has is left with whoever has them. The conversation keeps a line
 * saying who did it, and the card is asked for again.
 */
class AddRizqLead
{
    public function __construct(private RizqClient $rizq) {}

    /**
     * @param  'company'|'individual'  $type
     * @return array{added: bool, owner: string|null}|null Null when Rizq could not be reached.
     */
    public function handle(Conversation $conversation, User $actor, string $type, string $name, ?string $note): ?array
    {
        $phone = (string) $conversation->contact->phone;

        $answer = $this->rizq->ask((string) config('rizq.paths.leads'), [
            'phone' => $phone,
            'name' => $name,
            'type' => $type,
            'note' => $note,
            'added_by' => $actor->name,
        ]);

        if ($answer === null) {
            return null;
        }

        ClientCard::forget($phone);

        $added = (bool) ($answer['added'] ?? false);

        if ($added) {
            ConversationEvent::record($conversation, ConversationEventType::SentToSales, $actor);
        }

        return ['added' => $added, 'owner' => $answer['owner'] ?? null];
    }
}
