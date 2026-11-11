<?php

namespace NineteenNinetyFour\Newsletter\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Newsletter\Newsletter;
use Statamic\Console\RunsInPlease;

class Prune extends Command
{
    use RunsInPlease;

    protected $signature = 'newsletter:prune';

    protected $description = 'Delete newsletter sign-ups that were never confirmed';

    public function handle(Newsletter $newsletter): int
    {
        $deleted = $newsletter->prune();

        $this->info($deleted === 1 ? '1 unconfirmed sign-up deleted.' : "{$deleted} unconfirmed sign-ups deleted.");

        return self::SUCCESS;
    }
}
