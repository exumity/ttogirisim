<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Events extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, SoftDeletes;


    /**
     *The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title','description','type','starting_on','ending_on','manager_id','manager_note','quota'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at','manager_id','manager_note'
    ];



    /**
     * Get events with managers
     *
     * @return object
     *
     * @param $page
     */
    public static function indexEventsForUser($page,$type){
        $skip_value = Helpers::skipValueForPagination($page);

        /*
        $events = Events::latest()
            ->whereNull('deleted_at')
            ->where('ending_on', '>', date("Y-m-d H:i:s"))
            ->skip($skip_value)
            ->take(env('ITEM_COUNT_PER_PAGE'))
            ->get();

        $index = 0;
        foreach ($events as $event){
            $events[$index]->manager = Managers::where('id',$event->manager_id)->get()->first();
            $events[$index]->manager->profile = ManagerProfile::where('manager_id',$event->manager_id)->get()->first();
        }
        */

        $events =
            DB::table('events')
            ->select(
                'events.id as event_id',
                'events.title as event_title',
                'events.starting_on as event_starting_on',
                'events.ending_on as event_ending_on',
                'manager__profiles.manager_id',
                'manager__profiles.name_surname as manager_name_surname',
                'manager__profiles.profile_image as manager_profile_image'
            )
            ->leftJoin('managers','events.manager_id','=','manager_id')
            ->leftJoin('manager__profiles','events.manager_id','=','manager__profiles.manager_id')
            ->whereNull('events.deleted_at')
            ->where('events.ending_on', '>', date("Y-m-d H:i:s"))
                ->where('events.type',$type)
            ->orderBy('events.created_at', 'desc')
            //->skip($skip_value)
            //->take(env('ITEM_COUNT_PER_PAGE'))
            ->get();

        //add total subscriber for per event
        $index = 0;
        foreach ($events as $event){
            $events[$index]->manager_profile_image_url="https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$event->manager_profile_image;
            $events[$index]->total_tests = 0;
            $events[$index]->total_subscriber =
                EventSubscribers::where('event_id',$event->event_id)
                    ->where('type','<',2)
                    ->count();
            $events[$index]->image = EventImage::where('event_id',$event->event_id)->get()->first();
            $events[$index]->image->image_url = env("API_BASE_URL")."events/image/".$events[$index]->image->image_name;
            $index++;
        }

        if ($page>1){
            $events=[];
        }
        return $events;
    }


    public static function indexEventsForManager($manager_id,$page){
        $skip_value = Helpers::skipValueForPagination($page);
        $events =
            DB::table('events')
                ->select(
                    'events.id as event_id',
                    'events.title as event_title',
                    'events.manager_note as event_manager_note',
                    'events.type as event_type',
                    'events.starting_on as event_starting_on',
                    'events.ending_on as event_ending_on'
                )
                ->leftJoin('event__images','events.id','=','event__images.event_id')
                ->whereNull('events.deleted_at')
                ->where('events.manager_id','=',$manager_id)
                ->orderBy('events.created_at','desc')
                ->skip($skip_value)
                ->take(env('ITEM_COUNT_PER_PAGE'))
                ->get();

        //add total subscriber for per event
        $index = 0;
        foreach ($events as $event){
            $events[$index]->total_tests = 0;
            $events[$index]->total_subscriber =
                EventSubscribers::where('event_id',$event->event_id)
                    ->where('type','<',2)
                    ->count();
            $events[$index]->image = EventImage::where('event_id',$event->event_id)->get()->first();
            $events[$index]->image->image_url = env("API_BASE_URL")."events/image/".$events[$index]->image->image_name;
            $index++;

        }

        return $events;
    }


    /**
     *
     * Get event detail
     *
     * @param $id
     * @return mixed
     */
    public static function getEventDetail($id){
        $events =
            DB::table('events')
                ->select(
                    'events.id as event_id',
                    'events.title as event_title',
                    'events.description as event_description',
                    'events.address as event_address',
                    'events.starting_on as event_starting_on',
                    'events.ending_on as event_ending_on',
                    'manager__profiles.manager_id',
                    'manager__profiles.name_surname as manager_name_surname',
                    'manager__profiles.profile_image as manager_profile_image'
                )
                ->leftJoin('managers','events.manager_id','=','manager_id')
                ->leftJoin('manager__profiles','events.manager_id','=','manager__profiles.manager_id')
                ->whereNull('events.deleted_at')
                ->where('events.id',$id)
                ->get()
                ->first();

        if(!$events) return null;

        $events->manager_profile_image_url="https://www.ttogirisim.com/secure/public/api/v1/manager/image/".$events->manager_profile_image;
        //add total subscriber for per event
        $events->total_tests = 0;
        $events->total_subscriber =
            EventSubscribers::where('event_id',$events->event_id)
                ->where('type','<',2)
                ->count();
        $event_image = EventImage::where('event_id',$events->event_id)->get()->first();
        $event_image->image_url = env("API_BASE_URL")."events/image/".$event_image->image_name;
        $events->image = $event_image;


        return $events;
    }


    public static function getUserJoinedEventsForUser($user_id,$page){
        $skip_value = Helpers::skipValueForPagination($page);
        $events = DB::table('event__subscribers')
            ->select(
                'event__subscribers.event_id',
                'events.title',
                'event__subscribers.type as subscribe_type',
                'events.starting_on',
                'events.ending_on'
            )
            ->leftJoin('events','event__subscribers.event_id','=','events.id')
            ->where('event__subscribers.type','<','2')
            ->where('event__subscribers.user_id',$user_id)
            ->whereNull('event__subscribers.deleted_at')
            ->skip($skip_value)
            ->take(env('ITEM_COUNT_PER_PAGE'))
            ->get();

        $index=0;
        foreach ($events as $event){

            $events[$index]->event_status = self::eventStatus($event->starting_on,$event->ending_on);
        }


        return $events;


    }


    /**
     *
     * Search events for user
     *
     * @param string $keyword
     * @return mixed
     */
    public static function indexSearchEventsForUser($keyword=""){


        $events = Events::where('title','LIKE','%'.$keyword.'%')
            ->take(env("ITEM_COUNT_PER_PAGE"))
            ->get();

        //add total subscriber for per event
        $index = 0;
        foreach ($events as $event){
            $events[$index]->total_tests = 0;
            $events[$index]->total_subscriber =
                EventSubscribers::where('event_id',$event->id)
                    ->where('type','<',2)
                    ->count();
            $events[$index]->image = EventImage::where('event_id',$event->id)->get()->first();
            $events[$index]->image->image_url = env("API_BASE_URL")."events/image/".$events[$index]->image->image_name;
            $index++;

        }
        return $events;


    }



    public static function getEventDetailForManager($event_id){
        $events =
            DB::table('events')
                ->whereNull('deleted_at')
                ->where('id',$event_id)
                ->get()
                ->first();

        if(!$events) return null;

        //add total subscriber for per event
        $events->total_tests = 0;
        $events->total_subscriber =
            EventSubscribers::where('event_id',$events->id)
                ->where('type','<',2)
                ->count();
        $event_image = EventImage::where('event_id',$events->id)->get()->first();
        $event_image->image_url = env("API_BASE_URL")."events/image/".$event_image->image_name;
        $events->image = $event_image;


        /*
        $events = Events::select('id','title','description','type','starting_on','ending_on','address','manager_id','manager_note')
            ->where('id',$event_id)->get()->first();

        if(!$events) return null;


        //add total subscriber for per event
        $events->total_tests = 0;
        $events->total_subscriber =
            EventSubscribers::where('event_id',$events->event_id)
                ->where('type','<',2)
                ->count();
        $event_image = EventImage::where('event_id',$event_id)->get()->first();
        $event_image->image_url = env("API_BASE_URL")."events/image/".$event_image->image_name;
        $events->image = $event_image;
        */

        return $events;

    }


    public static function eventStatus ($starting_on,$ending_on){
        $now=date('Y-m-d H:i:s');
        if($now>=$starting_on && $now<=$ending_on)
            return 1;
        else if($now<$starting_on)
            return 0;
        else if($now>$ending_on)
            return 2;
    }


}
