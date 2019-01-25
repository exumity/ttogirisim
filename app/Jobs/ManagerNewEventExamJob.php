<?php

namespace App\Jobs;

use App\Applications;
use App\EventExams;
use App\Events;
use App\FcmServer;
use App\Helpers;
use App\ManagerProfile;
use App\UserFcmTokens;
use Illuminate\Support\Facades\DB;

class ManagerNewEventExamJob extends Job
{

    public $manager_id;
    public $exam;

    /**
     * Create a new job instance.
     *
     * @param Events $event
     * @param $manager_id
     */
    public function __construct(EventExams $exam, $manager_id )
    {
        $this->exam = $exam;
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

        $event = Events::find($this->exam->event_id);

        $tokens = DB::table('event__subscribers')
            ->select("user__fcm__tokens.fcm_token")
            ->leftJoin("user__fcm__tokens","event__subscribers.user_id","=","user__fcm__tokens.user_id")
            ->leftJoin("users__last__signins","event__subscribers.user_id","=","users__last__signins.id")
            ->where("event__subscribers.type","<","2")
            ->where("event__subscribers.event_id",$this->exam->event_id)
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
                "text"=>$event->title . " başlıklı etkinliğe yeni sınav eklendi.",
                "icon"=>"default",
                "sound"=>"default"
            ]
        ];

        FcmServer::sendNotificationToFcmServer($message);
    }
}
