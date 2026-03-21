<?php


namespace App\Modules\PaymentModule\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

Builder::macro('if', function ($condition, $column, $operator, $value) {
    if ($condition) {
        return $this->where($column, $operator, $value);
    }
    return $this;
});

class Parameters extends Controller
{
    protected $user_id;
    protected $user_email;

    public function __construct()
    {

        $this->middleware(function ($request, $next) {
            $this->user_id = \Auth::user()->id;
            $this->user_email = \Auth::user()->email;
            $this->dms_id = \Auth::user()->dms_id;
            return $next($request);
        });
    }

    public function getSchool_termsParam()
    {

        $qry = DB::table('school_terms');
        $data = $qry->get();
        getParamsdata($qry);

    }

    public function getschool_feessetupDetails2($year,$term_id,$school_id,$school_type_id,$running_agency) {// gewel 2
        // $year = $req->input('year_of_enrollment');
        // $term_id = $req->input('term_id');
        // $school_id = $req->input('school_id');
        // $school_type_id = $req->input('school_type_id');
        // $running_agency= $req->input('running_agency');
        try {
            //job 17/3/2022
            $running_agency_details=Db::table('school_running_agencies')
            // ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,wb_fees,d_fees')->get()->toArray();
                ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,d_fees,grade_nine_twelve')->get()->toArray();
            //dd($running_agency_details);
            //end 
            $qry_grade = DB::table('school_grades')
                ->whereIn('id', array(4, 5, 6, 7, 8, 9, 10, 11, 12))
                ->get();
            $results_grade = convertStdClassObjToArray($qry_grade);
            $dataset = array();
            $data = array();
            if (count($results_grade) > 0) {
                $enrollments = getSchoolenrollments($school_type_id, true);
                foreach ($results_grade as $rec_grade) {
                    $grade_id = $rec_grade['id'];
                    $data['school_grade'] = $grade_id;
                    if(count($running_agency_details)>0) {
                        $data['varied_fees']=$running_agency_details[0]->varied_fees;// 2 means should be constant
                        $data['d_fees']=$running_agency_details[0]->d_fees;
                        $data['b_fees']=$running_agency_details[0]->b_fees;
                        $data['grade_nine_twelve']=$running_agency_details[0]->grade_nine_twelve;
                        //$data['wb_fees']=$running_agency_details[0]->wb_fees;
                    }
                    $enrolldata = array();
                    foreach ($enrollments as $enrols) {
                        $enrollment_type_id = $enrols->school_enrollment_id;
                        $enrollment_type_code = $enrols->code;
                        $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
                        $enrolldata['.' . 1 . $enrollment_type_code] = $fees['term1_fees'];
                        // $enrolldata['.' . 2 . $enrollment_type_code] = $fees['term2_fees'];
                        // $enrolldata['.' . 3 . $enrollment_type_code] = $fees['term3_fees'];
                        $enrolldata['.' . 2 . $enrollment_type_code] = 0;
                        $enrolldata['.' . 3 . $enrollment_type_code] = 0;
                        $data = array_merge($data, $enrolldata);
                    }                   
                    $dataset[] = $data;
                }
            }
            //get running agency

            $res = array(
                'success' => true,
                'results' => $dataset,
                'message' => 'All is well'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }

    private function getStudentStatus($symbol)
    {
        $value="";
        switch($symbol)
        {
            case ".3d":
            case ".2d":
            case ".1d":
                $value="Day Scholar";
                break;
            case ".1w":
            case ".2w":
            case ".3w":
                $value="Weekly Boarder";
                break;
            case ".1b":
            case ".2b":
            case ".3b":
                $value="Boarder";
                break;
            default:
             $value="";

        }

        return $value;
    }
    

    public function ValidateFeeSetup2($year,$term_id,$school_id,$school_type_id,$batch_id,$data) {// gewel 2
        $running_agency_id=Db::table('school_information')->where('id',$school_id)->value('running_agency_id');
        $data=convertStdClassObjToArray($data);
        $school_fees_data=$data;
        $new_data=[];         
        if(array_key_exists(0,$data)) {
            foreach($data as $all_fees) {            
                $new_data[]=array(
                    "school_grade"=>$all_fees['school_grade'], 
                    ".1b"=>$all_fees['.1b'],
                    ".2b"=>$all_fees['.2b'],
                    ".3b"=>$all_fees['.3b'],
                    ".1d"=>$all_fees['.1d'],
                    ".2d"=>$all_fees['.3d'],
                    ".3d"=>$all_fees['.3d'],
                    ".1w"=>$all_fees['.1w'],
                    ".2w"=>$all_fees['.2w'],
                    ".3w"=>$all_fees['.3w']
                );
            }
        }else{
            $all_fees=$data;
            $new_data[]=array(
                "school_grade"=>$all_fees['school_grade'], 
                ".1b"=>$all_fees['.1b'],
                // ".2b"=>$all_fees['.2b'],
                // ".3b"=>$all_fees['.3b'],
                ".1d"=>$all_fees['.1d'],
                // ".2d"=>$all_fees['.3d'],
                // ".3d"=>$all_fees['.3d'],
                ".1w"=>$all_fees['.1w'],
                // ".2w"=>$all_fees['.2w'],
                // ".3w"=>$all_fees['.3w']
            );  
        }
        $school_fees_data=$new_data;
        // $grade_fees_key=[8=>0,9=>1,10=>2,11=>3,12=>4];
        $grade_fees_key=[8=>0, 9=>1, 10=>2, 11=>3, 12=>4, 8=>5, 9=>6, 10=>7, 11=>8, 12=>9];
        //$grades = [8,9,10,11,12];

        // $weekly_boarders=['.1w','.2w','.3w'];
        // $dayscholars = ['.1d','.2d','.3d'];
        // $boarders=['.1b','.2b','.3b'];
        
        $weekly_boarders=['.1w'];
        $dayscholars = ['.1d'];
        $boarders=['.1b'];

        $columnsWithErrors= [];
        $grades=[];//load available grades from grid
        foreach($new_data as $new_data_data) {
            $grades[]=$new_data_data['school_grade'];
        }
        foreach($grades as $grade_key=>$grade) {
            $fees_for_agency=Db::table('school_running_agencies')->where('id',$running_agency_id)->selectraw('varied_fees,grade_nine_twelve,d_fees,b_fees')->get();
            $fees_for_agency= $fees_for_agency[0];
            //$fees_setup= $school_fees_data[$grade_fees_key[$grade]];
            $fees_setup= $school_fees_data[$grade_key];
            $normalg=[8,10,11];
            $bad_keys=["school_grade","varied_fees","d_fees","b_fees","grade_nine_twelve"];
            // $good_keys = [".1b",".2b",".3b",".1d",".2d",".3d",'.1w','.2w','.3w'];
            $good_keys = [".1b",".1d",'.1w'];
            $key_term=[
                ".1b"=>"Term 1",
                // ".2b"=>"Term 2",
                // ".3b"=>"Term 3",
                ".1d"=>"Term 1",
                // ".2d"=>"Term 2",
                // ".3d"=>"Term 3",
                ".1w"=>"Term 1",
                // ".2w"=>"Term 2",
                // ".3w"=>"Term 3"
            ];
            foreach($fees_setup as $key=>$fee) {
                if(in_array($key,$good_keys)) {
                    if(in_array($grade,$normalg)) {
                        if($fees_for_agency->varied_fees==2 ) {                       
                            if(in_array($key,$dayscholars)) {                           
                                if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee) {
                                    $resan=1;
                                }else{                              
                                    $term = $key_term[$key];
                                    $columnsWithErrors[]="Grade:".$grade." ".$term." ".$this->getStudentStatus($key).",expected:".$fees_for_agency->d_fees."<br>";
                                }     
                            }
                            if(in_array($key,$boarders)) {
                                if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee) {
                                    $resan=1;                          
                                }else{
                                    $term = $key_term[$key];                            
                                    //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                                    $columnsWithErrors[]="Grade:".$grade." ".$term. " ".$this->getStudentStatus($key).",expected:".$fees_for_agency->b_fees."<br>";
                                }
                            }
                        }
                    }else{
                        if( ($fees_for_agency->grade_nine_twelve==2 || $fees_for_agency->grade_nine_twelve===2)//doesnt allow varying of grade and 12 fees
                          && ($fees_for_agency->varied_fees==2 || $fees_for_agency->varied_fees===2 )//doesnt allow varying for all grades
                        ) {
                            if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee) {
                                $resan=1;
                            }else{
                                $term = $key_term[$key];
                                $columnsWithErrors[]="Grade:".$grade." ".$term." " .$this->getStudentStatus($key).",expected:".$fees_for_agency->d_fees."<br>";;
                            }
                            if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee) {
                                $resan=1;
                            }else{                               
                                $term = $key_term[$key];
                                $columnsWithErrors[]="Grade:".$grade." ".$term." " .$this->getStudentStatus($key).",expected:".$fees_for_agency->b_fees."<br>";;
                            }
                        }
                    }                    
                }
            }
        }
        return $columnsWithErrors;
    }

    public function ValidateFeeSetup($year,$term_id,$school_id,$school_type_id,$batch_id)
    {
        // ($school_id, $enrollment_type_id, $grade_id, $year

        // $where = array(
        //     'school_enrollment_id' => $enrollment_type_id,
        //     'year' => $year,
        //     'school_id' => $school_id,
        //     'grade_id' => $grade_id
        // );

       
        $running_agency_id=Db::table('school_information')->where('id',$school_id)->value('running_agency_id');
        $results=$this->getschool_feessetupDetails2($year,$term_id,$school_id,$school_type_id,$running_agency_id);
        $school_fees_data=$results['results'];
        $grade_fees_key=[8=>0,9=>1,10=>2,11=>3,12=>4];
        $grades = [8,9,10,11,12];
        $weekly_boarders=['.1w','.2w','.3w'];
        $dayscholars = ['.1d','.2d','.3d'];
        $boarders=['.1b','.2b','.3b'];
        foreach($grades as $grade)
        {
            $fees_for_agency=Db::table('school_running_agencies')->where('id',$running_agency_id)->selectraw('varied_fees,grade_nine_twelve,d_fees,b_fees')->get();
            $fees_for_agency= $fees_for_agency[0];
            $fees_setup= $school_fees_data[$grade_fees_key[$grade]];
            $normalg=[8,10,11];
            $bad_keys=["school_grade","varied_fees","d_fees","b_fees","grade_nine_twelve"];
            foreach($fees_setup as $key=>$fee)
            {
              
            //    if($key!="school_grade" || $key!="varied_fees" || $key!="b_fees" || $key!="d_fees" || $key!="grade_nine_twelve" ||
            //    $key!=="school_grade" || $key!=="varied_fees" || $key!=="b_fees" || $key!=="d_fees" || $key!=="grade_nine_twelve")
            //      {
                    if(!in_array($key,$bad_keys)){

                   // if($grade!=9 || $grade!=12)
                   // {
                    if(in_array($grade,$normalg)){

                       
                     if($fees_for_agency->varied_fees==2 )
                     {
                       
                        if(in_array($key,$dayscholars))
                        {
                           
                            if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee)
                            {
                                //dd("h");
                                $resan=1;
                                //return 1;
                            }else{
                                //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                                return 0;
                            }

                                // if($fees_for_agency->d_fees!=$fee)
                                // {
                                //    dd("h1");
                                    
                                //     return false;
                                   

                                // }
     
                        }

                        if(in_array($key,$boarders))
                        {
                            
                               
                           // dd(gettype($fees_for_agency->b_fees)."-".gettype($fee));
                           if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee)
                           {
                            //dd("h2");
                            $resan=1;
                            //return 1;
                           }else{
                            //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                            //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                               return 0;
                           }
                                
                                // if($fees_for_agency->b_fees!=$fee)
                                // {
                                //     dd("h111");
                                //     dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                                //    return false;
                                   

                                // }
     
                        }

                     }
                    }else{
                        if( ($fees_for_agency->grade_nine_twelve==2 || $fees_for_agency->grade_nine_twelve===2)
                          && ($fees_for_agency->varied_fees==2 || $fees_for_agency->varied_fees===2 )
                        )
                        {

                            if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee)
                            {
                                //dd("h3");
                                $resan=1;
                                //return 1;
                            }else{
                               // dd("hhhh");
                                   // dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                                return 0;
                            }
                            // if($fees_for_agency->d_fees!=$fee)
                            // {
                            //     dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                            //     dd("h11111");
                            //     return false;
                                

                            // }
                            if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee)
                            {
                                //dd("h4");
                                $resan=1;
                                //return 1;
                            }else{
                                //dd("h11hhh");
                                //dd($key."-".$grade."-".$fees_for_agency->b_fees."-".$fee);
                                return 0;
                            }
                            // if($fees_for_agency->b_fees!=$fee)
                            // {
                            //     dd("h1111");
                            //     return false;
                               
                            // }
                            
                        }
                    }
                     //dd($fees_for_agency);
                 }
            }
        }
        return 1;
    }

    public function getschool_feessetupDetails3(Request $req){//gewel 2
        // $year = $req->input('year_of_enrollment');
        // $term_id = $req->input('term_id');
        // $school_id = $req->input('school_id');
        // $school_type_id = $req->input('school_type_id');
        // $running_agency= $req->input('running_agency');
        // $isValidation = $req->input('isValidation');
        // $batch_id="";
        // $running_agency_id=Db::table('school_information')->where('id',$school_id)->value('running_agency_id');
        // $results=$this->getschool_feessetupDetails2($year,$term_id,$school_id,$school_type_id,$running_agency_id);
        // $school_fees_data=$results['results'];
        // // $grade_fees_key=[8=>0,9=>1,10=>2,11=>3,12=>4];
        // // $grades = [8,9,10,11,12];
        // // $weekly_boarders=['.1w','.2w','.3w'];
        // // $dayscholars = ['.1d','.2d','.3d'];
        // // $boarders=['.1b','.2b','.3b'];
        // $grade_fees_key=[4=>0, 5=>1, 6=>2, 7=>3, 8=>4, 9=>5, 10=>6, 11=>7, 12=>8];
        // $grades = [4,5,6,7,8,9,10,11,12];
        // $weekly_boarders=['.1w'];
        // $dayscholars = ['.1d'];
        // $boarders=['.1b'];
        // // foreach($grades as $grade){
        // //     $fees_for_agency=Db::table('school_running_agencies')
        // //         ->where('id',$running_agency_id)
        // //         ->selectraw('varied_fees,grade_nine_twelve,d_fees,b_fees')->get();
        // //     if(Arr::exists($fees_for_agency, 0)){
        // //         $fees_for_agency = $fees_for_agency[0];
        // //         $fees_setup = $school_fees_data[$grade_fees_key[$grade]];
        // //         $normalg = [8,10,11];
        // //         $bad_keys  =["school_grade","varied_fees","d_fees","b_fees","grade_nine_twelve"];
        // //         foreach($fees_setup as $key => $fee){
        // //             if(!in_array($key,$bad_keys)){
        // //                 if(in_array($grade,$normalg)){
        // //                     if($fees_for_agency->varied_fees==2 ){
        // //                         if(in_array($key,$dayscholars)){
        // //                             if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee){
        // //                                 $resan=1;
        // //                             }else{
        // //                                 dd('if 1');
        // //                                 return 0;
        // //                             }
        // //                         }
        // //                         if(in_array($key,$boarders)){
        // //                             if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee){
        // //                                 $resan=1;
        // //                             }else{
        // //                                 dd('if 2');
        // //                                 return 0;
        // //                             }
        // //                         }
        // //                     }
        // //                 }else{
        // //                     if( ($fees_for_agency->grade_nine_twelve==2 || $fees_for_agency->grade_nine_twelve===2)
        // //                     && ($fees_for_agency->varied_fees==2 || $fees_for_agency->varied_fees===2 )
        // //                     ){
        // //                         if($fees_for_agency->d_fees==$fee || $fees_for_agency->d_fees===$fee){
        // //                             $resan=1;
        // //                         }else{
        // //                             dd('if 3');
        // //                             return 0;
        // //                         }
        // //                         if($fees_for_agency->b_fees==$fee || $fees_for_agency->b_fees===$fee){
        // //                             $resan=1;
        // //                         }else{
        // //                             dd('if 4');
        // //                             return 0;
        // //                         }
        // //                     }
        // //                 }                    
        // //             }
        // //         }
        // //     }
        // // }
        return 1;
    }

    // public function getschool_feessetupDetails(Request $req){//gewel 2
    //     $year = $req->input('year_of_enrollment');//from maincontroller
    //     $term_id = $req->input('term_id');
    //     $school_id = $req->input('school_id');
    //     $school_type_id = $req->input('school_type_id');
    //     $running_agency= $req->input('running_agency');       
    //     try {
    //         //job 17/3/2022
    //         $running_agency_details=Db::table('school_running_agencies')
    //         // ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,wb_fees,d_fees')->get()->toArray();
    //             ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,d_fees,grade_nine_twelve')->get()->toArray();
    //         //dd($running_agency_details);
    //         //end 
    //         $qry_grade = DB::table('school_grades')
    //             // ->whereIn('id', array(8, 9, 10, 11, 12))
    //             ->whereIn('id', array(4, 5, 6, 7, 8, 9, 10, 11, 12))
    //             ->get();
    //         $results_grade = convertStdClassObjToArray($qry_grade);
    //         $dataset = array();
    //         $data = array();
    //         if (count($results_grade) > 0) {
    //             $enrollments = getSchoolenrollments($school_type_id, true);
    //             foreach ($results_grade as $rec_grade) {
    //                 $grade_id = $rec_grade['id'];
    //                 $data['school_grade'] = $grade_id;
    //                 if(count($running_agency_details)>0) {
    //                     $data['varied_fees']=$running_agency_details[0]->varied_fees;// 2 means should be constant
    //                     $data['d_fees']=$running_agency_details[0]->d_fees;
    //                     $data['b_fees']=$running_agency_details[0]->b_fees;
    //                     $data['grade_nine_twelve']=$running_agency_details[0]->grade_nine_twelve;
    //                     //$data['wb_fees']=$running_agency_details[0]->wb_fees;
    //                 }                    
    //                 $enrolldata = array();
    //                 foreach ($enrollments as $enrols) {
    //                     $enrollment_type_id = $enrols->school_enrollment_id;
    //                     $enrollment_type_code = $enrols->code;
    //                     $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
    //                     // $enrolldata['.' . 1 . $enrollment_type_code] = $fees['term1_fees'];
    //                     // $enrolldata['.' . 2 . $enrollment_type_code] = $fees['term2_fees'];
    //                     // $enrolldata['.' . 3 . $enrollment_type_code] = $fees['term3_fees'];
    //                     $enrolldata['.' . 1 . $enrollment_type_code] = 0;
    //                     $enrolldata['.' . 2 . $enrollment_type_code] = $fees['term2_fees'];
    //                     $enrolldata['.' . 3 . $enrollment_type_code] = 0;
    //                     $data = array_merge($data, $enrolldata);
    //                 }                   
    //                 $dataset[] = $data;
    //             }
    //         }
    //         //get running agency
    //         $res = array(
    //             'success' => true,
    //             'results' => $dataset,
    //             'message' => 'All is well'
    //         );
    //     } catch (\Exception $exception) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         );
    //     } catch (\Throwable $throwable) {
    //         $res = array(
    //             'success' => false,
    //             'message' => $throwable->getMessage()
    //         );
    //     }
    //     return response()->json($res);
    // }


    public function getschool_feessetupDetails(Request $req)
    // public function getschool_feessetupDetails($year=null,$term_id=null,$school_id=null,$school_type_id=null,$running_agency=null)
    {
        $year = $req->input('year_of_enrollment');
        $batch_id = $req->input('batch_id');
        $term_id = 3;
        $school_id = $req->input('school_id');
        $school_type_id = $req->input('school_type_id');
        $running_agency= $req->input('running_agency');
        try {
            $term_id = 3;

            //job 17/3/2022
           $running_agency_details=Db::table('school_running_agencies')
          // ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,wb_fees,d_fees')->get()->toArray();
           ->where('id',$running_agency)->selectRaw('varied_fees,b_fees,d_fees,grade_nine_twelve')->get()->toArray();
           //dd($running_agency_details);
            //end 
            $qry_grade = DB::table('school_grades')
                ->whereIn('id', array(4, 5, 6, 7, 8, 9, 10, 11, 12))
                ->get();
            $results_grade = convertStdClassObjToArray($qry_grade);
            $dataset = array();
            $data = array();
            // $res=$this->ValidateFeeSetup($year,$term_id,$school_id,$school_type_id,$batch_id);
            if (count($results_grade) > 0) {
                $enrollments = getSchoolenrollments($school_type_id, true);
                foreach ($results_grade as $rec_grade) {
                    $grade_id = $rec_grade['id'];
                    $data['school_grade'] = $grade_id;
                    if(count($running_agency_details)>0)
                    {
                        $data['varied_fees']=$running_agency_details[0]->varied_fees;// 2 means should be constant
                        $data['d_fees']=$running_agency_details[0]->d_fees;
                        $data['b_fees']=$running_agency_details[0]->b_fees;
                        $data['grade_nine_twelve']=$running_agency_details[0]->grade_nine_twelve;
                        //$data['wb_fees']=$running_agency_details[0]->wb_fees;
                    }
                    
                    $enrolldata = array();
                    foreach ($enrollments as $enrols) {
                        $enrollment_type_id = $enrols->school_enrollment_id;
                        $enrollment_type_code = $enrols->code;
                        $fees = getAnnualSchoolFees($school_id, $enrollment_type_id, $grade_id, $year);
                        // $enrolldata['.' . 1 . $enrollment_type_code] = $fees['term1_fees'];
                        // $enrolldata['.' . 2 . $enrollment_type_code] = $fees['term2_fees'];
                        // $enrolldata['.' . 3 . $enrollment_type_code] = $fees['term3_fees'];
                        $enrolldata['.' . '3d'] = $fees['day_fee'];
                        $enrolldata['.' . '3b'] = $fees['boarder_fee'];
                        $enrolldata['.' . '3w'] = $fees['weekly_boarder_fee'];
                        $data = array_merge($data, $enrolldata);
                    }
                   
                    $dataset[] = $data;
                }
            }
            //get running agency

            $res = array(
                'success' => true,
                'results' => $dataset,
                'message' => 'All is well'
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }

    function funcUpdateschoolFees($table_name, $where_data, $table_data) {
        $qry = DB::table($table_name)
            ->where($where_data)
            ->get();
        if (count($qry) > 0) {
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            // $previous_data = getPreviousRecords($table_name, $where_data);
            // $success = updateRecord($table_name, $previous_data, $where_data, $table_data, $this->user_id);
            $success = DB::table($table_name)->where($where_data)->update($table_data);
        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            // $success = insertRecord($table_name, $table_data, $this->user_id);
            $success = DB::table($table_name)->insert($table_data);
        }
    }
    
    // public function saveschool_feessetupDetails(Request $req) {//GEWEL 2
    //     //save the data
    //     $year_of_enrollment = $req->input('year_of_enrollment');
    //     $term_id = $req->input('term_id');
    //     $school_id = $req->input('school_id');
    //     $batch_id = $req->input('batch_id');
    //     $school_type_id= $req->input('school_type_id');
    //     $postdata = file_get_contents("php://input");
    //     $data = json_decode($postdata);
    //     //job on 28/03/2022 - 02/04/2022
    //     // $res=$this->ValidateFeeSetup2($year_of_enrollment,$term_id,$school_id,$school_type_id,$batch_id,$data);
    //     // if(count($res)>0) {
    //     //     $res=implode("",$res);
    //     //     return response()->json([
    //     //         "success"=>false,
    //     //         "message"=> $res
    //     //     ]);
    //     // }
    //     // the post data is array thus handle as array data
    //     if (is_array($data)) {
    //         $data = json_decode($postdata, true);
    //     } else {
    //         $data = array();
    //         $data[] = json_decode($postdata, true);
    //     }
    //     $school_running_agency=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
    //     $weekly_border_plus = getWeeklyBordersTopUpAmount();
    //     foreach ($data as $key => $value) {
    //         //values in place
    //         $school_grade = $value['school_grade'];
    //         $term1_day_fees = 0;    $term1_border_fees = 0;     $term1_weekly_fees = 0;
    //         $term2_day_fees = 0;    $term2_border_fees = 0;     $term2_weekly_fees = 0;
    //         $term3_day_fees = 0;    $term3_border_fees = 0;     $term3_weekly_fees = 0;
    //         //todo term 1
    //         if (isset($value['.1d'])) {
    //             $term1_day_fees = $value['.1d'];
    //             if ($term1_day_fees >= 0) {
    //                 $term1_weekly_fees = $term1_day_fees + $weekly_border_plus;
    //             }
    //             //job on 29/03/2022
    //             if($school_running_agency==3) {//private               
    //                 $term1_weekly_fees=0;
    //             }
    //         }
    //         if (isset($value['.1b'])) {
    //             $term1_border_fees = $value['.1b'];
    //         }
    //         // //todo term 2
    //         // if (isset($value['.2d'])) {
    //         //     $term2_day_fees = $value['.2d'];
    //         //     if ($term2_day_fees >= 0) {
    //         //         $term2_weekly_fees = $term2_day_fees + $weekly_border_plus;
    //         //     } //job on 29/03/2022
    //         //     if($school_running_agency==3) {//private               
    //         //         $term2_weekly_fees=0;
    //         //     }
    //         // }
    //         // if (isset($value['.2b'])) {
    //         //     $term2_border_fees = $value['.2b'];
    //         // }

    //         // //todo term 3
    //         // if (isset($value['.3d'])) {
    //         //     $term3_day_fees = $value['.3d'];
    //         //     if ($term3_day_fees >= 0) {
    //         //         $term3_weekly_fees = $term3_day_fees + $weekly_border_plus;
    //         //     }
    //         //     //job on 29/03/2022
    //         //     if($school_running_agency==3)//private 
    //         //     {
    //         //     $term3_weekly_fees =0;
    //         //     }
    //         // }
    //         // if (isset($value['.3b'])) {
    //         //     $term3_border_fees = $value['.3b'];
    //         // }

    //         //todo updates
    //         $table_name = 'school_feessetup';
    //         //1. Day
    //         $table_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 1,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade,
    //             'term1_fees' => $term1_day_fees,
    //             'term2_fees' => $term2_day_fees,
    //             'term3_fees' => $term3_day_fees
    //         );
    //         $where_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 1,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade
    //         );
    //         $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
    //         //2. Border
    //         $table_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 2,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade,
    //             'term1_fees' => $term1_border_fees,
    //             'term2_fees' => $term2_border_fees,
    //             'term3_fees' => $term3_border_fees
    //         );
    //         $where_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 2,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade
    //         );
    //         $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
    //         //3. Weekly
    //         $table_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 3,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade,
    //             'term1_fees' => $term1_weekly_fees,
    //             'term2_fees' => $term2_weekly_fees,
    //             'term3_fees' => $term3_weekly_fees
    //         );
    //         $where_data = array(
    //             'year' => $year_of_enrollment,
    //             'school_enrollment_id' => 3,
    //             'school_id' => $school_id,
    //             'grade_id' => $school_grade
    //         );
    //         $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
    //     }
    //     $resp = array('success' => true, 'message' => 'School fees Details updated successfully');
    //     return response()->json($resp);
    // }
    
    function funcSaveTermlyFees($table_name, $where_data, $table_data) {
        //todo term 1
        // if (isset($value['.1d'])) {
        //     $term1_day_fees = $value['.1d'];
        //     if ($term1_day_fees >= 0) {
        //         $term1_weekly_fees = $term1_day_fees + $weekly_border_plus;
        //     }
        //     //job on 29/03/2022
        //     if($school_running_agency==3) {//private               
        //         $term1_weekly_fees=0;
        //     }
        // }
        // if (isset($value['.1b'])) {
        //     $term1_border_fees = $value['.1b'];
        // }

        //todo term 2
        if (isset($value['.2d'])) {
            $term2_day_fees = $value['.2d'];
            if ($term2_day_fees >= 0) {
                $term2_weekly_fees = $term2_day_fees + $weekly_border_plus;
            } //job on 29/03/2022
            if($school_running_agency==3) {//private               
                $term2_weekly_fees=0;
            }
        }
        if (isset($value['.2b'])) {
            $term2_border_fees = $value['.2b'];
        }

        // //todo term 3
        // if (isset($value['.3d'])) {
        //     $term3_day_fees = $value['.3d'];
        //     if ($term3_day_fees >= 0) {
        //         $term3_weekly_fees = $term3_day_fees + $weekly_border_plus;
        //     }
        //     //job on 29/03/2022
        //     if($school_running_agency==3)//private 
        //     {
        //     $term3_weekly_fees =0;
        //     }
        // }
        // if (isset($value['.3b'])) {
        //     $term3_border_fees = $value['.3b'];
        // }

        //todo updates
        $table_name = 'school_feessetup';
        //1. Day
        $table_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 1,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term_id' => $term_id,
            'term1_fees' => 0,
            'term2_fees' => $term2_day_fees,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 1,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
        //2. Border
        $table_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 2,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term_id' => $term_id,
            'term1_fees' => 0,
            'term2_fees' => $term2_border_fees,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 2,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
        //3. Weekly
        $table_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 3,
            'school_id' => $school_id,
            'grade_id' => $school_grade,
            'term_id' => $term_id,
            'term1_fees' => 0,
            'term2_fees' => $term2_weekly_fees,
            'term3_fees' => 0
        );
        $where_data = array(
            'year' => $year_of_enrollment,
            'school_enrollment_id' => 3,
            'school_id' => $school_id,
            'grade_id' => $school_grade
        );
        $this->funcUpdateschoolFees($table_name, $where_data, $table_data);

        $qry = DB::table($table_name)
            ->where($where_data)
            ->get();
        if (count($qry) > 0) {
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $this->user_id;
            $success = DB::table($table_name)->where($where_data)->update($table_data);
        } else {
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $this->user_id;
            $success = DB::table($table_name)->insert($table_data);
        }
    }

    public function saveschool_feessetupDetails(Request $req) {//GEWEL 2
        //save the data
        $year_of_enrollment = $req->input('year_of_enrollment');
        $term_id = $req->input('term_id');
        // $term_id = 3;
        $school_id = $req->input('school_id');
        $batch_id = $req->input('batch_id');
        $school_type_id= $req->input('school_type_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        //job on 28/03/2022 - 02/04/2022
        // $res=$this->ValidateFeeSetup2($year_of_enrollment,$term_id,$school_id,$school_type_id,$batch_id,$data);
        // if(count($res)>0) {
        //     $res=implode("",$res);
        //     return response()->json([
        //         "success"=>false,
        //         "message"=> $res
        //     ]);
        // }
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $school_running_agency=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
        $weekly_border_plus = getWeeklyBordersTopUpAmount();
        foreach ($data as $key => $value) {
            //values in place
            $school_grade = $value['school_grade'];
            $term1_day_fees = 0;    $term1_border_fees = 0;     $term1_weekly_fees = 0;
            $term2_day_fees = 0;    $term2_border_fees = 0;     $term2_weekly_fees = 0;
            $term3_day_fees = 0;    $term3_border_fees = 0;     $term3_weekly_fees = 0;
            //todo term 1
            // if (isset($value['.1d'])) {
            //     $term1_day_fees = $value['.1d'];
            //     if ($term1_day_fees >= 0) {
            //         $term1_weekly_fees = $term1_day_fees + $weekly_border_plus;
            //     }
            //     //job on 29/03/2022
            //     if($school_running_agency==3) {//private               
            //         $term1_weekly_fees=0;
            //     }
            // }
            // if (isset($value['.1b'])) {
            //     $term1_border_fees = $value['.1b'];
            // }

            //todo term 2
            // if (isset($value['.2d'])) {
            //     $term2_day_fees = $value['.2d'];
            //     if ($term2_day_fees >= 0) {
            //         $term2_weekly_fees = $term2_day_fees + $weekly_border_plus;
            //     } //job on 29/03/2022
            //     if($school_running_agency==3) {//private               
            //         $term2_weekly_fees=0;
            //     }
            // }
            // if (isset($value['.2b'])) {
            //     $term2_border_fees = $value['.2b'];
            // }

            // //todo term 3
            if (isset($value['.3d'])) {
                $term3_day_fees = $value['.3d'];
                if ($term3_day_fees >= 0) {
                    $day_fees = $term3_day_fees;
                    $weekly_boarder_fees = $term3_day_fees + $weekly_border_plus;
                }
                //job on 29/03/2022
                if($school_running_agency==3)//private 
                {
                $term3_weekly_fees =0;
                }
            }
            if (isset($value['.3b'])) {
                $boarder_fees = $value['.3b'];
            }

            //todo updates
            $table_name = 'school_feessetup';
            //1. Day
            $table_data = array(
                'year' => $year_of_enrollment,
                'school_enrollment_id' => 1,
                'school_id' => $school_id,
                'grade_id' => $school_grade,
                'term_id' => $term_id,
                'term1_fees' => 0,
                'term2_fees' => 0,
                'day_fee' => $day_fees,
                'boarder_fee' => $boarder_fees,
                'weekly_boarder_fee' => $weekly_boarder_fees,
                'term3_fees' => $term3_day_fees
            );
            $where_data = array(
                'year' => $year_of_enrollment,
                'school_enrollment_id' => 1,
                'school_id' => $school_id,
                'term_id' => 3,
                'grade_id' => $school_grade
            );
            $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
            // //2. Border
            // $table_data = array(
            //     'year' => $year_of_enrollment,
            //     'school_enrollment_id' => 2,
            //     'school_id' => $school_id,
            //     'grade_id' => $school_grade,
            //     'term_id' => $term_id,
            //     'term1_fees' => 0,
            //     'term2_fees' => 0,
            //     'term3_fees' => $term3_border_fees,
            // );
            // $where_data = array(
            //     'year' => $year_of_enrollment,
            //     'school_enrollment_id' => 2,
            //     'school_id' => $school_id,
            //     'term_id' => 3,
            //     'grade_id' => $school_grade
            // );
            // $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
            // //3. Weekly
            // $table_data = array(
            //     'year' => $year_of_enrollment,
            //     'school_enrollment_id' => 3,
            //     'school_id' => $school_id,
            //     'grade_id' => $school_grade,
            //     'term_id' => 3,
            //     'term1_fees' => 0,
            //     'term2_fees' => 0,
            //     'term3_fees' => $term3_border_fees
            // );
            // $where_data = array(
            //     'year' => $year_of_enrollment,
            //     'school_enrollment_id' => 3,
            //     'school_id' => $school_id,
            //     'grade_id' => $school_grade
            // );
            // $this->funcUpdateschoolFees($table_name, $where_data, $table_data);
        }
        $resp = array('success' => true, 'message' => 'School fees Details updated successfully');
        return response()->json($resp);
    }    

    public function getBeneficiary_school_statuses()
    {
        $qry = DB::table('beneficiary_school_statuses');
        $data = $qry->get();
        getParamsdata($qry);
    }

    public function getBeneficiariesInfo()
    {
        $qry = DB::table('beneficiary_information as t1')
            ->join('school_information as t2', 't1.school_id', '=', 't2.id')
            ->join('households as t3', 't1.household_id', '=', 't3.id')
            ->join('acc as t4', 't3.acc_id', '=', 't4.id')
            ->join('districts as t5', 't4.district_id', '=', 't5.id')
            ->select('t1.beneficiary_id as beneficiary_no', 't1.id as beneficiary_id', 't1.household_id', 't3.hhh_lname', 't1.last_name', 't4.name as hhh_village', 't5.id as hhh_district', 't5.province_id as hhh_province', 't1.first_name', 't1.last_name', 't1.dob', 't1.beneficiary_school_status', 't1.school_id', 't2.name as school_name', 't3.hhh_nrc_number', 't3.hhh_fname', 't1.current_school_grade as grade', 't1.first_name', 't1.beneficiary_school_status as enrollement_status_id', 't1.school_id')
            ->get();

        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);

    }

    public function saveParamCommonData(Request $req)
    {
        $user_id = \Auth::user()->id;
        $post_data = $req->all();
        $table_name = $post_data['table_name'];
        $id = $post_data['id'];
        $skip = $post_data['skip'];
        $skipArray = explode(",", $skip);
        //unset unnecessary values
        unset($post_data['_token']);
        unset($post_data['table_name']);
        unset($post_data['model']);
        unset($post_data['id']);
        unset($post_data['skip']);
        $table_data = $post_data;

        //add extra params
        $table_data['created_at'] = Carbon::now();
        $table_data['created_by'] = $user_id;
        $where = array(
            'id' => $id
        );
        if (isset($id) && $id != "") {
            if (recordExists($table_name, $where)) {
                unset($table_data['created_at']);
                unset($table_data['created_by']);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $success = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);

            }
        } else {
            $success = insertRecord($table_name, $table_data, $user_id);

        }
        if ($success) {
            $res = array(
                'success' => true,
                'message' => 'Data saved Successfully!!'
            );
        } else {
            $res = array(
                'success' => false,
                'message' => 'Problem encountered while saving data. Try again later!!'
            );
        }
        return response()->json($res);
    }
    //end hirams code
    
    // public function getOfflineBeneficiaryEnrollmentbatchinfo(Request $req)
    // {
    //     $res = array(
    //         'success' => true,
    //         'results' => [],
    //         'message' => 'All is well'
    //     );
    //     return response()->json($res);
    // }
    
    // public function getOfflineSchoolFeesSetup(Request $req)
    // {
    //     $res = array(
    //         'success' => true,
    //         'results' => [],
    //         'message' => 'All is well'
    //     );
    //     return response()->json($res);
    // }
     
    public function getOfflineSchoolFeesSetup(Request $req) {
        try {
            $post_data = $req->all() ;
            $school_id = isset($post_data['school_id']) ? $post_data['school_id'] : null;
            $school_id = $post_data['school_id'];
            if(isset($post_data['all_data'])) {
                $results = $self::saveOfflineSchoolFeesSetup($req);
            } else {
                if(isset($school_id)) {   
                    $term = Db::table('beneficiary_fees_staging')
                        ->select('term')->where('school_id',$school_id)->first();
                    if(isset($term->term)) {
                        if($term->term == 1) {
                            $selected_term = Db::table('beneficiary_fees_staging')
                                ->select(DB::raw('school_id,grade as school_grade, student_category'),
                                    DB::raw('COALESCE(day_fee, 0) AS `.1d`'),
                                    DB::raw('COALESCE(boarder_fee, 0) AS `.1b`'),
                                    DB::raw('COALESCE(weekly_boarder_fee, 0) AS `.1w`'),
                                    DB::raw('0 AS `.2d`'),DB::raw('0 AS `.2b`'),DB::raw('0 AS `.2w`'),
                                    DB::raw('0 AS `.3d`'),DB::raw('0 AS `.3b`'),DB::raw('0 AS `.3w`')
                                )->where('school_id',$school_id)
                                ->where(function ($query) {
                                    $query->where('student_category', 'external')
                                        ->orWhereNull('student_category')
                                        ->orWhere('student_category', 'GCE')
                                        ->orWhere('student_category', '');
                                });
                            $results = $selected_term->orderBy('school_grade','ASC')
                                ->groupBy('school_grade')->get();
                        } else if($term->term == 2) {
                            $selected_term = Db::table('beneficiary_fees_staging')
                                ->select(DB::raw('school_id,grade as school_grade, student_category'),
                                    DB::raw('0 AS `.1d`'),DB::raw('0 AS `.1b`'),DB::raw('0 AS `.1w`'),
                                    DB::raw('COALESCE(day_fee, 0) AS `.2d`'),
                                    DB::raw('COALESCE(boarder_fee, 0) AS `.2b`'),
                                    DB::raw('COALESCE(weekly_boarder_fee, 0) AS `.2w`'),
                                    DB::raw('0 AS `.3d`'),DB::raw('0 AS `.3b`'),DB::raw('0 AS `.3w`'),
                                )->where('school_id',$school_id)
                                ->where(function ($query) {
                                    $query->where('student_category', 'external')
                                        ->orWhereNull('student_category')
                                        ->orWhere('student_category', 'GCE')
                                        ->orWhere('student_category', '');
                                });
                            $results = $selected_term->groupBy('school_grade')->get(); 
                        } else if($term->term == 3) {
                            $selected_term = Db::table('beneficiary_fees_staging')
                                ->select(DB::raw('school_id,grade as school_grade, student_category'),
                                    DB::raw('0 AS `.1d`'),DB::raw('0 AS `.1b`'),DB::raw('0 AS `.1w`'),
                                    DB::raw('0 AS `.2d`'),DB::raw('0 AS `.2b`'),DB::raw('0 AS `.2w`'),
                                    DB::raw('COALESCE(day_fee, 0) AS `.3d`'),
                                    DB::raw('COALESCE(boarder_fee, 0) AS `.3b`'),
                                    DB::raw('COALESCE(weekly_boarder_fee, 0) AS `.3w`'),
                                )->where('school_id',$school_id)
                                ->where(function ($query) {
                                    $query->where('student_category', 'external')
                                        ->orWhereNull('student_category')
                                        ->orWhere('student_category', 'GCE')
                                        ->orWhere('student_category', '');
                                });
                            $results = $selected_term->groupBy('school_grade')->get(); 
                        } else {
                            $qry = DB::table('school_grades as t2')
                                ->leftJoin('beneficiary_fees_staging as t1', 't1.term', '=', 't2.id')
                                ->selectRaw("t2.name as school_grade,
                                    0 AS '.1d',0 as '.1b',0 as '.1w',
                                    0 as '.2d',0 as '.2b',0 as '.2w',
                                    0 as '.3d',0 as '.3b',0 as '.3w'")
                                ->wherein('t2.code', ['4', '5', '6', '7', '8', '9', '10', '11', '12']);
                            $results = $qry->groupBy('t2.name')->get();  
                        }
                    } else {
                        $qry = DB::table('school_grades as t2')
                            ->leftJoin('beneficiary_fees_staging as t1', 't1.term', '=', 't2.id')
                            ->selectRaw("t2.name as school_grade,
                                0 AS '.1d',0 as '.1b',0 as '.1w',
                                0 as '.2d',0 as '.2b',0 as '.2w',
                                0 as '.3d',0 as '.3b',0 as '.3w'")
                            ->wherein('t2.code', ['4', '5', '6', '7', '8', '9', '10', '11', '12']);
                        $results = $qry->groupBy('t2.name')->get(); 
                    }
                } else {
                    $qry = DB::table('school_grades as t2')
                        ->leftJoin('beneficiary_fees_staging as t1', 't1.term', '=', 't2.id')
                        ->selectRaw("t2.name as school_grade,
                            0 AS '.1d',0 as '.1b',0 as '.1w',
                            0 as '.2d',0 as '.2b',0 as '.2w',
                            0 as '.3d',0 as '.3b',0 as '.3w'")
                        ->wherein('t2.code', ['4', '5', '6', '7', '8', '9', '10', '11', '12']);
                    $results = $qry->groupBy('t2.name')->get();  
                }
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }
    
     public function saveOfflineSchoolFeesSetup(Request $req)
    {//frank
        try {
            $year_of_enrollment = $req->input('year_of_enrollment');
            $term_id = $req->input('term_id');
            $school_id = $req->input('school_id');
            $batch_id = $req->input('batch_id') ? $req->input('batch_id') : DB::table('beneficiary_metainfo_staging')
                    ->where('school_id', $school_id)->value('batch_id');
            $school_type_id= $req->input('school_type_id');        
            $postdata = $req->input('all_data');
            $data = json_decode($postdata, true);
            $school_running_agency=DB::table('school_information')->where('id',$school_id)->value('running_agency_id');
            $weekly_border_plus = getWeeklyBordersTopUpAmount();
            $table_name = 'school_feessetup';
            $where_data = array(
                'year' => $year_of_enrollment, 
                'school_id' => $school_id
                // 'grade_id' => $school_grade
            );
            $delete_res = DB::table($table_name)->where($where_data)->delete();
            foreach ($data as $key => $value) {
                //values in place
                $school_grade = $value['school_grade'];
                $term1_day_fees = 0;     $term2_day_fees = 0;     $term3_day_fees = 0;
                $term1_border_fees = 0;  $term2_border_fees = 0;  $term3_border_fees = 0;
                $term1_weekly_fees = 0;  $term2_weekly_fees = 0;  $term3_weekly_fees = 0;
                //todo term 1
                if (isset($value['.1d'])) {
                    $term1_day_fees = $value['.1d'];
                    if ($term1_day_fees >= 0) { 
                        $term1_weekly_fees = $term1_day_fees + $weekly_border_plus; 
                    }
                    //private
                    if($school_running_agency==3) { $term1_weekly_fees=0; }
                }
                if (isset($value['.1b'])) { $term1_border_fees = $value['.1b']; }
                //todo term 2
                if (isset($value['.2d'])) {
                    $term2_day_fees = $value['.2d'];
                    if ($term2_day_fees >= 0) { 
                        $term2_weekly_fees = $term2_day_fees + $weekly_border_plus; 
                    }
                    //private 
                    if($school_running_agency==3) { 
                        $term2_weekly_fees=0; 
                    }
                }
                if (isset($value['.2b'])) { 
                    $term2_border_fees = $value['.2b']; 
                }
                //todo term 3
                if (isset($value['.3d'])) { $term3_day_fees = $value['.3d'];
                    if ($term3_day_fees >= 0) { 
                        $term3_weekly_fees = $term3_day_fees + $weekly_border_plus; 
                    }
                    //private
                    if($school_running_agency==3) { 
                        $term3_weekly_fees = 0; 
                    }
                }
                if (isset($value['.3b'])) { 
                    $term3_border_fees = $value['.3b']; 
                }
                //todo updates
                //1. Day // 2. Boarders // 3. Weakly Boarders                
                $table_data = array();
                $table_data_1 = array(
                    'year' => $year_of_enrollment,          'school_enrollment_id' => 1,        'school_id' => $school_id,          'grade_id' => $school_grade,
                    'term1_fees' => $term1_day_fees,        'term2_fees' => $term2_day_fees,    'term3_fees' => $term3_day_fees,                         
                    'created_at' => Carbon::now(),          'created_by' => $this->user_id
                );
                //2. Border
                $table_data_2 = array(
                    'year' => $year_of_enrollment,          'school_enrollment_id' => 2,            'school_id' => $school_id,              'grade_id' => $school_grade,
                    'term1_fees' => $term1_border_fees,     'term2_fees' => $term2_border_fees,     'term3_fees' => $term3_border_fees,                        
                    'created_at' => Carbon::now(),          'created_by' => $this->user_id
                );
                //3. Weekly
                $table_data_3 = array(
                    'year' => $year_of_enrollment,          'school_enrollment_id' => 3,            'school_id' => $school_id,              'grade_id' => $school_grade,
                    'term1_fees' => $term1_weekly_fees,     'term2_fees' => $term2_weekly_fees,     'term3_fees' => $term3_weekly_fees,                        
                    'created_at' => Carbon::now(),          'created_by' => $this->user_id
                );

                array_push($table_data, $table_data_1);
                array_push($table_data, $table_data_2);
                array_push($table_data, $table_data_3);
                $success = DB::table($table_name)->insert($table_data);
            }
            $res = array('success' => true, 'reulsts' => true, 'message' => 'School fees Details updated successfully');
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getOfflineBeneficiaryEnrollmentbatchinfo(Request $req)//frank
    {
        try {            
            $post_data = $req->all();   
            $batch_id = isset($post_data['batch_id']) ? $post_data['batch_id'] : null;
            $school_id = isset($post_data['school_id']) ? $post_data['school_id'] : null;           
            $responses = DB::table('beneficiary_payresponses_staging_clone as t1')
                ->select('t1.responses','t1.id','t1.resps_inserted','t1.is_gce_external_candidate',
                    't1.confirmed_grade','t1.exam_fees')
                ->where(array('t1.school_id' => $school_id))
                ->whereRaw("t1.verification_status = 'pending'")->get();
            if($responses->count() > 0) {
                foreach ($responses as $response) {
                    $response_array = json_decode($response->responses, true);
                    $record_id = $response->id;
                    $category_9 = $response->is_gce_external_candidate == 1 ? 'external' : 'internal';
                    $category_12 = $response->is_gce_external_candidate == 1 ? 'GCE' : 'internal';
                    $grade = $response->confirmed_grade;
                    $resps_inserted = $response->resps_inserted;
                    $exam_fees = $response->exam_fees;
                    $where_array = array(
                        'school_id' => $school_id,
                        'grade' => $grade,
                        'term' => 1
                    );
                    if($grade == 9) { $where_array['student_category'] = $category_9; }
                    if($grade == 12) { $where_array['student_category'] = $category_12; }
                    $get1_ben_fees = DB::table('beneficiary_fees_staging as t1')
                        ->select('t1.day_fee','t1.boarder_fee','t1.weekly_boarder_fee','t1.term')
                        ->where($where_array)->first();     
                    $fst_tem_day = isset($get1_ben_fees->day_fee) ? $get1_ben_fees->day_fee : 0;
                    $fst_tem_bod = isset($get1_ben_fees->boarder_fee) ? $get1_ben_fees->boarder_fee : 0;
                    $fst_tem_wbod = isset($get1_ben_fees->weekly_boarder_fee) ? $get1_ben_fees->weekly_boarder_fee : 0;

                    $where_array['term'] = 2;
                    $get_t2ben_fees = DB::table('beneficiary_fees_staging as t1')
                        ->select('t1.day_fee','t1.boarder_fee','t1.weekly_boarder_fee','t1.term')
                        ->where($where_array)->first();
                    $sec_tem_day = isset($get_t2ben_fees->day_fee) ? $get_t2ben_fees->day_fee : 0;
                    $sec_tem_bod = isset($get_t2ben_fees->boarder_fee) ? $get_t2ben_fees->boarder_fee : 0;
                    $sec_tem_wbod = isset($get_t2ben_fees->weekly_boarder_fee) ? $get_t2ben_fees->weekly_boarder_fee : 0;

                    $where_array['term'] = 3;
                    $get_t3ben_fees = DB::table('beneficiary_fees_staging as t1')
                        ->select('t1.day_fee','t1.boarder_fee','t1.weekly_boarder_fee','t1.term')
                        ->where($where_array)->first();
                    $tat_tem_day = isset($get_t3ben_fees->day_fee) ? $get_t3ben_fees->day_fee : 0;
                    $tat_tem_bod = isset($get_t3ben_fees->boarder_fee) ? $get_t3ben_fees->boarder_fee : 0;
                    $tat_tem_wbod = isset($get_t3ben_fees->weekly_boarder_fee) ? $get_t3ben_fees->weekly_boarder_fee : 0;
                    
                    if($resps_inserted != 1 && $responses) {
                        // if($responses->count() > 0) {
                            if(!blank($response_array)) {    
                                foreach ($response_array as $resp_arr) {
                                    $update_array = null;
                                    //checklists -> 1. school status  2. renting  3. consent  4. disclaimer  5. is gce  6. is enrolled
                                    //options -> 1. Yes, 2. No, 3. Day, 4. Border, 5. Weekly Border 6. Private, 7. school managed 
                                    if($resp_arr['checklist_id'] == 1) {// 1. school status
                                        if ($resp_arr['option_id'] == 3) { // Day Scholar 1
                                            $update_array['beneficiary_schoolstatus_id'] = 1;
                                            $update_array['term1_fees'] = $fst_tem_day;
                                            $update_array['term2_fees'] = $sec_tem_day;
                                            $update_array['term3_fees'] = $tat_tem_day;
                                            $update_array['annual_fees'] = ($fst_tem_day + $sec_tem_day + $tat_tem_day + $exam_fees);
                                        } else if ($resp_arr['option_id'] == 4) { // Boarder 2
                                            $update_array['beneficiary_schoolstatus_id'] = 2;
                                            $update_array['term1_fees'] = $fst_tem_bod;
                                            $update_array['term2_fees'] = $sec_tem_bod;
                                            $update_array['term3_fees'] = $tat_tem_bod;
                                            $update_array['annual_fees'] = ($fst_tem_bod + $sec_tem_bod + $tat_tem_bod + $exam_fees);
                                        } else if ($resp_arr['option_id'] == 5) { // Weekly Boarder 3
                                            $update_array['beneficiary_schoolstatus_id'] = 3;
                                            $update_array['term1_fees'] = $fst_tem_wbod;
                                            $update_array['term2_fees'] = $sec_tem_wbod;
                                            $update_array['term3_fees'] = $tat_tem_wbod;
                                            $update_array['annual_fees'] = ($fst_tem_wbod + $sec_tem_wbod + $tat_tem_wbod + $exam_fees);
                                        }
                                    } else
                                    if($resp_arr['checklist_id'] == 2) {// 2. renting
                                        if ($resp_arr['option_id'] == 7) { // School Managed 1
                                            $update_array['wb_facility_manager_id'] = 1;
                                        } else if ($resp_arr['option_id'] == 6) { // Private 3
                                            $update_array['wb_facility_manager_id'] = 3;
                                        }
                                    } else
                                    if($resp_arr['checklist_id'] == 3) {// 3. consent
                                        if ($resp_arr['option_id'] == 1) { // Yes 1
                                            $update_array['has_signed_consent'] = 1;
                                        } else if ($resp_arr['option_id'] == 2) { // No 0
                                            $update_array['has_signed_consent'] = 0;
                                        }
                                    } else
                                    if($resp_arr['checklist_id'] == 4) {// 4. disclaimer
                                        if ($resp_arr['option_id'] == 1) { // Yes 1
                                            $update_array['has_signed_disclaimer'] = 1;
                                        } else if ($resp_arr['option_id'] == 2) { // No 0
                                            $update_array['has_signed_disclaimer'] = 0;
                                        }
                                    } else
                                    if($resp_arr['checklist_id'] == 5) {// 5. is gce
                                        if ($resp_arr['option_id'] == 1) { // Yes 1
                                            $update_array['is_gce_external_candidate'] = 1;
                                        } else if ($resp_arr['option_id'] == 2) { // No 0
                                            $update_array['is_gce_external_candidate'] = 0;
                                        }
                                    } else
                                    if($resp_arr['checklist_id'] == 6) {// 6. is enrolled
                                        if ($resp_arr['option_id'] == 1) { // Yes 1
                                            $update_array['enrollment_status_id'] = 1;
                                        } else if ($resp_arr['option_id'] == 2) { // No 0
                                            $update_array['enrollment_status_id'] = 0;
                                        }
                                    }
                                    if($update_array) {
                                        DB::table('beneficiary_payresponses_staging_clone')
                                            ->where('id', $record_id)
                                            ->update($update_array);
                                    }
                                }
                            }
                        // }
                    }
                }
            }
            
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t1.id,t1.id as girl_id,t4.name as home_district,t5.parent_phone as mobile_phone_number_for_parent_guardian,
                    t5.cwac_phone as mobile_phone_number_for_cwac_contact_person,t1.beneficiary_id as beneficiary_no,
                    t5.surname as last_name,IF(t5.signature='',0,1) as has_signed,IF(t5.signature='',0,1) as enrollment_status_id,
                    IF(t5.consent_form_path='',IF(t5.disclaimer_form='',0,1),1) as has_form,t5.grade as current_school_grade,
                    t5.confirmed_grade as school_grade,t5.previous_grade as performance_grade,t5.term1_fees,t5.term2_fees,
                    t5.term3_fees,t5.annual_fees"),'t5.school_id','t5.first_name',
                    't5.address','t5.home_district','t5.disclaimer_form_path','t5.subjects','t5.beneficiary_schoolstatus_id',
                    't5.disclaimer_form_data','t5.finger_print_path','t5.exam_number','t5.exam_fees','t5.remarks',
                    't5.wb_facility_manager_id','t5.has_signed_consent','t5.has_signed_disclaimer','t5.is_gce_external_candidate',
                    't5.verified_dob','t5.grm_question_answer','t5.additional_fee_amount','t5.additional_fee_description')
                ->leftJoin('school_information as t3', 't1.school_id', '=', 't3.id')
                ->leftJoin('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('beneficiary_payresponses_staging_clone as t5', 't1.id', '=', 't5.id');
            if(validateisNumeric($school_id)) {
                $id_qry = DB::table('beneficiary_payresponses_staging_clone as t1')
                    ->select('t1.id')
                    ->where(array(
                        'school_id' => $school_id,
                        'verification_status' => 'pending'
                    ))->get();
                $id_array1 = [];
                foreach ($id_qry as $key => $ben_id) {
                    $id_array1[] = $ben_id->id;
                }
                $qry->whereIn('t1.id', $id_array1);
                $results = $qry->groupBy('t1.id')->get();
            } else {
                $results = [];
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Responses inserted Successfully'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $t) {
            $res = array(
                'success' => false,
                'message' => $t->getMessage()
            );
        }
        return response()->json($res);
    }
    
    public function getCwacDropdowns(Request $request)
    {
        try {
            $table_name = $request->input('table_name');
            $filters = $request->input('filters');
            $filters = (array)json_decode($filters);
            $qry = DB::table($table_name);
             if (count((array)$filters) > 0) {
                if($table_name=="ar_asset_identifiers")//job on 26/5/2022
                {
                    $qry->whereIn('category_id',[$filters['category_id'],0]);
                }else{
                    $qry->where($filters);
                }               
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    
}

