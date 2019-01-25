<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


/**
 *
 * resim linkleri
 *
 */
$router->group(
    ['prefix' => 'api/v1/','namespace'=>'UserControllers'],
    function() use ($router) {
        $router->get("events/image/{id}","UserImageServiceController@getEventImage");
        $router->get("user/image/{id}","UserImageServiceController@getUserProfileImage");
        $router->get("applications/image/{id}","UserImageServiceController@getApplicationImage");
        $router->get("manager/image/{id}","UserImageServiceController@getManagerProfileImage");

    }
);

$router->group(
    ['middleware' => 'corsmiddleware'],
    function() use ($router) {

        /**
         *
         * Users Links
         *
         */
        $router->group(
            ['prefix' => 'api/v1/','namespace'=>'UserControllers','middleware'=>'check.api.key'],
            function() use ($router) {
                //Need Auth Links
                $router->group(
                    ['middleware' => 'user.jwt.auth'],
                    function() use ($router) {
                        $router->group(
                            ["prefix"=>"events"],
                            function () use ($router){
                                $router->get("/","UserEventsController@getEvents");
                                $router->post("/{event_id}/check_in","UserEventsController@checkInEvent");
                                $router->get("search","UserEventsController@searchEvent");
                                $router->get("/{id}","UserEventsController@getDetailsEvents");
                                $router->put("/{id}/join","UserEventsController@joinEvent");
                                $router->delete("/{id}/quit","UserEventsController@quitEvent");
                                $router->get("/{id}/exams","UserExamsController@getEventExams");
                                $router->get("/{event_id}/exams/{exam_id}","UserExamsController@getExamDetail");
                                $router->post("/{event_id}/exams/{exam_id}","UserExamsController@completeExam");
                            }
                        );
                        $router->group(
                            ["prefix"=>"applications"],
                            function () use ($router){
                                $router->get("/","UserApplicationsController@indexApplications");
                                $router->post("/search","UserApplicationsController@indexSearchedApplications");
                                $router->get("/{application_id}","UserApplicationsController@getApplicationsDetail");
                                $router->post("/{application_id}/apply","UserApplicationsController@joinApplication");
                                $router->put("/{application_id}/apply","UserApplicationsController@updateJoinedApplication");
                                $router->delete("/{application_id}/cancel","UserApplicationsController@cancelApplication");
                            }
                        );
                        $router->group(
                            ["prefix"=>"user"],
                            function () use ($router){
                                $router->post("/profile","UserProfileController@updateProfile");
                                $router->post("/image","UserProfileController@updateUserProfileImage");
                                $router->post("/image/b64","UserProfileController@updateUserProfileImageBase64");
                                $router->get("/events/joined","UserEventsController@userJoinedEvents");
                                $router->get("/applications/applied","UserApplicationsController@indexUserJoinedApplications");
                                $router->post("/fcm_token","UserProfileController@updateFcmToken");
                                $router->post('upgrade','UserProfileController@upgradeAccount');

                            }
                        );
                        $router->delete('logout','UserAuthenticateController@logout');
                        $router->get('user','UserProfileController@getUserProfile');
                    }
                );
                //Without Auth Links
                $router->post('login', 'UserAuthenticateController@login' );
                $router->post('user','UsersRegisterController@register');
            }
        );
        //options için yönelme
        $router->options('{all:.*}', ['middleware' => 'corsmiddleware', function() {
            //return response('');
        }]);

        /**
         *
         * Managers Links
         *
         */
        $router->group(
            ['prefix' => 'api/v1/manage/','namespace'=>'ManagerControllers'],
            function() use ($router) {
                //Without Auth Links
                $router->post('login', 'ManagerAuthenticateController@login' );
                $router->post('forgotten_password', 'ManagerProfileController@resetPasswordRequest' );
                //Need Auth Links
                $router->group(
                    ['middleware' => 'manager.jwt.auth'],
                    function() use ($router) {
                        $router->get('/', 'ManagerAuthenticateController@halil' );
                        $router->delete('logout','ManagerAuthenticateController@logout');
                        $router->get('manager', 'ManagerProfileController@getManagerProfile' );
                        $router->post('image', 'ManagerProfileController@updateProfileImage' );
                        $router->put('manager', 'ManagerProfileController@updateProfile' );
                        $router->put('fcm_token', 'ManagerProfileController@updateFcmToken' );
                        $router->group(
                            ["prefix"=>"events",'middleware' => 'manager.event.ownership'],
                            function () use ($router){
                                $router->post('/search','ManagerEventsController@searchEvents');
                                $router->get('/','ManagerEventsController@getManagerEvents');
                                $router->get("/{event_id}","ManagerEventsController@getEventDetail");
                                $router->post("/{event_id}/image","ManagerEventsController@updateEventImage");
                                $router->post("/","ManagerEventsController@store");
                                $router->delete("/{event_id}","ManagerEventsController@delete");
                                $router->put("/{event_id}","ManagerEventsController@updateEvent");
                                $router->post("/exams/{event_id}","ManagerExamsController@store");
                                $router->get("/{event_id}/exams","ManagerExamsController@indexExams");
                                $router->get("/{event_id}/exams/{exam_id}","ManagerExamsController@getExamDetail");
                                $router->delete("/exams/{exam_id}","ManagerExamsController@deleteEventExam");
                                $router->get("/{event_id}/subscribers","ManagerEventsController@getEventSubscribers");
                                $router->get("/{event_id}/qr_code","ManagerEventsController@getQrCode");
                                $router->get("/{event_id}/exams/{exam_id}/user/{user_id}","ManagerExamsController@getCompletedUserAnswers");
                                $router->get("/{event_id}/exams/{exam_id}/users","ManagerExamsController@getUsersAsCompletedExam");

                            }
                        );
                        $router->group(
                            ["prefix"=>"applications",'middleware' => 'manager.event.ownership'],
                            function () use ($router){
                                $router->post('/','ManagerApplicationsController@store');
                                $router->get('/','ManagerApplicationsController@indexApplications');
                                $router->get('/{application_id}','ManagerApplicationsController@getApplicationDetail');
                                $router->post('/{application_id}/image','ManagerApplicationsController@updateApplicationImage');
                                $router->put('/{application_id}','ManagerApplicationsController@updateApplication');
                                $router->delete('/{application_id}','ManagerApplicationsController@delete');
                                $router->get('/{application_id}/subscribers','ManagerApplicationsController@getApplicationSubscribers');
                            }
                        );
                        $router->group(
                            ["prefix"=>"users",'middleware' => 'manager.event.ownership'],
                            function () use ($router){
                                $router->get('/','ManagerUsersController@indexUsers');
                                $router->get('/{user_id}','ManagerUsersController@getUserDetail');

                            }
                        );
                    }
                );
            }
        );



        /**
         *
         * Global Links
         *
         */
        $router->get('/key',function () use ($router){
            //return str_random(16);
            return uniqid(md5(uniqid(time())));
        });




    }
);
