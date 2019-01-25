<?php

namespace App\Http\Controllers\ManagerControllers;

use App\Helpers;
use App\Managers;
use App\ManagerLastSignin;
use App\UserLastSignin;
use Firebase\JWT\JWT;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ManagerAuthenticateController extends Controller
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
        $this->exp_time=time() + (int)env('MANAGER_JWT_EXP');
        $this->request = $request;
    }

    /**
     * Create a new token.
     *
     * @param  \App\User   $user
     * @return string
     */
    protected function jwt(Managers $manager) {
        $payload = [
            'iss' => "hs", // Issuer of the token
            'sub' => $manager->id, // Subject of the token
            'iat' => $this->iat_time, // Time when JWT was issued.
            'exp' => $this->exp_time // Expiration time
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('MANAGERS_JWT_SECRET'));
    }

    public function login(){
        $validator = Validator::make(
            $this->request->only(["email","password"]),[
                "email"=>"bail|required|email",
                "password"=>"bail|required|string"
            ]
        );
        if($validator->fails()){

           return Helpers::responseErrorJson(400,$validator->errors()->all());
        }

        try{
            $user = Managers::where('email',$this->request->input('email'))->first();
            if(!$user) return Helpers::responseErrorJson(400,["Email does not exist."]);

            // Verify the password and generate the token
            if (Hash::check($this->request->input('password'), $user->password)) {
                $last_login = ManagerLastSignin::find($user->id);

                if(!$last_login) {
                    $user_last_login = new ManagerLastSignin;
                    $user_last_login->time = $this->iat_time;
                    $user_last_login->id = $user->id;
                    $user_last_login->save();
                }else{
                    $last_login->time = $this->iat_time;
                    $last_login->save();
                }
                return Helpers::responseSuccessJson(200,["token"=>$this->jwt($user),"exp"=>$this->exp_time]);
            }

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Auth error".$e]);
        }

        return Helpers::responseErrorJson(400,["Invalid email or password"]);


    }

    public function logout(){
        try{
            $manager_last_login=ManagerLastSignin::find($this->request->user_id);
            if(!$manager_last_login){
                return Helpers::responseErrorJson(400,["Sign in not found"]);
            }else{
                $manager_last_login->time="";
                $manager_last_login->save();
                return Helpers::responseSuccessJson(200,['Sign out successfully']);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Logout error"]);
        }
    }

    //

}
