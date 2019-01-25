<?php

namespace App\Http\Controllers\ManagerControllers;

use App\EventSubscribers;
use App\EventImage;
use App\Events;
use App\Jobs\ManagersCreatedsEventsJob;
use App\UserProfile;
use SimpleSoftwareIO\QrCode\BaconQrCodeGenerator;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use App\Helpers;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ManagerEventsController extends Controller
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
        $validator = Validator::make(
            $this->request->only(["quota","title","description","type","manager_note","address","starting_on","ending_on","image"]),[
                "quota"=>"required|integer",
                "title"=>"required|string",
                "description"=>"required|string",
                "type"=>"required|integer",
                "manager_note"=>"nullable|string",
                "address"=>"required|string",
                "starting_on"=>"required|date_format:Y-m-d H:i",
                "ending_on"=>"required|date_format:Y-m-d H:i",
                "image"=>"bail|required|mimes:jpeg|max:2048|dimensions:min_width=500,min_height=500" //2048Kb
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }



        try{
            list($image_width,$image_height) = getimagesize($this->request->image);
            $image_dimensions = $image_width."x".$image_height;

            $destination_path = storage_path("app/images/events");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();

            $event = new Events;
            $event->title = Str::lower($this->request->title);
            $event->description = $this->request->description;
            $event->type = $this->request->type;
            $event->manager_note = $this->request->input("manager_note");
            $event->address = $this->request->address;
            $event->starting_on = $this->request->starting_on;
            $event->ending_on = $this->request->ending_on;
            $event->manager_id=$this->request->user_id;
            $event->quota=$this->request->quota;
            $event->save();

            $event_image = new EventImage;
            $event_image->event_id = $event->id;
            $event_image->image_name = $image_name;
            $event_image->dimensions = $image_dimensions;
            $event_image->save();


            try{
                $job=(new ManagersCreatedsEventsJob($event,$this->request->user_id))->onConnection('database')->delay(0);
                $this->dispatch($job);
            }catch(\Exception $exception){};

            DB::commit();

            return Helpers::responseSuccessJson(201,["event_id"=>$event->id]);
        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["Add event error"]);
        }
    }


    public function delete($event_id){

        //check event
        try{
            $event = Events::find($event_id);
            if($event){
                $event->delete();
                return Helpers::responseSuccessJson(200,["message"=>"deleted"]);
            } else {
                return Helpers::responseErrorJson(404,["Event can not found"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event delete error"]);
        }
    }


    public function getEventDetail($event_id){
        //check event
        try{
            $event = Events::getEventDetailForManager($event_id);
            if(!$event) return Helpers::responseErrorJson(404,["Event can not found"]);
            return Helpers::responseSuccessJson(200,$event);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event detail error".$e]);
        }
    }


    public function getManagerEvents(){
        $validator = Validator::make(
            $this->request->only(["page"]),[
                "page"=>"nullable|integer"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }
        try{
            $events = Events::indexEventsForManager($this->request->user_id,$this->request->input("page"));

            $more = true;
            if(count($events)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            return Helpers::responseSuccessJson(200,$events,["more"=>$more]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event getting error"]);
        }
    }

    /**
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEvent($event_id){
        $vars= [
            "title"=>"required|string",
            "description"=>"required|string",
            "type"=>"required|integer",
            "manager_note"=>"required|string",
            "address"=>"required|string",
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
            DB::table('events')->where('id',$event_id)->update($values);
            return Helpers::responseSuccessJson(200,["message"=>"success"]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['update error']);
        }


    }


    public function getEventSubscribers($event_id){
        $validator = Validator::make(
            $this->request->only(["page"]),[
                "page"=>"nullable|integer"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }
        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $subscribers = EventSubscribers::where('event_id',$event_id)
                ->skip($skip_value)
                ->take(env('ITEM_COUNT_PER_PAGE'))
                ->get();
            $index=0;
            foreach ($subscribers as $subscriber){
                $profile = UserProfile::where('user_id',$subscriber->user_id)->first();
                $subscribers[$index]->profile=$profile;
                $index++;
            }
            return Helpers::responseSuccessJson(200,$subscribers);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["event subscribers error"]);
        }
    }

    public function searchEvents(){
        $validator = Validator::make(
            $this->request->only(["keyword"]),[
                "keyword"=>"required|string"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }


        try{
            $events = Events::where('title','LIKE','%'.$this->request->keyword.'%')
                ->where('manager_id',$this->request->user_id)
                ->take(env("ITEM_COUNT_PER_PAGE"))
                ->get();

            //add total subscriber for per event
            $index = 0;
            foreach ($events as $event){
                $events[$index]->total_subscriber =
                    EventSubscribers::where('event_id',$event->id)
                        ->where('type','<',2)
                        ->count();
                $events[$index]->image = EventImage::where('event_id',$event->id)->get()->first();
                $events[$index]->image->image_url = env("API_BASE_URL")."events/image/".$events[$index]->image->image_name;
                $index++;
            }

            return Helpers::responseSuccessJson(200,$events);


        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["event search error"]);
        }

    }


    public function updateEventImage($event_id){
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

            $destination_path = storage_path("app/images/events");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();
            EventImage::where('event_id',$event_id)
                ->update(["image_name"=>$image_name,"dimensions"=>$image_dimensions]);
            DB::commit();
            return Helpers::responseErrorJson(200,["message"=>"updated"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["Event image update error"]);
        }
    }



    public function getQrCode($event_id){
        $customClaims = [
            'id' => $event_id,
            'time'=>time()
        ];
        $val=(string)$customClaims["id"]."|-|".(string)$customClaims["time"];
        $token=Helpers::encrypt_decrypt("encrypt",$val);


        header('Content-Type: image/png');
        //echo QrCode::format('png')->margin(1)->merge(Storage::url('e-96.png'))->size(500)->generate($token->get());
        //echo QrCode::format('png')->color(0,0,0)->errorCorrection('L')->margin(1)->size(500)->generate($token);
       // QrCode::format('png')->color(0,0,0)->errorCorrection('L')->margin(1)->size(500)->generate($token);
        $qrcode = new BaconQrCodeGenerator;
        echo $qrcode->format('png')->color(0,0,0)->errorCorrection('L')->margin(1)->size(650)->generate($token);

    }





    //

}
