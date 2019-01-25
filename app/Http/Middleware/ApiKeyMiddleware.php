<?php

namespace App\Http\Middleware;

use App\Helpers;
use Closure;

class ApiKeyMiddleware
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
        $api_key = $request->header("api-key");

        try{
            if(empty($api_key))
                return Helpers::responseErrorJson(401,["Api key is not defined"]);
            else{
                if($api_key!==env('API_KEY')){
                    return Helpers::responseErrorJson(401,["Api key is not valid"]);
                }
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,['Api key error']);
        }

        return $next($request);
    }
}