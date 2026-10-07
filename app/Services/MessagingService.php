<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\MessageRequestReceived;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Direct messaging (SRS 25).
 *
 * Connections message freely. Anyone else in the network can send one
 * message as a request; nothing more until the recipient accepts (replying
 * counts as accepting). Blocks stop messages both ways. There is no
 * administrative read path: moderators only ever see a message someone
 * reported.
 */
class MessagingService
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function start(User $sender, User $recipient, string $body, ?StoredFile $attachment = null): Conversation
    {
        $this->guard($sender, $recipient);

        $conversation = DB::transaction(function () use ($sender, $recipient) {
            $existing = Conversation::where('direct_key', Conversation::directKey($sender->id, $recipient->id))->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $conversation = new Conversation;
            $conversation->forceFill([
                'type' => 'direct',
                'direct_key' => Conversation::directKey($sender->id, $recipient->id),
                'status' => $this->connections->areConnected($sender->id, $recipient->id) ? Conversation::ACTIVE : Conversation::REQUEST,
                'created_by' => $sender->id,
            ])->save();
            $conversation->participants()->attach([$sender->id, $recipient->id]);

            return $conversation;
        });

        $isNewRequest = $conversation->wasRecentlyCreated && $conversation->status === Conversation::REQUEST;
        $this->send($sender, $conversation, $body, $attachment);

        if ($isNewRequest) {
            $recipient->notify(new MessageRequestReceived($sender, $conversation));
        }

        return $conversation;
    }

    public function send(User $sender, Conversation $conversation, string $body, ?StoredFile $attachment = null): Message
    {
        if (! $conversation->hasParticipant($sender)) {
            throw new InvalidArgumentException('Not your conversation.');
        }

        $other = $conversation->participants()->whereKeyNot($sender->id)->first();
        if ($other && ($this->connections->isBlockedEitherWay($sender->id, $other->id) || ! $other->isActive())) {
            throw new InvalidArgumentException('You can’t message this member.');
        }

        if ($conversation->status === Conversation::DECLINED) {
            throw new InvalidArgumentException('This member declined your message request.');
        }

        if ($conversation->status === Conversation::REQUEST) {
            if ($conversation->created_by === $sender->id) {
                if ($conversation->messages()->where('sender_id', $sender->id)->exists()) {
                    throw new InvalidArgumentException('Wait until they accept your message request.');
                }
            } else {
                // Replying to a request accepts it.
                $conversation->forceFill(['status' => Conversation::ACTIVE])->save();
            }
        }

        return DB::transaction(function () use ($sender, $conversation, $body, $attachment) {
            $message = $conversation->messages()->make(['body' => $body]);
            $message->forceFill(['sender_id' => $sender->id, 'attachment_file_id' => $attachment?->id])->save();
            $attachment?->attachable()->associate($message)->save();

            $conversation->forceFill(['last_message_at' => now()])->save();
            $this->markRead($sender, $conversation);

            return $message;
        });
    }

    public function respond(User $user, Conversation $conversation, bool $accept): void
    {
        if ($conversation->status !== Conversation::REQUEST || $conversation->created_by === $user->id || ! $conversation->hasParticipant($user)) {
            throw new InvalidArgumentException('There is no request to answer.');
        }

        $conversation->forceFill(['status' => $accept ? Conversation::ACTIVE : Conversation::DECLINED])->save();
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    /** Conversations with messages from others newer than the user's last read. */
    public function unreadCount(User $user): int
    {
        return DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->where('cp.user_id', $user->id)
            ->where('c.status', '!=', Conversation::DECLINED)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('messages as m')
                ->whereColumn('m.conversation_id', 'c.id')
                ->where('m.sender_id', '!=', $user->id)
                ->whereNull('m.deleted_at')
                ->where(fn ($q) => $q->whereNull('cp.last_read_at')->orWhereColumn('m.created_at', '>', 'cp.last_read_at')))
            ->count();
    }

    private function guard(User $sender, User $recipient): void
    {
        if ($sender->is($recipient)) {
            throw new InvalidArgumentException('You can’t message yourself.');
        }
        if (! $sender->isCommunityMember() || ! $recipient->isActive() || $this->connections->isBlockedEitherWay($sender->id, $recipient->id)) {
            throw new InvalidArgumentException('You can’t message this member.');
        }
    }
}
