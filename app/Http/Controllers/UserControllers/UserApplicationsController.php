<?php

namespace App\Http\Controllers\UserControllers;

use App\Applications;
use App\ApplicationsImages;
use App\Events;
use App\EventSubscribers;
use App\Helpers;
use App\Jobs\UserApplicationAppliedJob;
use App\Jobs\UserApplicationCaceledJob;
use App\ManagerProfile;
use App\Managers;
use App\ManagerLastSignin;
use App\User;
use App\UserApplicationAnswers;
use App\UserJoinedApplications;
use App\UserProfile;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\ApplicationQuestions;


class UserApplicationsController extends Controller
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

    public function indexApplications(){
        $validator = Validator::make(
            $this->request->only(['page']),[
                'page'=>'nullable|integer'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());


        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $applications = Applications::where('ending_on','>',date('Y-m-d H:i:s'))
                ->where('starting_on','<','NOW()')
                ->where("type",$this->request->user_type)
                ->orderBy('created_at', 'desc')
               // ->skip($skip_value)
                //->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();

            $index=0;
            foreach ($applications as $application){
                $is_completed = UserJoinedApplications::where('application_id',$application->id)
                    ->where('user_id',$this->request->user_id)
                    ->get();
                $iscomp=false;
                if(count($is_completed)>0) $iscomp=true;
                $applications[$index]->is_completed=$iscomp;

                $manager = ManagerProfile::select('id','name_surname','profile_image')->where('manager_id',$application->manager_id)->get()->first();
                $manager->profile_image_url = "https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$manager->profile_image;
                $applications[$index]->manager=$manager;

                $application_image = ApplicationsImages::where('application_id',$application->id)->get()->first();
                $application_image->image_url = "https://ttogirisim.com/secure/public/api/v1/applications/image/".$application_image->image_name;
                $applications[$index]->image = $application_image;
                $index++;
            }

            $more = true;
            if(count($applications)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            if ($this->request->input("page")>1) $applications=[];

            return Helpers::responseSuccessJson(200,$applications,["more"=>$more]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["applications getting error"]);
        }
    }

    public function getApplicationsDetail($application_id){
        try{
            $application = Applications::find($application_id);
            if(!$application)
                return Helpers::responseErrorJson(404,["application can not found"]);



            $application_image = ApplicationsImages::where('application_id',$application->id)->get()->first();
            $application_image->image_url = "https://ttogirisim.com/secure/public/api/v1/applications/image/".$application_image->image_name;
            $application->image = $application_image;

            $manager = ManagerProfile::select('id','name_surname','profile_image')->where('manager_id',$application->manager_id)->get()->first();
            $manager->profile_image_url = "https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$manager->profile_image;
            $application->manager=$manager;


            $application->total_subscribers = UserJoinedApplications::where('application_id',$application_id)->count();

            $application->questions = ApplicationQuestions::where('application_id',$application_id)->get();


            $is_completed = UserJoinedApplications::where('application_id',$application->id)
                ->where('user_id',$this->request->user_id)
                ->get();
            $iscomp=false;
            if(count($is_completed)>0) $iscomp=true;
            $application->is_completed=$iscomp;

            if($iscomp){
                $answers = UserApplicationAnswers::where('user_id',$this->request->user_id)
                    ->where('application_id',$application->id)
                    ->get();
                $application->user_answers=$answers;
            }else{
                $application->user_answers=[];
            }

            $application->application_status=Applications::applicationStatus($application->starting_on,$application->ending_on);

            return Helpers::responseSuccessJson(200,$application);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["application detail getting error"]);
        }
    }



    public function joinApplication($application_id){

        $validator = Validator::make(
            $this->request->only(['answers']),[
                'answers'=>'required'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $app = Applications::find($application_id);
            if(!$app)
                return Helpers::responseErrorJson(404,["application can not found"]);
            if(Applications::applicationStatus($app->starting_on,$app->ending_on)!=1){
                if($app->ending_on<date('Y-m-d H:i:s')){
                    return Helpers::responseErrorJson(400,["Bu başvurunun tarihi geçmiş veya başlamamış."]);
                }
            }

            $already_joined = UserJoinedApplications::where('user_id',$this->request->user_id)
                ->where('application_id',$application_id)->get()->first();
            if($already_joined)
                return Helpers::responseErrorJson(400,["already joined"]);

            DB::beginTransaction();
            $joined_application = new UserJoinedApplications;
            $joined_application->user_id = $this->request->user_id;
            $joined_application->application_id = $application_id;
            $joined_application->save();

            $answers=null;
            try{
                $answers = $this->request->input("answers");
                for ($i=0; $i<count($answers); $i++){
                    $tmp=$answers[$i]["answer"];
                    $tmp=$answers[$i]["question_id"];
                }
            }catch (\Exception $e){
                return Helpers::responseErrorJson(400,["invalid answers format"]);
            }

            for ($i=0; $i<count($answers); $i++){
                $user_answers = new UserApplicationAnswers;
                $user_answers->answer = $answers[$i]["answer"] ;
                $user_answers->application_id = $application_id;
                $user_answers->user_id = $this->request->user_id;
                $user_answers->question_id = $answers[$i]["question_id"];
                $user_answers->save();
            }

            $job=(new UserApplicationAppliedJob($app, $this->request->user_id))->onConnection('database')->delay(0);
            $this->dispatch($job);

            DB::commit();



            return Helpers::responseSuccessJson(201,["message"=>"joined"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["application join error"]);
        }
    }

    public function updateJoinedApplication($application_id){
        $validator = Validator::make(
            $this->request->only(['answers']),[
                'answers'=>'required'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $app = Applications::find($application_id);
            if($app){
                if(Applications::applicationStatus($app->starting_on,$app->ending_on)!=1){
                    if($app->ending_on<date('Y-m-d H:i:s')){
                        return Helpers::responseErrorJson(400,["Bu başvurunun tarihi geçmiş veya başlamamış."]);
                    }
                }
            }

            $user_joined_application = UserJoinedApplications::where('user_id',$this->request->user_id)
                ->where('application_id',$application_id)->get()->first();
            if(!$user_joined_application)
                return Helpers::responseErrorJson(400,["application join can not found"]);

            $answers=null;
            try{
                $answers = $this->request->input("answers");
                for ($i=0; $i<count($answers); $i++){
                    $tmp=$answers[$i]["answer"];
                    $tmp=$answers[$i]["question_id"];
                }
            }catch (\Exception $e){
                return Helpers::responseErrorJson(400,["invalid answers format"]);
            }

            DB::beginTransaction();
            for ($i=0; $i<count($answers); $i++){
                DB::table('user__application__answers')
                    ->where('question_id',$answers[$i]["question_id"])
                    ->where('application_id',$application_id)
                    ->update(["answer"=>$answers[$i]["answer"]]);
            }
            DB::commit();
            return Helpers::responseSuccessJson(201,["message"=>"updated"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["application join update error"]);
        }
    }


    public function indexUserJoinedApplications(){
        try{
            $result=array();
            $applications = UserJoinedApplications::where('user_id',$this->request->user_id)->get();

            $index=0;
            foreach ($applications as $application){
                $appli = Applications::find($application->application_id);

                $manager = ManagerProfile::select('id','name_surname','profile_image')->where('manager_id',$appli->manager_id)->get()->first();
                $manager->profile_image_url = "https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$manager->profile_image;
                $appli->manager=$manager;

                $application_image = ApplicationsImages::where('application_id',$appli->id)->get()->first();
                $application_image->image_url = "https://ttogirisim.com/secure/public/api/v1/applications/image/".$application_image->image_name;
                $appli->image= $application_image;
                $appli->application_status=Applications::applicationStatus($appli->starting_on,$appli->ending_on);
                array_push($result,$appli);
                $index++;
            }

            return Helpers::responseSuccessJson(200,$result);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["joined application getting error".$e]);
        }
    }

    public function cancelApplication($application_id){
        try{
            $application_ = Applications::find($application_id);
            if(!$application_) return Helpers::responseErrorJson(404,["application can not found"]);

            DB::beginTransaction();

            $application=UserJoinedApplications::where('user_id',$this->request->user_id)->where('application_id',$application_id)->get()->first();
            if(!$application) return Helpers::responseErrorJson(404,["application joined can not found"]);

            DB::table('user__joined__applications')
                ->where('application_id',$application_id)
                ->where('user_id',$this->request->user_id)
                ->delete();
            DB::table('user__application__answers')
                ->where('application_id',$application_id)
                ->where('user_id',$this->request->user_id)
                ->delete();


            $job=(new UserApplicationCaceledJob($application_, $this->request->user_id))->onConnection('database')->delay(0);
            $this->dispatch($job);

            DB::commit();



            return Helpers::responseSuccessJson(200,["message"=>"deleted"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["application cancel error"]);
        }
    }

    public function indexSearchedApplications(){
        $validator = Validator::make(
            $this->request->only(['keyword']),[
                'keyword'=>'required|string'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());


        try{
            $applications = Applications::where('title','LIKE','%'.$this->request->keyword.'%')
                ->where('starting_on','<','NOW()')
                ->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();
            $index=0;
            foreach ($applications as $application){
                $is_completed = UserJoinedApplications::where('application_id',$application->id)
                    ->where('user_id',$this->request->user_id)
                    ->get();
                $iscomp=false;
                if(count($is_completed)>0) $iscomp=true;
                $applications[$index]->is_completed=$iscomp;

                $manager = ManagerProfile::select('id','name_surname','profile_image')->where('manager_id',$application->manager_id)->get()->first();
                $manager->profile_image_url = "https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$manager->profile_image;
                $applications[$index]->manager=$manager;

                $application_image = ApplicationsImages::where('application_id',$application->id)->get()->first();
                $application_image->image_url = "https://ttogirisim.com/secure/public/api/v1/applications/image/".$application_image->image_name;
                $applications[$index]->image = $application_image;
                $index++;
            }
            return Helpers::responseSuccessJson(200,$applications);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["application searching error"]);
        }
    }







}
