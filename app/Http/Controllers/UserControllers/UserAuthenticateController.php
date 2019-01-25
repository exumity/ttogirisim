<?php

namespace App\Http\Controllers\UserControllers;

use App\Helpers;
use App\User;
use App\UserLastSignin;
use Firebase\JWT\JWT;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAuthenticateController extends Controller
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
    protected function jwt(User $user) {
        $payload = [
            'iss' => "hs", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => $this->iat_time, // Time when JWT was issued.
            'exp' => $this->exp_time, // Expiration time
            'typ' => $user->type
        ];

        // As you can see we are passing `JW
        //T_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('USERS_JWT_SECRET'));
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
            $user = User::where('email',(string)$this->request->input('email'))->first();
            if(!$user) return Helpers::responseErrorJson(400,["Email does not exist."]);

            // Verify the password and generate the token
            if (Hash::check($this->request->input('password'), $user->password)) {
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
                return Helpers::responseSuccessJson(200,["token"=>$this->jwt($user),"exp"=>$this->exp_time]);
            }

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,[$e]);
        }

        return Helpers::responseErrorJson(400,["Invalid email or password"]);


    }

    public function logout(){
        try{
            $user_last_login=UserLastSignin::find($this->request->user_id);
            if(!$user_last_login){
                return Helpers::responseErrorJson(400,["Sign in not found"]);
            }else{
                $user_last_login->time="";
                $user_last_login->save();
                return Helpers::responseSuccessJson(200,['Sign out successfully']);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Logout error"]);
        }
    }

    //

}
