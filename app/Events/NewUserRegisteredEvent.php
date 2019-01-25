<?php

namespace App\Events;

use App\User;

class NewUserRegisteredEvent extends Event
{

    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct()
    {

    }
}
