<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\AlumniRecordImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportAlumniRecords implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Import $import) {}

    public function handle(AlumniRecordImporter $importer): void
    {
        $importer->run($this->import);
    }
}
