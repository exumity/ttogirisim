<?php

namespace App\Http\Controllers\ManagerControllers;

use App\ApplicationQuestions;
use App\Applications;
use App\ApplicationsImages;
use App\EventImage;
use App\Events;
use App\Jobs\ManagerNewApplcationJob;
use App\ManagerProfile;
use App\UserApplicationAnswers;
use App\UserJoinedApplications;
use App\UserProfile;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use App\Helpers;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ManagerApplicationsController extends Controller
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


    public function store(){
        $validator=Validator::make(
            $this->request->only(['title','description','starting_on','ending_on','type','manager_note','image','questions']), [
                'title'=>'bail|required|string',
                'description'=>'bail|required|string',
                'starting_on'=>'bail|required|date:Y-m-d H:i',
                'ending_on'=>'bail|required|date:Y-m-d H:i',
                'type'=>'bail|required|integer',
                'manager_note'=>'nullable|string',
                "image"=>"bail|required|mimes:jpeg|max:2048|dimensions:min_width=500,min_height=500", //2048Kb
                'questions'=>'bail|required',
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());


        $questions = explode("|-|",$this->request->questions);
        if(count($questions)<=0)
            return Helpers::responseErrorJson(400,["invalid questions format"]);

        try{

            list($image_width,$image_height) = getimagesize($this->request->image);
            $image_dimensions = $image_width."x".$image_height;

            $destination_path = storage_path("app/images/applications");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();

            $application = new Applications;
            $application->title = $this->request->title;
            $application->description = $this->request->description;
            $application->starting_on = $this->request->starting_on;
            $application->ending_on = $this->request->ending_on;
            $application->manager_note = $this->request->input("manager_note");
            $application->manager_id = $this->request->user_id;
            $application->type = $this->request->type;
            $application->save();

            $application_image = new ApplicationsImages;
            $application_image->image_name = $image_name;
            $application_image->application_id = $application->id;
            $application_image->dimensions = $image_dimensions;
            $application_image->save();

            foreach ($questions as $question){
                $q = new ApplicationQuestions;
                $q->question=$question;
                $q->application_id=$application->id;
                $q->manager_id=$this->request->user_id;
                $q->save();
            }

            try{
                $job=(new ManagerNewApplcationJob($application,$this->request->user_id))->onConnection('database')->delay(0);
                $this->dispatch($job);
            }catch(\Exception $exception){};

            DB::commit();

            return Helpers::responseSuccessJson(200,["application_id"=>$application->id]);

        }catch (\Exception $e){
            DB::commit();
            return Helpers::responseErrorJson(500,["application store error"]);
        }
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
            $applications = Applications::where('manager_id',$this->request->user_id)
                ->skip($skip_value)
                ->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();

            $index=0;
            foreach ($applications as $application){
                $total_subscribers = UserJoinedApplications::where('application_id',$application->id)->count();
                $applications[$index]->total_subscribers=$total_subscribers;

                $application_image = ApplicationsImages::where('application_id',$application->id)->get()->first();
                $application_image->image_url = "https://ttogirisim.com/secure/public/api/v1/applications/image/".$application_image->image_name;
                $applications[$index]->image = $application_image;
                $index++;
            }

            $more = true;
            if(count($applications)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            return Helpers::responseSuccessJson(200,$applications,["more"=>$more]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["applications getting error"]);
        }
    }


    public function delete($application_id){
        try{
            $application = Applications::find($application_id);
            if(!$application)
                return Helpers::responseErrorJson(404,["Application can not found"]);

            $application->delete();
            return Helpers::responseSuccessJson(200,["message"=>"deleted"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["application delete error"]);
        }
    }


    public function getApplicationDetail($application_id){
        try{
            $application = Applications::find($application_id);
            if(!$application)
                return Helpers::responseErrorJson(404,["application can not found"]);

            $application->image = ApplicationsImages::where('application_id',$application_id)->get()->first();

            $application->total_subscribers = UserJoinedApplications::where('application_id',$application_id)->count();

            $application->questions = ApplicationQuestions::where('application_id',$application_id)->get();

            return Helpers::responseSuccessJson(200,$application);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["application detail getting error"]);
        }
    }


    public function getApplicationSubscribers($application_id){
        $validator = Validator::make(
            $this->request->only(['page']),[
                'page'=>'nullable|integer'
            ]
        );

        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $subscribers = UserJoinedApplications::where('application_id',$application_id)
                ->skip($skip_value)
                ->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();

            $more = true;
            if(count($subscribers)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            $index=0;
            foreach ($subscribers as $subscriber){
                $subscribers[$index]->profile = UserProfile::select('name_surname','profile_image')->where('user_id',$subscriber->user_id)->first();
                $user_answers = UserApplicationAnswers::where('application_id',$application_id)->where('user_id',$subscriber->user_id)->get();
                $subscribers[$index]->user_answers=$user_answers;
                $index++;
            }
            return Helpers::responseSuccessJson(200,$subscribers,["more"=>$more]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["application subscribers getting error"]);
        }
    }


    public function updateApplication($application_id){
        $vars= [
            "title"=>"required|string",
            "description"=>"required|string",
            "type"=>"required|integer",
            "manager_note"=>"required|string",
            "starting_on"=>"required|date_format:Y-m-d H:i",
            "ending_on"=>"required|date_format:Y-m-d H:i",
        ];

        //set arrays of updates data
        $setupdaterequest=array();
        $setupdaterequestrules=array();

        //check defined data

        foreach ($vars as $var => $key){
            if(!empty($this->request->input((string)$var))){
                $setupdaterequest[]=$var;
                $setupdaterequestrules[$var]=$key;
            }
        }

        if(count($setupdaterequest)<=0)
            return Helpers::responseErrorJson(400,['No data to update']);

        $validator = Validator::make($this->request->only($setupdaterequest), $setupdaterequestrules);
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        $values=$this->request->all($setupdaterequest);


        try{
            DB::table('applications')->where('id',$application_id)->update($values);
            return Helpers::responseSuccessJson(200,["message"=>"success"]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['update error']);
        }

    }


    public function updateApplicationImage($application_id){
        $validator = Validator::make(
            $this->request->only(["image"]),[
                "image"=>"bail|required|mimes:jpeg|max:2048|dimensions:min_width=500,min_height=500" //2048Kb
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            list($image_width,$image_height) = getimagesize($this->request->image);
            $image_dimensions = $image_width."x".$image_height;

            $destination_path = storage_path("app/images/applications");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();
            ApplicationsImages::where('application_id',$application_id)
                ->update(["image_name"=>$image_name,"dimensions"=>$image_dimensions]);


            DB::commit();
            return Helpers::responseErrorJson(200,["message"=>"updated"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["Application image update error"]);
        }
    }






    //

}
