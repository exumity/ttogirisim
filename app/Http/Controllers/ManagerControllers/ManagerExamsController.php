<?php

namespace App\Http\Controllers\ManagerControllers;

use App\EventExams;
use App\EventExamsQuestions;
use App\EventQuestionAnswers;
use App\EventImage;
use App\Events;
use App\Jobs\ManagerNewEventExamJob;
use App\User;
use App\UserClassicAnswers;
use App\UserCompletedExams;
use App\UserProfile;
use App\UserTestAnswers;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use App\Helpers;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ManagerExamsController extends Controller
{

    private $request;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function store($event_id)
    {
        $validator = Validator::make(
            $this->request->only(["exam"]), [
                "exam" => "required"
            ]
        );

        if ($validator->fails()) {
            return Helpers::responseErrorJson(400, $validator->errors()->all());
        }

        try{
            if(!Events::find($event_id))
                return Helpers::responseErrorJson(404,["event can not found"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["check event error"]);
        }

        try{
            DB::beginTransaction();
            $exam = new EventExams;
            $exam->title=$this->request->exam["title"];
            $exam->description=$this->request->exam["description"];
            $exam->type = $this->request->exam["type"];
            $exam->manager_id = $this->request->user_id;
            $exam->event_id = $event_id;
            $exam->save();

            $questions = $this->request->exam["questions"];
            for($i=0; $i<count($questions); $i++){
                $question = new EventExamsQuestions;
                $question->question = $questions[$i]["question"];
                $question->exam_id = $exam->id;
                $question->manager_id = $this->request->user_id;
                $question->event_id = $event_id;
                $question->save();

                $answers = $questions[$i]["answers"];
                for($j=0;$j<count($answers);$j++){
                    $answer = new EventQuestionAnswers;
                    $answer->answer = $answers[$j]["answer"];
                    $answer->correct = $answers[$j]["correct"];
                    $answer->exam_id = $exam->id;
                    $answer->manager_id = $this->request->user_id;
                    $answer->question_id=$question->id;
                    $answer->event_id=$event_id;
                    $answer->save();
                }
            }

            try{
                $job=(new ManagerNewEventExamJob($exam,$this->request->user_id))->onConnection('database')->delay(0);
                $this->dispatch($job);
            }catch(\Exception $exception){};

            DB::commit();

            return Helpers::responseSuccessJson(201,["exam_id"=>$exam->id]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["exam create error"]);
        }




    }


    public function indexExams($event_id){
        $validator = Validator::make(
            $this->request->only(["page"]), [
                "page" => "nullable"
            ]
        );

        if ($validator->fails()) {
            return Helpers::responseErrorJson(400, $validator->errors()->all());
        }

        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $exams = EventExams::where('manager_id',$this->request->user_id)
                ->where('event_id',$event_id)
                ->orderBy('created_at','desc')
                ->skip($skip_value)
                ->take(env('ITEM_COUNT_PER_PAGE'))
                ->get();
            $index=0;
            foreach ($exams as $exam){
                $questions_count=EventExamsQuestions::where('event_id',$exam->event_id)
                ->where('exam_id',$exam->id)->count();
                $exams[$index]->total_questions=$questions_count;

                $completed_count = UserCompletedExams::where('event_id',$exam->event_id)
                ->where('exam_id',$exam->id)
                ->count();
                $exams[$index]->completed_count=$completed_count;
                $index++;
            }
            $more = true;
            if(count($exams)<env("ITEM_COUNT_PER_PAGE"))
                $more = false;

            return Helpers::responseSuccessJson(200,$exams,["more"=>$more]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["exams getting error"]);
        }

    }


    public function deleteEventExam($exam_id){
        try{
            $exam = EventExams::find($exam_id);
            if($exam){
                $exam->delete();
                return Helpers::responseSuccessJson(200,["message"=>"deleted"]);
            } else {
                return Helpers::responseErrorJson(404,["Exam can not found"]);
            }
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["delete exam error"]);
        }
    }

    public function getExamDetail($event_id,$exam_id){
        try{
            $exam = EventExams::where('event_id',$event_id)
                ->where('id',$exam_id)
                ->where('publish',1)
                ->first();
            if(!$exam) return Helpers::responseErrorJson(404,["exam can not found"]);

            $exam_questions = EventExamsQuestions::where('exam_id',$exam->id)
                ->get();

            $index=0;
            foreach ($exam_questions as $question){
                $answers =   EventQuestionAnswers::where('exam_id',$exam->id)
                    ->where('question_id',$question->id)->get();
                $exam_questions[$index]->answers = $answers;
                $index++;
            }
            $exam->questions = $exam_questions;


            return Helpers::responseSuccessJson(200,$exam);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["exam detail getting error"]);
        }
    }

    public function getCompletedUserAnswers($event_id,$exam_id,$user_id){
        try{
            $event = Events::find($event_id);
            $exam = EventExams::find($exam_id);
            $user = User::find($user_id);
            if(!$exam && !$event && !$user) return Helpers::responseErrorJson(404,["user,event or exam can not found"]);

            $joined = UserCompletedExams::where('event_id',$event_id)->where('exam_id',$exam_id)->where('user_id',$user_id)->get()->first();
            if(!$joined) return Helpers::responseErrorJson(404,["joined can not found"]);

            $event->exam = $exam;
            $questions = EventExamsQuestions::where('event_id',$event_id)->where('exam_id',$exam_id)->get();
            $answers = DB::table('event__question__answers')->where('event_id',$event_id)->where('exam_id',$exam_id)->get();
            //$answers = EventQuestionAnswers::select('correct')->where('event_id',$event_id)->where('exam_id',$exam_id)->get();
            if($exam->type==0){
                $event->user_answers=UserClassicAnswers::where('exam_id',$exam_id)
                    ->where('event_id',$event_id)
                    ->where('user_id',$user_id)
                    ->get();
            }else{
                $event->user_answers=UserTestAnswers::where('exam_id',$exam_id)
                    ->where('event_id',$event_id)
                    ->where('user_id',$user_id)
                    ->get();
            }

            $event->questions = $questions;
            $event->answers=$answers;

            return Helpers::responseSuccessJson(200,$event);


        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["user answers getting error"]);
        }

    }


    public function getUsersAsCompletedExam($event_id,$exam_id){
        try{
            $completed_users = UserCompletedExams::where('event_id',$event_id)->where('exam_id',$exam_id)->get();
            $index=0;
            foreach ($completed_users as $completed_user){
                $user = UserProfile::where('user_id',$completed_user->user_id)->get()->first();
                $completed_users[$index]->user = $user;
                $index++;
            }

            return Helpers::responseSuccessJson(200,$completed_users);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["exam completed users getting error"]);
        }
    }








    //

}
