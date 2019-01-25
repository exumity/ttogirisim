<?php

namespace App\Http\Controllers\UserControllers;

use App\Helpers;
use App\User;
use App\UserFcmTokens;
use App\UserLastSignin;
use App\UserProfile;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
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

    public function getUserProfile(){
        try{
            $user = User::find($this->request->user_id);
            $profile = UserProfile::where('user_id',$user->id)->first();
            $profile->profile_image_url = "https://ttogirisim.com/secure/public/api/v1/user/image/".$profile->profile_image;
            return Helpers::responseSuccessJson(200,[
                "user"=>$user,
                "profile"=>$profile
            ]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Profile getting error"]);
        }
    }

    public function updateProfile(){
        //define variables of database and request input
        $vars= [
            'name_surname'=>'required|string',
            'gender'=>'required|string',
            'birthday'=>'required|date:Y-m-d',
            'address'=>'required|string',
            'phone'=>'required|string'
        ];

        //set arrays of updates data
        $setupdaterequest=array();
        $setupdaterequestrules=array();

        //check defined data

        foreach ($vars as $var => $key){

            if($this->request->has((string)$var)){
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
            DB::table('user__profiles')
                ->where('user_id',$this->request->user_id)
                ->update($values);
            return Helpers::responseSuccessJson(200,["message"=>"updated"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Update error"]);
        }


    }

    public function updateUserProfileImageBase64(){
        $validator = Validator::make(
            $this->request->only(["image"]),[
                "image"=>"bail|required|string" //2048Kb
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $destination_path = storage_path("app/images/users/");
            $image_name = uniqid(md5(uniqid(time())));
            $f=file_put_contents($destination_path.$image_name,base64_decode($this->request->input("image")));
            DB::beginTransaction();
            UserProfile::where('user_id',$this->request->user_id)->update(["profile_image"=>$image_name]);
            DB::commit();
            return Helpers::responseSuccessJson(201,["message"=>"updated"]);
        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["uploading error".$e]);
        }


    }

    public function updateUserProfileImage(){
        $validator = Validator::make(
            $this->request->only(["image"]),[
                "image"=>"bail|required|mimes:jpeg|max:2048|dimensions:min_width=200,min_height=200,ratio=1/1" //2048Kb
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            list($image_width,$image_height) = getimagesize($this->request->image);

            $destination_path = storage_path("app/images/users");
            $image_name = uniqid(md5(uniqid(time())));
            $this->request->file("image")->move($destination_path,$image_name);

            DB::beginTransaction();
            UserProfile::where('user_id',$this->request->user_id)->update(["profile_image"=>$image_name]);
            DB::commit();

            return Helpers::responseErrorJson(200,["message"=>"updated"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["Profile image update error"]);
        }
    }

    public function updateFcmToken(){
        $validator = Validator::make(
            $this->request->only(["fcm_token"]),[
                "fcm_token"=>"required|string" //
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            UserFcmTokens::updateOrCreate(
                ['user_id' => $this->request->user_id],
                ['fcm_token' => $this->request->fcm_token]
            );
            return Helpers::responseSuccessJson(201,["message"=>"updated"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["fcm token update error"]);
        }
    }

    public function upgradeAccount(){
        $validator = Validator::make(
            $this->request->only(["key"]),[
                "key"=>"required|string" //
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            DB::beginTransaction();
            $user = User::find($this->request->user_id);
            if(!$user) return Helpers::responseErrorJson(404,["User can not found"]);

            $key = DB::table("account__keys")->whereNull("user_id")->where("account_key",$this->request->key)->get()->first();

            if(!$key) return Helpers::responseErrorJson(404,["Account key can not found"]);

            if(!((int)$user->type<(int)$key->type)) return Helpers::responseErrorJson(401,["Upgrade denied"]);

            $user->type = $key->type;
            $user->save();

            DB::table("account__keys")->where("id",$key->id)->update(["user_id"=>$this->request->user_id]);

            $last_signin = UserLastSignin::find($this->request->user_id);

            $last_signin->time=null;
            $last_signin->save();
            DB::commit();

            return Helpers::responseSuccessJson(200,["message"=>"upgraded"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["account upgrade error"]);
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

            $user = User::find($this->request->user_id);
            $user->name_surname=UserProfile::where('user_id',$this->request->user_id)->get()->first()->nema_surname;

            $info=array(
                'token'=>$token,
                'username'=>$user->name_surname
            );

            Mail::send('emails.reset_password',$info,function ($m) use ($user){
                $m->to($user->email,$user->name_surname)->subject('Parola sıfırlama isteği.');
                $m->from('qoxteq@gmail.com', 'Tto Girişim');
            });
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["password reset request error"]);
        }


    }


    public function resetPassword(){
        $validator = Validator::make(
            $this->request->only(["token"]),[
                "token"=>"required|string" //
            ]
        );
        if($validator->fails())
            return Helpers::responseErrorJson(400,$validator->errors()->all());

        try{
            $token = Helpers::encrypt_decrypt("decrypt",$this->request->token);
            $token = explode("|-|",$token);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["password reset error"]);
        }


    }


}
