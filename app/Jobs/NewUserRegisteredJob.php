<?php

namespace App\Jobs;

use App\FcmServer;
use App\ManagerFcmToken;
use App\ManagerLastSignin;
use App\User;
use App\UserProfile;

class NewUserRegisteredJob extends Job
{

    public $profile;

    /**
     * Create a new job instance.
     *
     * @param UserProfile $profile
     */
    public function __construct(UserProfile $profile)
    {
        $this->profile=$profile;
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


        $message=[
            "registration_ids"=>$re_ids,
            "notification"=>[
                "title"=>"Yeni Kullanıcı Kaydoldu",
                "body"=>$this->profile->name_surname." adlı kullanıcı uygulamaya kaydoldu.",
                "icon"=>"https://ttogirisim.com/staticweb/images/default_profile_image.jpg",
                "click_action"=>"https://ttogirisim.com/manage/users/".$this->profile->user_id
            ],
            "data"=>[
                "id"=>"user",
		        "title"=>"Yeni Kullanıcı Kaydoldu",
		        "message"=>$this->profile->name_surname . " adlı kullanıcı uygulamaya kaydoldu.",
		        "to"=>"/users/".$this->profile->user_id
            ]
        ];

        FcmServer::sendNotificationToFcmServer($message);


    }
}
