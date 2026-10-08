<?php

namespace App\Providers;

use Anthropic\Client;
use App\Listeners\SecurityEventSubscriber;
use App\Models\Achievement;
use App\Models\AlumniProfile;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\Connection;
use App\Models\Conversation;
use App\Models\DistinguishedAlumnus;
use App\Models\Donation;
use App\Models\EventPhoto;
use App\Models\EventRegistration;
use App\Models\FundraisingCampaign;
use App\Models\GalleryItem;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\MentorshipRequest;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\ResearchOpportunity;
use App\Models\SpeakerInvitation;
use App\Models\Startup;
use App\Models\StoredFile;
use App\Models\Story;
use App\Models\StoryImage;
use App\Models\Survey;
use App\Models\User;
use App\Models\VolunteerSignup;
use App\Services\Ai\AnthropicLanguageModel;
use App\Services\Ai\LanguageModel;
use App\Services\ConnectionService;
use App\Services\LandingPageService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Holds a per-request cache of connection ids.
        $this->app->scoped(ConnectionService::class);

        // Optional AI layer (config/ai.php). The SDK reads ANTHROPIC_API_KEY.
        $this->app->singleton(LanguageModel::class, fn () => new AnthropicLanguageModel(new Client));

        // The configured hosted-checkout provider (config/payments.php).
        $this->app->bind(PaymentGateway::class, fn () => match (config('payments.gateway')) {
            'razorpay' => new RazorpayGateway,
            default => new FakeGateway,
        });
    }

    public function boot(): void
    {
        // SRS 15: long passphrases over composition rules. Breached-password
        // checks call the HIBP range API (k-anonymity), so only in production.
        Password::defaults(fn () => Password::min(config('security.password.min_length'))
            ->max(config('security.password.max_length'))
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        // Lazy loading and silently discarded attributes are bugs; surface
        // them during development rather than as N+1 queries in production.
        // (Not "missing attributes": freshly created models legitimately lack
        // columns that were left to their database defaults.)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if (config('security.force_https')) {
            URL::forceScheme('https');
        }

        Event::subscribe(SecurityEventSubscriber::class);

        // Short, stable type names in polymorphic columns (reports,
        // notifications, roles) instead of PHP class names.
        Relation::enforceMorphMap([
            'user' => User::class,
            'alumni_profile' => AlumniProfile::class,
            'connection' => Connection::class,
            'event' => \App\Models\Event::class,
            'event_registration' => EventRegistration::class,
            'job_posting' => JobPosting::class,
            'job_referral_request' => JobReferralRequest::class,
            'mentorship_request' => MentorshipRequest::class,
            'community' => Community::class,
            'post' => Post::class,
            'post_comment' => PostComment::class,
            'stored_file' => StoredFile::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'achievement' => Achievement::class,
            'story' => Story::class,
            'startup' => Startup::class,
            'research_opportunity' => ResearchOpportunity::class,
            'speaker_invitation' => SpeakerInvitation::class,
            'volunteer_signup' => VolunteerSignup::class,
            'campaign' => Campaign::class,
            'donation' => Donation::class,
            'fundraising_campaign' => FundraisingCampaign::class,
            'survey' => Survey::class,
            'event_photo' => EventPhoto::class,
            'gallery_item' => GalleryItem::class,
            'story_image' => StoryImage::class,
        ]);

        $this->registerRateLimiters();

        // The public landing page is cached; curated content changes refresh it.
        foreach ([\App\Models\Event::class, Story::class, DistinguishedAlumnus::class, Community::class, GalleryItem::class] as $model) {
            $model::saved(fn () => app(LandingPageService::class)->flush());
            $model::deleted(fn () => app(LandingPageService::class)->flush());
        }

        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    }

    /** Abuse limits on member actions (SRS 66). Auth limits live in FortifyServiceProvider. */
    private function registerRateLimiters(): void
    {
        $by = fn (Request $request, string $key) => $key.':'.($request->user()?->id ?: $request->ip());

        RateLimiter::for('connections', fn (Request $r) => [Limit::perHour(30)->by($by($r, 'conn-h')), Limit::perDay(80)->by($by($r, 'conn-d'))]);
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(20)->by($by($r, 'report')));
        RateLimiter::for('posts', fn (Request $r) => [Limit::perMinute(5)->by($by($r, 'post-m')), Limit::perDay(100)->by($by($r, 'post-d'))]);
        RateLimiter::for('messages', fn (Request $r) => [Limit::perMinute(20)->by($by($r, 'msg-m')), Limit::perDay(500)->by($by($r, 'msg-d'))]);
        RateLimiter::for('conversations', fn (Request $r) => Limit::perDay(25)->by($by($r, 'conv-d')));
        RateLimiter::for('donations', fn (Request $r) => [Limit::perMinute(5)->by('give:'.$r->ip()), Limit::perHour(20)->by('give-h:'.$r->ip())]);
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by('hook:'.$r->ip()));
        RateLimiter::for('admin-password', fn (Request $r) => [Limit::perMinute(5)->by($by($r, 'admpw-m')), Limit::perHour(30)->by($by($r, 'admpw-h'))]);
        RateLimiter::for('ai', fn (Request $r) => [Limit::perMinute(6)->by($by($r, 'ai-m')), Limit::perDay(60)->by($by($r, 'ai-d'))]);
        RateLimiter::for('registrations', fn (Request $r) => Limit::perMinute(10)->by($by($r, 'event-reg')));
    }
}
