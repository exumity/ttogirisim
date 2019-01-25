<?php

namespace App\Http\Middleware;

use App\Helpers;
use App\ManagerLastSignin;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Exception;

class ManagerJwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->header("token");
        if(!$token) {
            // Unauthorized response if token not there
            return Helpers::responseErrorJson(401,["Token not provided"]);
        }
        try {
            $credentials = JWT::decode($token, env('MANAGERS_JWT_SECRET'), ['HS256']);
        } catch(ExpiredException $e) {
            return Helpers::responseErrorJson(401,['Provided token is expired']);
        } catch (SignatureInvalidException $e){
            return Helpers::responseErrorJson(401,['Invalid token']);
        } catch(Exception $e) {
            return Helpers::responseErrorJson(500,['Token error']);
        }
        try{
            $user_last_signin = ManagerLastSignin::find($credentials->sub);
            if(!$user_last_signin){
                return Helpers::responseErrorJson(401,["Outside token"]);
            }else{
                if((string)$user_last_signin->time!==(string)$credentials->iat)
                    return Helpers::responseErrorJson(401,["Old token"]);
            }
            $request->user_id = $credentials->sub;
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['Last login error']);
        }

        return $next($request);
    }
}