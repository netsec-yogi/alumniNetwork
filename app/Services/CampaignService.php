<?php

namespace App\Services;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Delivers a campaign to its audience (SRS 48-49). Runs from a queued job. */
class CampaignService
{
    public function __construct(
        private readonly AudienceBuilder $audiences,
        private readonly AuditLogger $audit,
    ) {}

    public function deliver(Campaign $campaign): void
    {
        // Claim the campaign atomically so a double dispatch can't send twice.
        $claimed = Campaign::whereKey($campaign->id)->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'sending']);
        if ($claimed === 0) {
            return;
        }
        $campaign->refresh();

        $channels = $campaign->channels;
        $emailable = in_array('email', $channels, true)
            ? $this->audiences->emailable($this->audiences->query($campaign->audience))->pluck('users.id')->flip()
            : collect();
        $total = $emails = 0;

        $this->audiences->query($campaign->audience)->select('users.id', 'users.name', 'users.email')->chunkById(500, function ($users) use ($campaign, $channels, $emailable, &$total, &$emails) {
            $rows = [];
            foreach ($users as $user) {
                /** @var User $user */
                $email = $emailable->has($user->id);
                $rows[] = ['campaign_id' => $campaign->id, 'user_id' => $user->id, 'emailed' => $email];

                if (in_array('in_app', $channels, true)) {
                    $user->notify(new Notice($campaign->subject, route('notifications.index', [], false), Str::limit(strip_tags($campaign->bodyHtml()), 180)));
                }
                if ($email) {
                    Mail::to($user)->queue(new CampaignMail($campaign, $user));
                    $emails++;
                }
                $total++;
            }
            DB::table('campaign_recipients')->insertOrIgnore($rows);
        }, 'users.id', 'id');

        $campaign->forceFill(['status' => 'sent', 'sent_at' => now(), 'recipients_count' => $total, 'emails_count' => $emails])->save();
        $this->audit->record('campaign.sent', 'communications', $campaign, null, ['recipients' => $total, 'emails' => $emails], $campaign->created_by);
    }
}
