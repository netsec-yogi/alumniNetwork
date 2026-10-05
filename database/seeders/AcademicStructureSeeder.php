<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Programme;
use Illuminate\Database\Seeder;

/**
 * ABV-IIITM departments and programmes, past and present -- alumni graduated
 * from programmes that are no longer offered, so those stay (inactive ones
 * remain selectable for historical batches). To be confirmed against the
 * academic section's records before go-live.
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'CSE' => 'Computer Science & Engineering',
            'EEE' => 'Electrical & Electronics Engineering',
            'ES' => 'Engineering Sciences',
            'MS' => 'Management Studies',
        ];

        foreach ($departments as $code => $name) {
            Department::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $dept = Department::pluck('id', 'code');

        $programmes = [
            ['BTECH-CSE', 'B.Tech. Computer Science & Engineering', 'B.Tech.', 4, 'CSE'],
            ['BTECH-EE', 'B.Tech. Electrical Engineering', 'B.Tech.', 4, 'EEE'],
            ['IPG-MTECH', 'Integrated Post Graduate (B.Tech. + M.Tech.) IT', 'IPG M.Tech.', 5, 'CSE'],
            ['IPG-MBA', 'Integrated Post Graduate (B.Tech. + MBA) IT', 'IPG MBA', 5, 'MS'],
            ['MTECH-CSE', 'M.Tech. Computer Science & Engineering', 'M.Tech.', 2, 'CSE'],
            ['MTECH-DSAI', 'M.Tech. Data Science & AI', 'M.Tech.', 2, 'CSE'],
            ['MTECH-VLSI', 'M.Tech. VLSI', 'M.Tech.', 2, 'EEE'],
            ['MBA', 'Master of Business Administration', 'MBA', 2, 'MS'],
            ['PHD', 'Doctor of Philosophy', 'Ph.D.', 5, null],
        ];

        foreach ($programmes as [$code, $name, $degree, $years, $deptCode]) {
            Programme::updateOrCreate(['code' => $code], [
                'name' => $name,
                'degree' => $degree,
                'duration_years' => $years,
                'department_id' => $deptCode ? $dept[$deptCode] : null,
            ]);
        }
    }
}
