<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class FcmServer extends Model
{
    private static $url="https://fcm.googleapis.com/fcm/send";
    private static $server_key="AAAAEZo4cZk:APA91bFy4x2KB6gTueozjbxPWCFIA6Svyid8LuYP1smgu8NU4xJ2EXzDzuuz0hEYYgnfkeJWAdMHK0N49f_2hv6sHJg7lf7vAZB0muWAJJwOM8yaw55jk3RsRW0p-T_W52BqWbWKVp-u86IPRBkBigmTLrl_LSs2ZA";

    public static function sendNotificationToFcmServer($message){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::$url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($message,JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => array(
                "Authorization: key=".self::$server_key,
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

    }
}
