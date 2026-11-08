<?php

namespace App\Console\Commands;

use App\Services\ImportReviewStore;
use Illuminate\Console\Command;

class PruneImportReviews extends Command
{
    protected $signature = 'nai:import-prune';
    protected $description = 'Delete expired or unreadable encrypted import reviews from private storage';

    public function handle(ImportReviewStore $reviews): int
    {
        $count = $reviews->prune();
        $this->info('Removed '.$count.' expired or unreadable import reviews.');
        return self::SUCCESS;
    }
}
