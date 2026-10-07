<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Achievement;
use App\Models\AlumniProfile;
use App\Models\AlumniRecord;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\DistinguishedAlumnus;
use App\Models\Event;
use App\Models\FundraisingCampaign;
use App\Models\JobPosting;
use App\Models\MentorProfile;
use App\Models\ResearchOpportunity;
use App\Models\SpeakerProfile;
use App\Models\Startup;
use App\Models\Story;
use App\Models\Survey;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\AlumniVerificationService;
use App\Services\CommunityService;
use App\Services\DonationService;
use App\Services\EventRegistrationService;
use App\Services\PostService;
use App\Services\SurveyService;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development only: verified alumni for the directory,
 * institute records to auto-verify against, a few pending claims for the
 * verification queue, and one account per staff role. Every demo password
 * is "Demo-Password-2026".
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Password-2026';

    public function run(AlumniVerificationService $verification): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to load demo data in production.');

            return;
        }

        // A factory as an attribute value is resolved per row, so each
        // profile gets its own user (->for() would share a single one).
        $alumniUser = fn () => ['user_id' => User::factory()->role(RoleName::Alumni)->state(['password' => self::PASSWORD])];

        // Verified alumni across batches, plus their institute records.
        AlumniProfile::factory(60)
            ->state($alumniUser)
            ->create()
            ->each(function (AlumniProfile $p) {
                $record = AlumniRecord::create([
                    'roll_number' => $p->roll_number,
                    'name' => $p->user->name,
                    'programme_id' => $p->programme_id,
                    'admission_year' => $p->admission_year,
                    'graduation_year' => $p->graduation_year,
                ]);
                $p->forceFill(['alumni_record_id' => $record->id])->save();
            });

        // Institute records nobody has claimed yet -- register with one of
        // these to see automatic verification.
        AlumniRecord::factory(20)->create();

        // Pending claims for the manual queue: no matching record.
        AlumniProfile::factory(6)
            ->pending()
            ->state($alumniUser)
            ->create()
            ->each(fn (AlumniProfile $p) => $verification->submit($p, 'I graduated before the records were digitised.'));

        $staff = [
            ['verification@iiitm.ac.in', 'Verification Officer', RoleName::VerificationOfficer],
            ['alumni.office@iiitm.ac.in', 'Alumni Office', RoleName::AlumniAdmin],
            ['events@iiitm.ac.in', 'Events Team', RoleName::EventManager],
            ['faculty@iiitm.ac.in', 'Faculty Member', RoleName::Faculty],
            ['student@iiitm.ac.in', 'Student User', RoleName::Student],
        ];

        foreach ($staff as [$email, $name, $role]) {
            User::factory()->role($role)->create(['email' => $email, 'name' => $name, 'password' => self::PASSWORD]);
        }

        $this->seedEvents();
        $this->seedJobs();
        $this->seedMentors();
        $this->seedCommunities();
        $this->seedPhaseTwo();
    }

    private function seedPhaseTwo(): void
    {
        $alumni = User::role(RoleName::Alumni->value)->whereHas('alumniProfile', fn ($q) => $q->verified())->with('alumniProfile')->get();
        $office = User::where('email', 'alumni.office@iiitm.ac.in')->firstOrFail();
        $faculty = User::where('email', 'faculty@iiitm.ac.in')->firstOrFail();

        // Achievements: some published, some awaiting review.
        $titles = ['Promoted to Principal Engineer', 'Best Paper Award at ACL', 'Patent granted for low-power VLSI design', 'Forbes 30 Under 30 Asia', 'Appointed Joint Secretary, MeitY', 'Raised Series A for our startup'];
        foreach ($alumni->random(8) as $i => $u) {
            $a = new Achievement(['category' => array_keys(Achievement::CATEGORIES)[$i % 7], 'title' => $titles[$i % count($titles)], 'description' => fake()->sentence(18), 'achieved_on' => now()->subMonths($i)->toDateString()]);
            $a->forceFill(['alumni_profile_id' => $u->alumniProfile->id, 'status' => $i < 6 ? 'published' : 'submitted', 'reviewed_at' => $i < 6 ? now() : null])->save();
        }

        // Distinguished alumni and stories.
        foreach ($alumni->random(3) as $i => $u) {
            DistinguishedAlumnus::create(['alumni_profile_id' => $u->alumniProfile->id, 'category' => ['industry', 'research', 'entrepreneurship'][$i], 'award_year' => 2025, 'citation' => fake()->paragraph(3), 'is_published' => true]);
        }
        foreach (['From Gwalior to Geneva: building particle-physics software', 'Why I left Big Tech to build for Bharat', 'Interview: leading AI research at 30'] as $i => $title) {
            $s = new Story(['type' => $i === 2 ? 'interview' : 'article', 'title' => $title, 'excerpt' => fake()->sentence(16), 'body' => "## The beginning\n\n".fake()->paragraph(6)."\n\n> ".fake()->sentence(12)."\n\n## What's next\n\n".fake()->paragraph(5), 'alumni_profile_id' => $alumni->random()->alumniProfile->id]);
            $s->forceFill(['status' => 'published', 'published_at' => now()->subDays($i * 9), 'author_id' => $office->id])->save();
        }

        // Startups, speakers, research and volunteering.
        foreach ($alumni->random(5) as $u) {
            $startup = Startup::factory()->create(['created_by' => $u->id]);
            $startup->founders()->attach($u->alumniProfile->id, ['role' => 'Co-founder & CEO']);
        }
        $alumni->random(10)->each(fn ($u) => SpeakerProfile::factory()->create(['user_id' => $u->id]));
        foreach (['Federated learning for healthcare data' => 'project', 'Guest lecture series: cloud architecture' => 'guest_lecture', 'Industry collaboration on EV battery analytics' => 'collaboration'] as $title => $type) {
            $o = new ResearchOpportunity(['type' => $type, 'title' => $title, 'description' => fake()->paragraph(4), 'areas' => ['ML', 'Systems'], 'organization' => 'ABV-IIITM Gwalior']);
            $o->forceFill(['posted_by' => $faculty->id, 'status' => 'open'])->save();
        }
        foreach (['JoSAA counselling helpdesk' => 'admissions', 'Mock interviews for final-year students' => 'career_guidance', 'Annual Alumni Meet volunteer crew' => 'events'] as $title => $category) {
            $v = new VolunteerOpportunity(['category' => $category, 'title' => $title, 'description' => fake()->paragraph(3), 'is_remote' => $category !== 'events', 'starts_on' => now()->addWeeks(2)->toDateString(), 'slots' => 20, 'hours_estimate' => 4]);
            $v->forceFill(['created_by' => $office->id, 'status' => 'open'])->save();
        }

        // Paid demo donations through the real service (test gateway).
        $donations = app(DonationService::class);
        foreach ($alumni->random(6) as $i => $u) {
            $d = $donations->start(['category' => array_keys(config('payments.categories'))[$i % 8], 'amount' => [2500, 5000, 10000, 1000][$i % 4], 'donor_name' => $u->name, 'donor_email' => $u->email], $u, '127.0.0.1')['donation'];
            $donations->markPaid($d, 'pay_demo_'.$i);
        }

        User::factory()->role(RoleName::FundraisingManager)->create(['email' => 'giving@iiitm.ac.in', 'name' => 'Fundraising Office', 'password' => self::PASSWORD]);

        $this->seedPhaseThree($alumni, $office);
    }

    private function seedPhaseThree($alumni, User $office): void
    {
        $giving = User::where('email', 'giving@iiitm.ac.in')->firstOrFail();
        $category = array_key_first(config('payments.categories'));
        $story = fn () => "## Why this matters\n\n".fake()->paragraph(5)."\n\n## Where the money goes\n\n- ".fake()->sentence()."\n- ".fake()->sentence();

        // Campaigns: one live with gifts, a Giving Day with a matching sponsor, one awaiting review.
        $campaigns = [
            ['institutional', 'Modernise the Central Library', 2500000, now()->subWeeks(2), now()->addMonths(2), $giving, 'published', []],
            ['giving_day', 'IIITM Giving Day 2026', 1000000, now()->subDay(), now()->addDays(6), $giving, 'published', ['matching_sponsor' => 'Class of 2005', 'matching_ratio' => 1, 'matching_cap_paise' => 50000000]],
            ['crowdfunding', 'Robotics club: national competition travel', 300000, now()->addWeek(), now()->addMonths(1), $alumni->first(), 'pending_approval', []],
        ];
        $donations = app(DonationService::class);
        foreach ($campaigns as $i => [$type, $title, $goal, $starts, $ends, $organizer, $status, $extra]) {
            $c = new FundraisingCampaign(['type' => $type, 'title' => $title, 'summary' => fake()->sentence(20), 'story' => $story(), 'category' => $category, 'goal_paise' => $goal * 100, 'starts_at' => $starts, 'ends_at' => $ends, ...$extra]);
            $c->forceFill(['organizer_id' => $organizer->id, 'status' => $status, 'approved_by' => $status === 'published' ? $giving->id : null, 'approved_at' => $status === 'published' ? now() : null])->save();

            if ($status === 'published') {
                foreach ($alumni->random(5)->values() as $j => $u) {
                    $d = $donations->start(['category' => $category, 'fundraising_campaign_id' => $c->id, 'amount' => [5000, 10000, 2500, 25000, 1000][$j], 'donor_name' => $u->name, 'donor_email' => $u->email], $u, '127.0.0.1')['donation'];
                    $donations->markPaid($d, "pay_demo_c{$i}_{$j}");
                }
            }
        }

        // Surveys: a published anonymous alumni survey with answers, and a draft.
        $surveys = app(SurveyService::class);
        $survey = (new Survey(['title' => 'Alumni career outcomes 2026', 'description' => 'Five minutes to help us improve placements and mentoring.', 'audience' => ['roles' => ['alumni']], 'is_anonymous' => true, 'closes_at' => now()->addMonth()]))
            ->forceFill(['created_by' => $office->id, 'status' => 'draft']);
        $survey->save();
        $questions = [
            ['single', 'What best describes your current role?', ['Industry', 'Academia / research', 'Startup founder', 'Government', 'Higher studies']],
            ['multiple', 'Which programmes would you take part in?', ['Mentoring', 'Guest lectures', 'Hiring', 'Reunions']],
            ['rating', 'How well did IIITM prepare you for your career?', null],
            ['nps', 'How likely are you to recommend IIITM to a prospective student?', null],
            ['text', 'Anything else we should know?', null],
        ];
        foreach ($questions as $pos => [$type, $prompt, $options]) {
            $survey->questions()->create(['position' => $pos, 'type' => $type, 'prompt' => $prompt, 'options' => $options, 'required' => $type !== 'text']);
        }
        $surveys->publish($survey);
        $survey->load('questions');
        foreach ($alumni->random(12) as $u) {
            $surveys->submit($u, $survey, $survey->questions->mapWithKeys(fn ($q) => [$q->id => match ($q->type) {
                'single' => fake()->randomElement($q->options),
                'multiple' => fake()->randomElements($q->options, 2),
                'rating' => fake()->numberBetween(3, 5),
                'nps' => fake()->numberBetween(5, 10),
                default => fake()->boolean(30) ? fake()->sentence() : null,
            }])->all());
        }

        $draft = (new Survey(['title' => 'Post-event feedback: Annual Alumni Meet', 'is_anonymous' => false, 'audience' => ['roles' => ['alumni']]]))
            ->forceFill(['created_by' => $office->id, 'status' => 'draft']);
        $draft->save();
        $draft->questions()->create(['position' => 0, 'type' => 'rating', 'prompt' => 'How was the event overall?', 'required' => true]);
    }

    private function seedCommunities(): void
    {
        $communities = app(CommunityService::class);
        $posts = app(PostService::class);
        $alumni = User::role(RoleName::Alumni->value)->whereHas('alumniProfile', fn ($q) => $q->verified())->with('alumniProfile.programme')->get();

        // Batch groups for every verified alumnus.
        $alumni->each(fn (User $u) => $communities->enrolInBatchGroup($u->alumniProfile));

        $groups = collect([
            Community::factory()->chapter('Bengaluru')->create(['name' => 'Bengaluru Chapter']),
            Community::factory()->chapter('Hyderabad')->create(['name' => 'Hyderabad Chapter']),
            Community::factory()->create(['name' => 'AI & Machine Learning', 'category' => 'professional']),
            Community::factory()->create(['name' => 'Founders & Startups', 'category' => 'interest']),
            Community::factory()->approval()->create(['name' => 'Higher Studies Abroad', 'category' => 'interest']),
        ]);

        foreach ($groups as $i => $group) {
            $members = $alumni->random(min(12, $alumni->count()));
            foreach ($members as $j => $user) {
                CommunityMember::firstOrNew(['community_id' => $group->id, 'user_id' => $user->id])
                    ->forceFill(['role' => $j === 0 ? CommunityMember::ADMIN : CommunityMember::MEMBER, 'status' => CommunityMember::ACTIVE])->save();
            }
            foreach ($members->take(3) as $user) {
                $posts->create($user, ['body' => fake()->paragraph()], $group);
            }
        }

        $chapterAdmin = User::factory()->role(RoleName::ChapterAdmin)->create(['email' => 'chapter@iiitm.ac.in', 'name' => 'Bengaluru Chapter Lead', 'password' => self::PASSWORD]);
        CommunityMember::firstOrNew(['community_id' => $groups[0]->id, 'user_id' => $chapterAdmin->id])->forceFill(['role' => CommunityMember::ADMIN, 'status' => CommunityMember::ACTIVE])->save();
        Event::published()->limit(2)->update(['community_id' => $groups[0]->id]);

        $samples = [
            'Thrilled to share that I’ve joined the ML platform team — happy to chat with juniors exploring infra roles!',
            'Anyone from the 2014 batch in Pune this weekend? Planning an informal meetup.',
            'Our team is hiring backend interns for summer. Details in the Jobs section — referrals available.',
            'Just published a paper on federated learning at NeurIPS. Grateful to my IIITM professors for the foundation.',
        ];
        foreach ($alumni->random(8) as $i => $user) {
            $posts->create($user, ['body' => $samples[$i % count($samples)], 'kind' => $i % 4 === 3 ? 'achievement' : 'post']);
        }
    }

    private function seedMentors(): void
    {
        User::role(RoleName::Alumni->value)
            ->whereHas('alumniProfile', fn ($q) => $q->verified()->where('graduation_year', '<=', now()->year - 4))
            ->inRandomOrder()->limit(15)->get()
            ->each(fn (User $u) => MentorProfile::factory()->create(['user_id' => $u->id]));
    }

    private function seedJobs(): void
    {
        $posters = User::role(RoleName::Alumni->value)->whereHas('alumniProfile', fn ($q) => $q->verified())->inRandomOrder()->limit(8)->get();

        foreach ($posters as $i => $poster) {
            JobPosting::factory()->create(['posted_by' => $poster->id, 'type' => $i % 3 === 0 ? 'internship' : 'job', 'employment_type' => $i % 3 === 0 ? 'internship' : 'full_time']);
        }
        // A couple awaiting moderation.
        JobPosting::factory(2)->pending()->create(['posted_by' => $posters->first()->id]);
    }

    private function seedEvents(): void
    {
        $organiser = User::where('email', 'events@iiitm.ac.in')->firstOrFail();
        $alumni = User::role(RoleName::Alumni->value)->whereHas('alumniProfile', fn ($q) => $q->verified())->inRandomOrder()->limit(25)->get();

        Event::factory(5)->create(['created_by' => $organiser->id]);
        Event::factory()->public()->create([
            'created_by' => $organiser->id, 'title' => 'Annual Alumni Meet 2026', 'type' => 'alumni_meet',
            'venue' => 'Main Auditorium, ABV-IIITM Gwalior', 'capacity' => 30, 'max_guests' => 2,
        ]);
        Event::factory()->create([
            'created_by' => $organiser->id, 'title' => 'Webinar: Breaking into Product Management', 'type' => 'webinar',
            'is_online' => true, 'venue' => null, 'online_url' => 'https://meet.example.com/pm-webinar', 'capacity' => null,
        ]);
        Event::factory()->draft()->create(['created_by' => $organiser->id, 'title' => 'Draft: Founders Fireside']);

        // Some registrations so attendee lists and the waitlist have content.
        $registrations = app(EventRegistrationService::class);
        $events = Event::published()->get();
        foreach ($alumni as $i => $user) {
            foreach ($events->random(2) as $event) {
                rescue(fn () => $registrations->register($user, $event, $i % 3 === 0 ? min(1, $event->max_guests) : 0), report: false);
            }
        }
    }
}
