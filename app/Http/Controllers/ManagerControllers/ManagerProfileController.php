<?php

namespace App\Http\Controllers\ManagerControllers;

use App\Applications;
use App\EventExams;
use App\EventImage;
use App\Events;
use App\ManagerFcmToken;
use App\ManagerProfile;
use App\Managers;
use Validator;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\Request;
use App\Helpers;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ManagerProfileController extends Controller
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


    public function getManagerProfile(){
        try{
            $manager = ManagerProfile::where('manager_id',$this->request->user_id)->first();
            if(!$manager) return Helpers::responseErrorJson(401,["Manager can not found"]);

            $manager->email = Managers::select("email")->where('id',$this->request->user_id)->get()->first()->email;
            $manager->total_events = Events::where('manager_id',$this->request->user_id)->count();
            $manager->total_applicatiions = Applications::where('manager_id',$this->request->user_id)->count();
            $manager->total_exams = EventExams::where('manager_id',$this->request->user_id)->count();

            return Helpers::responseSuccessJson(200,$manager);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['Getting manager profile error']);
        }
    }

    public function updateProfileImage(){
        $validator = Validator::make(
            $this->request->only(["image"]),[
                "image"=>"bail|required|mimes:jpeg|max:2048|dimensions:min_width=200,min_height=200,ratio=1/1" //2048Kb
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            list($image_width,$image_height) = getimagesize($this->request->image);

            $destination_path = storage_path("app/images/managers");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();
            ManagerProfile::where('manager_id',$this->request->user_id)->update(["profile_image"=>$image_name]);
            DB::commit();

            return Helpers::responseErrorJson(200,["message"=>"updated"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["Profile image update error"]);
        }
    }


    public function updateProfile(){
        //define variables of database and request input
        $vars= [
            'name_surname'=>'required|string',
            'birthday'=>'required|date:Y-m-d',
            'address'=>'required|string',
            'phone'=>'required|string'
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
            DB::table('manager__profiles')
                ->where('manager_id',$this->request->user_id)
                ->update($values);
            return Helpers::responseSuccessJson(200,["message"=>"updated"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Update error"]);
        }

    }


    public function updateFcmToken(){
        $validator = Validator::make(
            $this->request->only(["fcm_token"]),[
                "fcm_token"=>"required|string" //2048Kb
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            ManagerFcmToken::updateOrCreate(
                ['manager_id' => $this->request->user_id],
                ['fcm_token' => $this->request->fcm_token]
            );
            return Helpers::responseSuccessJson(201,["message"=>"updated"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["fcm token update error"]);
        }
    }



    public function resetPasswordRequest(){
        $validator = Validator::make(
            $this->request->only(["email"]),[
                "email"=>"required|email" //
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{

            $token = Helpers::encrypt_decrypt("encrypt",$this->request->email."|-|".(string)time());

            $user = Managers::where('email',$this->request->email)->get()->first();
            if(!$user){

            }else{
                $user->name_surname=ManagerProfile::where('manager_id',$user->id)->get()->first()->name_surname;

                $info=array(
                    'token'=>$token,
                    'username'=>$user->name_surname
                );



                Mail::send('emails.reset_password',$info,function ($m) use ($user){
                    $m->to("l1311012064@stud.sdu.edu.tr",$user->name_surname)
                        ->subject('Parola sıfırlama isteği.');
                    $m->from(env("MAIL_USERNAME"), 'Tto Girişim');
                });

            }

            return Helpers::responseSuccessJson(200,["message"=>"Email adresiniz eşleştiyse size bir email gönderdik."]);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["password reset request error".$e]);
        }


    }







    //

}
