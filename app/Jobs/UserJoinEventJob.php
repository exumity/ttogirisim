<?php

namespace App\Jobs;

use App\Events;
use App\EventSubscribers;
use App\EventImage;
use App\FcmServer;
use App\ManagerFcmToken;
use App\ManagerLastSignin;
use App\User;
use App\UserProfile;

class UserJoinEventJob extends Job
{

    public $subscriber;

    /**
     * Create a new job instance.
     *
     * @param UserProfile $profile
     */
    public function __construct(EventSubscribers $subscriber)
    {
        $this->subscriber=$subscriber;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $registration_ids = ManagerFcmToken::select('fcm_token')->whereNotNull('fcm_token')->get();
        $re_ids=array();
        foreach ($registration_ids as $registration_id){
            array_push($re_ids,$registration_id->fcm_token);
        }

        $user = UserProfile::where('user_id',$this->subscriber->user_id)->get()->first();
        $event = Events::find($this->subscriber->event_id);
        $event->image = EventImage::where('event_id',$event->id);


        $message=[
            "registration_ids"=>$re_ids,
            "notification"=>[
                "title"=>"Etkinliğe Katılım",
                "body"=>$user->name_surname." etkinliğe katıldı.",
                "icon"=>"https://www.ttogirisim.com/secure/public/api/v1/user/image/".$user->profile_image,
                "click_action"=>"https://ttogirisim.com/manage/events/detail/".$event->id
            ],
            "data"=>[
                "id"=>"event",
                "title"=>"Etkinliğe Katılım",
                "message"=>$user->name_surname . " adlı kullanıcı, ".$event->title." başlıklı etkinliğe katıldı.",
                "to"=>"/events/detail/".$event->id
            ]
        ];

        FcmServer::sendNotificationToFcmServer($message);


    }
}
