<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\AlumniProfile;
use App\Models\AlumniRecord;
use App\Models\User;
use App\Services\AlumniVerificationService;
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
    }
}
