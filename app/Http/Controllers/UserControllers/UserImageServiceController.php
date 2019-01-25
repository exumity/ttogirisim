<?php

namespace App\Http\Controllers\UserControllers;

use App\Events;
use App\EventSubscribers;
use App\Helpers;
use App\Managers;
use App\ManagerLastSignin;
use App\User;
use App\UserProfile;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Validator;
use Illuminate\Http\Request;

class UserImageServiceController extends Controller
{
    private $request;
    public $headers;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $type = 'image/jpeg';
        $this->headers=[
            'Access-Control-Allow-Origin'=>$request->server('HTTP_ORIGIN'),
            'Access-Control-Allow-Methods'=>'OPTIONS, HEAD, GET, POST, PUT, PATCH, DELETE',
            'Access-Control-Allow-Headers'=>$request->header('Access-Control-Request-Headers'),
            'Content-Type' => $type
        ];

        $this->request = $request;


    }

    public function getEventImage($id){

        $path = storage_path("app/images/events/").$id;

        $response = new BinaryFileResponse($path, 200 , $this->headers);

        return $response;
    }

    public function getApplicationImage($id){

        $path = storage_path("app/images/applications/").$id;

        $response = new BinaryFileResponse($path, 200 , $this->headers);

        return $response;
    }

    public function getUserProfileImage($id){

        $path = storage_path("app/images/users/").$id;
        $response = new BinaryFileResponse($path, 200 , $this->headers);


        return $response;
    }

    public function getManagerProfileImage($id){

        $path = storage_path("app/images/managers/").$id;
        $response = new BinaryFileResponse($path, 200 , $this->headers);


        return $response;
    }




}
