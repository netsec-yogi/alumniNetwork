<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Direct messages (SRS 25). */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly FileUploadService $uploads,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['inbox', 'requests'])]])['tab'] ?? 'inbox';

        $conversations = Conversation::for($user)
            ->when($tab === 'inbox', fn ($q) => $q->where(fn ($q) => $q->where('status', Conversation::ACTIVE)
                ->orWhere(fn ($q) => $q->whereIn('status', [Conversation::REQUEST, Conversation::DECLINED])->where('created_by', $user->id))))
            ->when($tab === 'requests', fn ($q) => $q->where('status', Conversation::REQUEST)->where('created_by', '!=', $user->id))
            ->with(['participants:id,name', 'participants.alumniProfile:id,user_id,photo_file_id,preferred_name', 'participants.alumniProfile.photo', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Conversation $c) => $this->summary($c, $user));

        return Inertia::render('Messages/Index', [
            'tab' => $tab,
            'conversations' => $conversations,
            'requestCount' => Conversation::for($user)->where('status', Conversation::REQUEST)->where('created_by', '!=', $user->id)->count(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);
        $user = $request->user();
        $conversation->load(['participants:id,name,status', 'participants.alumniProfile:id,user_id,photo_file_id,preferred_name,verification_status', 'participants.alumniProfile.photo']);
        $this->messaging->markRead($user, $conversation);
        $other = $conversation->otherParticipant($user);

        $messages = $conversation->messages()
            ->with('attachment')
            ->latest('id')->limit(100)->get()->reverse()->values()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'mine' => $m->sender_id === $user->id,
                'body' => $m->status === 'removed' ? null : $m->body,
                'removed' => $m->status === 'removed',
                'attachment' => $m->attachment && $m->status !== 'removed' ? [
                    'url' => $m->attachment->url(),
                    'thumb' => $m->attachment->isImage() ? $m->attachment->url(true) : null,
                    'name' => $m->attachment->original_name,
                    'is_image' => $m->attachment->isImage(),
                ] : null,
                'at' => $m->created_at->format('j M, g:i A'),
            ]);

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'is_requester' => $conversation->created_by === $user->id,
                'other' => $other ? [
                    'name' => $other->alumniProfile?->preferred_name ?: $other->name,
                    'photo_url' => $other->alumniProfile?->photo?->url(true),
                    'profile_id' => $other->alumniProfile?->isVerified() ? $other->alumniProfile->id : null,
                ] : null,
            ],
            'messages' => $messages,
            'reportReasons' => Report::REASONS,
        ]);
    }

    public function start(Request $request, AlumniProfile $profile): RedirectResponse
    {
        $this->authorize('interact', $profile);
        $data = $this->validated($request);

        try {
            $conversation = $this->messaging->start($request->user(), $profile->user, $data['body'], $this->attachment($request));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $data = $this->validated($request);

        try {
            $this->messaging->send($request->user(), $conversation, $data['body'], $this->attachment($request));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function respond(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $accept = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]])['decision'] === 'accept';

        try {
            $this->messaging->respond($request->user(), $conversation, $accept);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $accept ? back() : redirect()->route('messages.index', ['tab' => 'requests'])->with('success', 'Request declined.');
    }

    public function destroy(Request $request, Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);
        $message->delete();

        return back();
    }

    /** @return array{body: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:'.config('security.uploads.max_document_kb')],
        ]);
        $data['body'] = (string) ($data['body'] ?? '');

        return $data;
    }

    /** Images are re-encoded; anything else must be a PDF. Private to the conversation. */
    private function attachment(Request $request): ?StoredFile
    {
        $file = $request->file('attachment');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        try {
            return str_starts_with((string) $file->getMimeType(), 'image/')
                ? $this->uploads->storeImage($file, $request->user(), 'message_attachment', StoredFile::PRIVATE)
                : $this->uploads->storeDocument($file, $request->user(), 'message_attachment', StoredFile::PRIVATE);
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['attachment' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function summary(Conversation $c, User $user): array
    {
        $other = $c->otherParticipant($user);
        $last = $c->latestMessage;
        $read = $c->participants->firstWhere('id', $user->id)?->pivot?->last_read_at;

        return [
            'id' => $c->id,
            'status' => $c->status,
            'other' => [
                'name' => $other?->alumniProfile?->preferred_name ?: ($other?->name ?? 'Former member'),
                'photo_url' => $other?->alumniProfile?->photo?->url(true),
            ],
            'preview' => $last ? (($last->sender_id === $user->id ? 'You: ' : '').($last->status === 'removed' ? 'Message removed' : ($last->body !== '' ? Str::limit($last->body, 80) : '📎 Attachment'))) : null,
            'unread' => $last && $last->sender_id !== $user->id && ($read === null || $last->created_at->gt($read)),
            'at' => $c->last_message_at?->diffForHumans(),
        ];
    }
}
