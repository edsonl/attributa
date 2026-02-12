<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIpClassificationJob;
use Illuminate\Console\Command;

class ProcessIpClassifications extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ips:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processa IPs pendentes para classificação';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessIpClassificationJob::dispatch();

        $this->info('Job de classificação de IP disparado.');
        //
    }
}
