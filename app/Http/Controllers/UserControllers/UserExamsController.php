<?php

namespace App\Http\Controllers\UserControllers;

use App\EventExams;
use App\EventExamsQuestions;
use App\EventQuestionAnswers;
use App\Events;
use App\EventSubscribers;
use App\Helpers;
use App\Managers;
use App\ManagerLastSignin;
use App\User;
use App\UserClassicAnswers;
use App\UserCompletedExams;
use App\UserProfile;
use App\UserTestAnswers;
use Symfony\Component\Console\Helper\Helper;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserExamsController extends Controller
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

    public function getEventExams($id){
        $validator = Validator::make(
            $this->request->only(["page"]), [
                "page" => "nullable|integer"
            ]
        );

        if ($validator->fails())
            return Helpers::responseErrorJson(400, $validator->errors()->all());

        try{
            if(!Events::find($id))
                return Helpers::responseErrorJson(404,["event can not found"]);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["check event error"]);
        }

        try{
            $skip_value = Helpers::skipValueForPagination($this->request->input("page"));
            $exams = EventExams::where('publish',1)->where('event_id',$id)
                ->select('id','title','type','event_id')
                ->skip($skip_value)
                ->take(env('ITEM_COUNT_PER_PAGE'))
                ->get();
            $index=0;
            foreach ($exams as $exam){
                $completed_exam = UserCompletedExams::where('event_id',$exam->event_id)->where('exam_id',$exam->id)
                    ->where('user_id',$this->request->user_id)
                    ->get()
                    ->first();
                if(!$completed_exam) $exams[$index]->is_completed=false;
                else $exams[$index]->is_completed=true;

                $total_questions = EventExamsQuestions::where('event_id',$id)->where('exam_id',$exam->id)->count();
                $exams[$index]->total_question = $total_questions;

                $index++;
            }
            return Helpers::responseSuccessJson(200,$exams);
        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Exams getting error"]);
        }

    }

    public function getExamDetail($event_id, $exam_id){
        try{
            $exam = EventExams::where('event_id',$event_id)
                ->where('id',$exam_id)
                ->where('publish',1)
                ->first();
            if(!$exam) return Helpers::responseErrorJson(404,["exam can not found"]);

            $completed_exam = UserCompletedExams::where('event_id',$event_id)->where('exam_id',$exam_id)
                ->where('user_id',$this->request->user_id)
                ->first();
            if(!$completed_exam) $exam->is_completed=false;
            else $exam->is_completed=true;

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

            $exam->user_answers=[];
            if($exam->is_completed){
                if($exam->type==1){
                    $user_answers = UserTestAnswers::select('question_id','answer_id')->where('exam_id',$exam->id)->where('user_id',$this->request->user_id)->get();
                    $exam->user_answers =$user_answers;
                }elseif ($exam->type==0){
                    $user_answers = UserClassicAnswers::select('question_id','answer')->where('exam_id',$exam->id)->where('user_id',$this->request->user_id)->get();
                    $exam->user_answers = $user_answers;
                }
            }

            return Helpers::responseSuccessJson(200,$exam);

        }catch (\Exception $e){
            return Helpers::responseErrorJson(500,["Questions getting error"]);
        }
    }


    public function completeExam($event_id,$exam_id){

        $validator = Validator::make(
            $this->request->only(["answers"]), [
                "answers" => "required"
            ]
        );

        if ($validator->fails())
            return Helpers::responseErrorJson(400, $validator->errors()->all());


        try{
            $exam = EventExams::where('event_id',$event_id)
                ->where('id',$exam_id)
                ->where('publish',1)
                ->first();
            if(!$exam) return Helpers::responseErrorJson(404,["exam can not found"]);

            $completed_exam = UserCompletedExams::where('event_id',$event_id)->where('exam_id',$exam_id)
                ->where('user_id',$this->request->user_id)
                ->first();
            if($completed_exam) return Helpers::responseErrorJson(400,["already completed"]);

            $answers = $this->request->answers;

            if(!is_array($answers)) return Helpers::responseErrorJson(400,["invalid answers format"]);


            try{
                $index=0;
                foreach ($answers as $answer){
                    $tmp = $answer["question_id"];
                    if((int)$exam->type===0){
                        $tmp = $answer["answer"];
                    }else if((int)$exam->type===1){
                        $tmp = $answer["answer_id"];
                    }else{
                        return Helpers::responseErrorJson(500,["invalid event type at server"]);
                    }
                    $index++;
                }
            }catch (\Exception $e){
                return Helpers::responseErrorJson(400,["invalid answers type"]);
            }

            DB::beginTransaction();
            $completed_exam = new UserCompletedExams;
            $completed_exam->event_id = $event_id;
            $completed_exam->exam_id = $exam_id;
            $completed_exam->user_id = $this->request->user_id;
            $completed_exam->save();

            //0 klasik //1 test
            if((int)$exam->type===0){
                $index=0;
                foreach ($answers as $answer){
                    $ans = new UserClassicAnswers;
                    $ans->event_id=$event_id;
                    $ans->exam_id=$exam_id;
                    $ans->user_id=$this->request->user_id;
                    $ans->question_id=$answer["question_id"];
                    $ans->answer=$answer["answer"];
                    $ans->save();
                    $index++;
                }
            }else if((int)$exam->type===1){
                $index=0;
                foreach ($answers as $answer){
                    $ans = new UserTestAnswers;
                    $ans->event_id=$event_id;
                    $ans->exam_id=$exam_id;
                    $ans->user_id=$this->request->user_id;
                    $ans->question_id=$answer["question_id"];
                    $ans->answer_id=$answer["answer_id"];
                    $ans->save();
                    $index++;
                }

            }else{
                return Helpers::responseErrorJson(400,["event type error"]);
            }

            DB::commit();
            return Helpers::responseSuccessJson(201,["message"=>"successfully"]);

        }catch (\Exception $e){
            DB::rollBack();
            return Helpers::responseErrorJson(500,["exam complete error".$e]);
        }
    }




}
