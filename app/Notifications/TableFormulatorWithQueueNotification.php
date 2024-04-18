<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TableFormulatorWithQueueNotification extends Notification implements ShouldQueue
{
    use Queueable;
}
