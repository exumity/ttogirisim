<?php
/**
 * Created by PhpStorm.
 * User: halil
 * Date: 22.07.2018
 * Time: 03:19
 */

namespace App;

class Helpers {


    public static function responseErrorJson($status,$error=null){
        if($error==null) $error=[];
        $response = response()->json(
            [
                "meta"=>[
                    "response"=>[
                        "status"=>false,
                        "code"=>$status
                    ]
                ],
                "errors"=>$error
            ],$status
        );
        return $response;
    }

    public static function responseSuccessJson($status,$data=null,$metas=null){
        if($data==null) $data=[];
        $response_value =
            [
                "meta"=>[
                    "response"=>[
                        "status"=>true,
                        "code"=>$status
                    ],
                ],
                "data"=>$data
            ];

        if(!empty($metas) and is_array($metas)){
            foreach ($metas as $key => $value){
                $response_value["meta"][$key]=$value;
            }
        }

        $response = response()->json($response_value,$status);
        return $response;
    }

    public static function skipValueForPagination($page){
        if(!empty($page)) {
            if ((int)$page > 0) {
                return (int)env('ITEM_COUNT_PER_PAGE') * ($page - 1);
            }
        }
        return 0;
    }

    public static function encrypt_decrypt($action, $string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = 'JV6G8FUB9TKE728G';
        $secret_iv = 'HV9AAKPY9SLGIJ9N';
        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        } else if( $action == 'decrypt' ) {
            $output = openssl_decrypt($string, $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
}