<?php

namespace App\Http\Controllers\UserControllers;

use App\AccountKey;
use App\Events\NewUserRegisteredEvent;
use App\Helpers;
use App\Jobs\NewUserRegisteredJob;
use App\User;
use App\UserLastSignin;
use App\UserProfile;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Doorman\Task\ProcessCallbackTask;
use Firebase\JWT\JWT;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use App\FcmServer;

class UsersRegisterController extends Controller
{

    private $iat_time;
    private $exp_time;

    private $request;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->iat_time=time();
        $this->exp_time=time() + (int)env('USER_JWT_EXP');
        $this->request = $request;
    }

    /**
     * Create a new token.
     *
     * @param  \App\User   $user
     * @return string
     */
    protected function jwt(User $user) {
        $payload = [
            'iss' => "hs", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => $this->iat_time, // Time when JWT was issued.
            'exp' => $this->exp_time, // Expiration time
            'typ' => $user->type
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('USERS_JWT_SECRET'));
    }


    public function register(){
        $validator = Validator::make(
            $this->request->only(["email","password","name_surname","type","password_confirmation"]),[
                "email"=>"bail|required|unique:users,email",
                "password"=>"bail|required|string|confirmed",
                "name_surname"=>"bail|required|string",
                "type"=>"bail|required|integer"
            ]
        );
        if($validator->fails()){
            return Helpers::responseErrorJson(400,$validator->errors()->all());
        }

        switch ($this->request->input('type')){
            case 0:
                try{
                    DB::beginTransaction();
                    $user = new User;
                    $user->email = $this->request->input('email');
                    $user->password = app('hash')->make($this->request->input('password'));
                    $user->type = 0;
                    $user->save();

                    $profile = new UserProfile;
                    $profile->name_surname=Str::lower($this->request->input('name_surname'));
                    $profile->user_id = $user->id;
                    $profile->save();

                    $last_login = UserLastSignin::find($user->id);
                    if(!$last_login) {
                        $user_last_login = new UserLastSignin;
                        $user_last_login->time = $this->iat_time;
                        $user_last_login->id = $user->id;
                        $user_last_login->save();
                    }else{
                        $last_login->time = $this->iat_time;
                        $last_login->save();
                    }

                    DB::commit();

                    $token = $this->jwt($user);


                    $job=(new NewUserRegisteredJob($profile))->onConnection('database')->delay(0);
                    $this->dispatch($job);



                    return Helpers::responseSuccessJson(201,[
                        "token"=>$token,"exp"=>$this->exp_time
                    ]);

                }catch (\Exception $e){
                    DB::rollBack();
                    return Helpers::responseErrorJson(500,['Candidate register error'.$e]);
                }
                break;
            case 1:
                $validator = Validator::make(
                    $this->request->only(["account_key"]),[
                        "account_key"=>"bail|required|string"
                    ]
                );
                if($validator->fails()){
                    return Helpers::responseErrorJson(400,$validator->errors()->all());
                }

                try{
                    $account_key = AccountKey::where('account_key',$this->request->input('account_key'))->first();
                    if(!$account_key){
                        return Helpers::responseErrorJson(400,["The account key not found"]);
                    }else{
                        if((int)$account_key->type!==1 OR !empty($account_key->user_id)){
                            return Helpers::responseErrorJson(400,["Invalid account key"]);
                        }else{
                            DB::beginTransaction();
                            $user = new User;
                            $user->email = $this->request->input('email');
                            $user->password = app('hash')->make($this->request->input('password'));
                            $user->type = 1;
                            $user->save();

                            $profile = new UserProfile;
                            $profile->name_surname=Str::lower($this->request->input('name_surname'));
                            $profile->user_id = $user->id;
                            $profile->save();

                            $account_key_update = AccountKey::find($account_key->id);
                            $account_key_update->user_id = $user->id;
                            $account_key_update->save();

                            $last_login = UserLastSignin::find($user->id);
                            if(!$last_login) {
                                $user_last_login = new UserLastSignin;
                                $user_last_login->time = $this->iat_time;
                                $user_last_login->id = $user->id;
                                $user_last_login->save();
                            }else{
                                $last_login->time = $this->iat_time;
                                $last_login->save();
                            }

                            DB::commit();

                            $token = $this->jwt($user);

                            return Helpers::responseSuccessJson(201,[
                                "token"=>$token,"exp"=>$this->exp_time
                            ]);
                        }
                    }
                }catch (\Exception $e){
                    DB::rollBack();
                    return Helpers::responseErrorJson(500,["Pre-incubation register error".$e]);
                }
                break;
            case 2:
                $validator = Validator::make(
                    $this->request->only(["account_key"]),[
                        "account_key"=>"bail|required|string"
                    ]
                );
                if($validator->fails()){
                    return Helpers::responseErrorJson(400,$validator->errors()->all());
                }
                try{
                    $account_key = AccountKey::where('account_key',$this->request->input('account_key'))->first();
                    if(!$account_key){
                        return Helpers::responseErrorJson(400,["The account key not found"]);
                    }else{
                        if((int)$account_key->type!==2 OR !empty($account_key->user_id)){
                            return Helpers::responseErrorJson(400,["Invalid account key"]);
                        }else{
                            DB::beginTransaction();
                            $user = new User;
                            $user->email = $this->request->input('email');
                            $user->password = app('hash')->make($this->request->input('password'));
                            $user->type = 2;
                            $user->save();

                            $profile = new UserProfile;
                            $profile->name_surname=Str::lower($this->request->input('name_surname'));
                            $profile->user_id = $user->id;
                            $profile->save();

                            $account_key_update = AccountKey::find($account_key->id);
                            $account_key_update->user_id = $user->id;
                            $account_key_update->save();

                            $last_login = UserLastSignin::find($user->id);
                            if(!$last_login) {
                                $user_last_login = new UserLastSignin;
                                $user_last_login->time = $this->iat_time;
                                $user_last_login->id = $user->id;
                                $user_last_login->save();
                            }else{
                                $last_login->time = $this->iat_time;
                                $last_login->save();
                            }

                            DB::commit();

                            $token = $this->jwt($user);

                            return Helpers::responseSuccessJson(201,[
                                "token"=>$token,"exp"=>$this->exp_time
                            ]);
                        }
                    }
                }catch (\Exception $e){
                    DB::rollBack();
                    return Helpers::responseErrorJson(500,["Pre-incubation register error"]);
                }
                break;
            default:
                return Helpers::responseErrorJson(400,["Invalid type"]);
        }

    }

    //

}
