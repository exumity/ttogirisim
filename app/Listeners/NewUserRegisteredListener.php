<?php

namespace App\Listeners;

use App\Events\ExampleEvent;
use App\Events\NewUserRegisteredEvent;
use App\FcmServer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewUserRegisteredListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ExampleEvent  $event
     * @return void
     */
    public function handle(NewUserRegisteredEvent $event)
    {


    }
}
