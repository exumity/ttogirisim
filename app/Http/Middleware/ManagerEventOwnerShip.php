<?php

namespace App\Http\Middleware;

use App\Helpers;
use App\Events;
use App\ManagerLastSignin;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Exception;
use Symfony\Component\Console\Helper\Helper;

class ManagerEventOwnerShip
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
        //bu kısım kullanıcının token kontrolünden sonra olacağı için tekrar token kontrol yaptırmadım
        $token = $request->header("token");

        if(!empty($request->route()[2]["event_id"])){
            $event_id = $request->route()[2]["event_id"];
            //check ownership
            try{
                $credentials = JWT::decode($token, env('MANAGERS_JWT_SECRET'), ['HS256']);
                $event = Events::find($event_id);
                if($event){
                    if((int)$event->manager_id!==(int)$credentials->sub)
                        return Helpers::responseErrorJson(401,["Access denied"]);
                }
            } catch (\Exception $e){
                return Helpers::responseErrorJson(500,["Ownership control error"]);
            }
        }


        return $next($request);
    }
}