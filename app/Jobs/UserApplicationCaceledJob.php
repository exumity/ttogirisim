<?php

namespace App\Jobs;

use App\Applications;
use App\ApplicationsImages;
use App\Events;
use App\EventSubscribers;
use App\EventImage;
use App\FcmServer;
use App\ManagerFcmToken;
use App\ManagerLastSignin;
use App\User;
use App\UserProfile;

class UserApplicationCaceledJob extends Job
{

    public $applications;
    public $user_id;

    /**
     * Create a new job instance.
     *
     * @param Applications $subscriber
     * @param $user_id
     */
    public function __construct(Applications $applications,$user_id)
    {
        $this->applications=$applications;
        $this->user_id=$user_id;

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

        $user = UserProfile::where('user_id',$this->user_id)->get()->first();


        $message=[
            "registration_ids"=>$re_ids,
            "notification"=>[
                "title"=>"Başvuru İptali",
                "body"=>$user->name_surname." başvuru yaptı.",
                "icon"=>"https://www.ttogirisim.com/secure/public/api/v1/user/image/".$user->profile_image,
                "click_action"=>"https://ttogirisim.com/manage/applications/application/".$this->applications->id
            ],
            "data"=>[
                "id"=>"application",
                "title"=>"Başvuru İptali",
                "message"=>$user->name_surname . " adlı kullanıcı, ".$this->applications->title." başlıklı başvurusunu iptal etti.",
                "to"=>"/applications/application/".$this->applications->title
            ]
        ];

        FcmServer::sendNotificationToFcmServer($message);


    }
}
