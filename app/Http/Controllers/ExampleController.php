<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function halil(Request $request){
        echo $request->input("name");
    }

    //
}
