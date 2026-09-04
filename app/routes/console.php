<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler domain (generate invoice, reminder, rekonsiliasi) ditambahkan saat milestone terkait.
Schedule::command('queue:prune-batches --hours=48')->daily();
