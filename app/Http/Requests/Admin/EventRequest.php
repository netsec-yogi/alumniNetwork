<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventType;
use App\Enums\Permission;
use App\Models\CommunityMember;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            ? $this->user()->can('update', $event)
            : $this->user()->can('create', Event::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(EventType::class)],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_online' => ['boolean'],
            'venue' => ['required_unless:is_online,true', 'nullable', 'string', 'max:255'],
            'online_url' => ['required_if:is_online,true', 'nullable', 'url:https', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_guests' => ['integer', 'min:0', 'max:10'],
            'registration_opens_at' => ['nullable', 'date', 'before:ends_at'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at', 'before_or_equal:ends_at'],
            'audience' => ['required', Rule::in([Event::AUDIENCE_PUBLIC, Event::AUDIENCE_MEMBERS])],
            // Full event managers may host anywhere; chapter admins only for
            // groups they administer (SRS 8: "assigned chapters").
            'community_id' => $this->isEventManager()
                ? ['nullable', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')]
                : ['required', 'integer', Rule::in($this->administeredGroupIds())],
        ];
    }

    public function messages(): array
    {
        return ['community_id.required' => 'Choose the chapter hosting this event.', 'community_id.in' => 'You can only create events for groups you administer.'];
    }

    public function isEventManager(): bool
    {
        return $this->user()->can(Permission::EventsManageAttendance->value);
    }

    /** @return list<int> */
    public function administeredGroupIds(): array
    {
        return CommunityMember::where(['user_id' => $this->user()->id, 'role' => 'admin', 'status' => 'active'])->pluck('community_id')->all();
    }
}
