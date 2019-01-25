<?php

namespace App\Jobs;

use App\Applications;
use App\Events;
use App\FcmServer;
use App\Helpers;
use App\ManagerProfile;
use App\UserFcmTokens;
use Illuminate\Support\Facades\DB;

class ManagerNewApplcationJob extends Job
{

    public $manager_id;
    public $application;

    /**
     * Create a new job instance.
     *
     * @param Events $event
     * @param $manager_id
     */
    public function __construct(Applications $application, $manager_id )
    {
        $this->application = $application;
        $this->manager_id = $manager_id;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $manager = ManagerProfile::find($this->manager_id);

        $tokens = DB::table('users')
            ->select("user__fcm__tokens.fcm_token")
            ->leftJoin("user__fcm__tokens","users.id","=","user__fcm__tokens.user_id")
            ->leftJoin("users__last__signins","users.id","=","users__last__signins.id")
            ->where("users.type",$this->application->type)
            ->whereNotNull("user__fcm__tokens.fcm_token")
            ->whereNotNull("users__last__signins.time")
            ->get();

        $re_ids=array();
        foreach ($tokens as $token){
            array_push($re_ids,$token->fcm_token);
        }


        $message=[
            "registration_ids"=>$re_ids,
            "notification"=>[
                "title"=>$manager->name_surname,
                "text"=>"Yeni başvuru: ".$this->application->title,
                "icon"=>"default",
                "sound"=>"default"
            ]
        ];

        FcmServer::sendNotificationToFcmServer($message);
    }
}
