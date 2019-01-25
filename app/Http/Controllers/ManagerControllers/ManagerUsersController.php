<?php

namespace App\Http\Controllers\ManagerControllers;

use App\EventSubscribers;
use App\EventImage;
use App\Events;
use App\ManagerProfile;
use App\User;
use App\UserCompletedExams;
use App\UserJoinedApplications;
use App\UserProfile;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use App\Helpers;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ManagerUsersController extends Controller
{

    private $request;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }


    public function indexUsers(){
        $validator = Validator::make(
            $this->request->only(['page']),[
                'page'=>'nullable|integer'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $users = DB::table("users")->select("id","email","created_at","type")
                ->orderBy('created_at', 'desc')
                ->skip($skip_value)
                ->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();

            $index=0;
            foreach ($users as $user) {
                $profile = UserProfile::where('user_id',$user->id)->get()->first();
                $users[$index]->profile = $profile;
                $index++;
            }

            $more = true;
            if(count($users)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            return Helpers::responseSuccessJson(200,$users,["more"=>$more]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['users getting error']);
        }
    }

    public function getUserDetail($user_id){
        try{
            $user = User::find($user_id);

            $user->profile = UserProfile::where('user_id',$user_id)->get()->first();

            $user->total_joined_events = EventSubscribers::where('user_id',$user_id)->count();
            $user->total_joined_applications = UserJoinedApplications::where('user_id',$user_id)->count();
            $user->total_completed_exams = UserCompletedExams::where('user_id',$user_id)->count();

            return Helpers::responseSuccessJson(200,$user);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["user detail getting error"]);
        }
    }







    //

}
