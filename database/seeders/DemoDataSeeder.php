<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\AlumniProfile;
use App\Models\AlumniRecord;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\JobPosting;
use App\Models\MentorProfile;
use App\Models\User;
use App\Services\AlumniVerificationService;
use App\Services\CommunityService;
use App\Services\EventRegistrationService;
use App\Services\PostService;
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
