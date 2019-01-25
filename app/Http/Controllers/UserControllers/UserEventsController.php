<?php

namespace App\Http\Controllers\UserControllers;

use App\Events;
use App\EventSubscribers;
use App\EventImage;
use App\Helpers;
use App\Jobs\USerCancelEventJob;
use App\Jobs\UserJoinEventJob;
use App\ManagerProfile;
use App\Managers;
use App\ManagerLastSignin;
use App\User;
use App\UserProfile;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;

class UserEventsController extends Controller
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

    public function getEvents(){
        $validator = Validator::make(
            $this->request->only(["page"]),[
                "page"=>"nullable|integer"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }

        try{
            $events= Events::indexEventsForUser($this->request->input("page"),$this->request->user_type);
            $more = true;
            if(count($events)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            return Helpers::responseSuccessJson(200,$events,["more"=>$more]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Get events page error"]);
        }


    }

    public function getDetailsEvents($id){
        try{
            $event = Events::getEventDetail($id);
            if(!$event)
                return Helpers::responseErrorJson(404,["Event can not found"]);



            $is_checked=false;
            $is_joined=false;
            $is_joined_data = EventSubscribers::where('event_id',$event->event_id)
                ->where('user_id',$this->request->user_id)
                ->get()
                ->first();
            if(!$is_joined_data){
                $is_joined=false;
            }else{
                if ((int)$is_joined_data->type===2) $is_joined=false;
                if((int)$is_joined_data->type===1) {$is_joined=true; $is_checked=true;}
                if((int)$is_joined_data->type===0) $is_joined=true;
            }



            return Helpers::responseSuccessJson(200,$event,["is_joined"=>$is_joined,"is_checked"=>$is_checked]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Get custom event error"]);
        }
    }

    public function searchEvent(){
        $validator = Validator::make(
            $this->request->only(["keyword"]),[
                "keyword"=>"required|string"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }

        try{
            return
                Helpers::responseSuccessJson(200,
                    Events::indexSearchEventsForUser($this->request->keyword)
                );
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event search error"]);
        }
    }

    public function joinEvent($id){

        //check event
        try{
            $event = Events::find($id);
            if(!$event) {
                return Helpers::responseErrorJson(404,["Event can not found"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event check error"]);
        }

        //check user type for this event
        try{
            if((int)$this->request->user_type!==(int)$event->type){
                return Helpers::responseErrorJson(401,["Access denied"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event check user type error"]);
        }

        //check already joined
        try{
            $check = EventSubscribers::where('event_id',$event->id)->where('user_id',$this->request->user_id)->get()->first();
            $subscriber_count = EventSubscribers::where("event_id",$event->id)->where("type","<","2")->count();
            if($subscriber_count >= $event->quota && $event->quota!=0){
                return Helpers::responseErrorJson(401,["Bu etkinliğe katılamazsınız. Etkinlik kişi sayısı dolmuş."]);
            }
            if($check){
                if((int)$check->type===2){
                    $check->type = 0;
                    $check->save();
                    $job=(new UserJoinEventJob($check))->onConnection('database')->delay(0);
                    $this->dispatch($job);
                    return Helpers::responseSuccessJson(200,["subscribe_id"=>$check->id]);
                }else{
                    return Helpers::responseErrorJson(400,["Already have been subscribed"]);
                }
            }else{
                $subscriber = new EventSubscribers;
                $subscriber->event_id = $event->id;
                $subscriber->user_id = $this->request->user_id;
                $subscriber->type = 0;
                $subscriber->save();

                $job=(new UserJoinEventJob($subscriber))->onConnection('database')->delay(0);
                $this->dispatch($job);

                return Helpers::responseSuccessJson(201,["subscribe_id"=>$subscriber->id]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Subscribe already check error"]);
        }

    }


    public function quitEvent($id){
        //check event
        try{
            $event = Events::find($id);
            if(!$event) {
                return Helpers::responseErrorJson(404,["Event can not found"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event check error"]);
        }

        //check user type for this event
        try{
            if((int)$this->request->user_type!==(int)$event->type){
                return Helpers::responseErrorJson(401,["Access denied"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Event check user type error"]);
        }

        //check is joined and quit from event
        try{
            $check = EventSubscribers::where('event_id',$event->id)->where('user_id',$this->request->user_id)->get()->first();
            if($check){
                if((int)$check->type===2){
                    return Helpers::responseErrorJson(400,["Already have been quited"]);
                }else if((int)$check->type===1){
                    return Helpers::responseErrorJson(400,["Event checked, Can not quit"]);
                }else{
                    $check->type=2;
                    $check->save();
                    $job=(new USerCancelEventJob($check))->onConnection('database')->delay(0);
                    $this->dispatch($job);
                    return Helpers::responseSuccessJson(200,["message"=>"Successfully quited"]);
                }
            }else{
                return Helpers::responseErrorJson(404,["Subscribe can not found"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Subscribe already check error"]);
        }
    }


    public function userJoinedEvents(){
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
            $subscribers = EventSubscribers::where('user_id',$this->request->user_id)
                ->where('type','<','2')
                //->skip($skip_value)
                //->take(env('ITEM_COUNT_PER_PAGE'))
                ->get();
            $result=array();
            $index=0;
            foreach ($subscribers as $subscriber){
                $events = Events::find($subscriber->event_id);
                $events_image = EventImage::where('event_id',$subscriber->event_id)->get()->first();
                $events_image->event_image_url = "https://www.ttogirisim.com/secure/public/api/v1/events/image/".$events_image->image_name;
                $events->image=$events_image;
                $events->total_subscribers = EventSubscribers::where('event_id',$subscriber->event_id)->where('type','<','2')->count();
                $manager = ManagerProfile::select('manager_id','name_surname','profile_image')->where('manager_id',$events->manager_id)->get()->first();
                $manager->profile_image_url="https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$manager->profile_image;
                $events->manager=$manager;
                $events->event_status = Events::eventStatus($events->starting_on,$events->ending_on);

                array_push($result,$events);
                $index++;
            }
            if ($this->request->input("page") > 1) $result = [];
            return Helpers::responseSuccessJson(200,$result);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Joined's events getting error"]);
        }


    }


    public function checkInEvent($event_id){
        $validator = Validator::make(
            $this->request->only(["code"]),[
                "code"=>"required|string"
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());


        try{

            try{
                $code = Helpers::encrypt_decrypt("decrypt",$this->request->code);
                $code = explode("|-|",$code);
                if(count($code)!=2) return Helpers::responseErrorJson(401,["Unauthorized code"]);

                if((int)$code[0]!==(int)$event_id) return Helpers::responseErrorJson(401,["Unauthorized code"]);

                if((int)time()-(int)$code[1]>18) return Helpers::responseErrorJson(401,["Unauthorized code"]);

            }catch (\Exception $e){
                return Helpers::responseErrorJson(401,["Unauthorized code"]);
            }

            $event = Events::find($event_id);
            if(!$event) return Helpers::responseErrorJson(404,["event can not found"]);

            $subs = EventSubscribers::where('user_id',$this->request->user_id)->where('event_id',$event_id)->get()->first();
            if(!$subs) return Helpers::responseErrorJson(404,["can not found subscribe"]);

            switch ((int)$subs->type){
                case 1:
                    return Helpers::responseErrorJson(400,["Already checked"]);
                    break;
                case 2:
                    return Helpers::responseErrorJson(400,["Declined event. can not check-in"]);
            }
            $s = EventSubscribers::find($subs->id);
            $s->type=1;
            $s->save();

            return Helpers::responseSuccessJson(200,["message"=>"checked"]);


        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["event check-in error"]);
        }
    }




}
