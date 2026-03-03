<?php

namespace App\Modules\MandEModule\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Modules\MandEModule\Traits\MandEModuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\PaymentModule\Http\Controllers\PaymentModuleController;

class MandEModuleController extends BaseController
{
    use MandEModuleTrait;

    public function index()
    {
        return view('mandemodule::index');
    }

    public function saveMandEModuleCommonData(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $id = $post_data['id'];
            $res = array();
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['id']);
            $table_data = $post_data;//encryptArray($post_data, $skipArray);
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
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $id;
                }
            } else {
                $res = insertRecord($table_name, $table_data, $user_id);
            }
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
        return response()->json($res);
    }
    
    public function getMandEModuleParamFromTable(Request $request)
    {
        $table_name = $request->input('table_name');
        $filters = $request->input('filters');
        $filters = (array)json_decode($filters);
        try {
            $qry = DB::table($table_name);
            if (count((array)$filters) > 0) {
                $qry->where($filters);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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

    public function deleteMandEModuleRecord(Request $request)
    {
        try {
            $record_id = $request->input('id');
            $table_name = $request->input('table_name');
            $user_id = $this->user_id;
            $where = array(
                'id' => $record_id
            );
            $previous_data = getPreviousRecords($table_name, $where);
            $res = deleteRecord($table_name, $previous_data, $where, $user_id);
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
        return response()->json($res);
    }

    public function getMandEKPIs(Request $request)
    {
        $category_id = $request->input('category_id');
        $section_id = $request->input('section_id');
        $frequency_id = $request->input('frequency_id');
        $kpi_status=$request->input('kpi_status');
        if($kpi_status==null || $kpi_status== ''){
             $kpi_status=1;
        }
       
        try {
            $qry = Db::table('mne_kpis as t1')
                ->join('mne_kpis_categories as t2', 't1.category_id', '=', 't2.id')
                ->leftJoin('mne_datacollection_tools as t3', 't1.collection_tool_id', '=', 't3.id')
                ->leftJoin('mne_datacollection_frequencies as t4', 't1.frequency_id', '=', 't4.id')
                ->leftJoin('mne_kpis_sections as t5', 't1.section_id', '=', 't5.id')
                ->leftJoin('mne_datacollection_unit_measure as t6','t1.unit_measure','=','t6.id')
                ->leftJoin('kpi_types as t7','t1.kpi_type_id','=','t7.id')
                ->select('t1.*', 't2.name as category','t1.id as kpi_id', 't3.name as data_source', 't4.name as frequency', 't5.name as section','t6.name as unit_measure','t7.name as kpi_type_name')
                ->where('t1.active',$kpi_status);

/*            if (validateisNumeric($category_id)) {
                $qry->where(array('t1.category_id'=>$category_id,'t1.active'=>1));
            }
            if (validateisNumeric($frequency_id)) {
                $qry->where(array('t1.frequency_id'=> $frequency_id,'t1.active'=>1));
            }*/
            if (validateisNumeric($section_id)) {
                $qry->where(array('t1.section_id', $section_id,'t1.active'=>1));
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getDataCollectionToolSections(Request $request)
    {
        $dataCollectionToolId = $request->input('dataCollectionToolId');
        try {
            $qry = Db::table('mne_datacollectiontool_sections as t1')
                ->leftJoin('mne_datacollection_frequencies as t2', 't1.frequency_id', '=', 't2.id')
                ->select('t1.*', 't2.name as frequency')
                ->where('t1.datacollectiontool_id', $dataCollectionToolId)
                ->orderBy('t1.order_no');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function addDataCollectionToolQuiz(Request $request)
    {
        try {
            $id = $request->input('id');
            $datacollectiontool_id = $request->input('datacollectiontool_id');
            $question = $request->input('name');
            $description = $request->input('description');
            $order_no = $request->input('order_no');
            $answer_type = $request->input('answer_type');
            $options_count = $request->input('answer_options_count');
            $has_mis_value = $request->input('has_mis_value');
            $user_id = $this->user_id;
            $res = array();
            $value_range = '';
            DB::transaction(function () use (&$res, $request, $id, $datacollectiontool_id, $description, $order_no, $answer_type, $question, $user_id, $options_count, $has_mis_value, $value_range) {
                $level = $request->input('level');
                if ($level == 2) {
                    $parent_id = $request->input('child_id');
                } else {
                    $parent_id = $request->input('parent_id');
                }
                $max_value = '';
                $min_value = '';
                if ($answer_type == 3) {
                    $max_value = $request->input('answer_option1');
                    $min_value = $request->input('label_option1');
                }
                $questions_data = array(
                    'datacollectiontool_id' => $datacollectiontool_id,
                    'name' => $question,
                    'order_no' => $order_no,
                    'has_mis_value' => $has_mis_value,
                    'answer_type' => $answer_type,
                    'description' => $description,
                    'section_id' => $request->input('section_id'),
                    'level' => $level,
                    'parent_id' => $parent_id,
                    'min_value' => $min_value,
                    'max_value' => $max_value,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $where = array(
                    'id' => $id
                );
                if (isset($id) && $id != '') {
                    $prev_records = getPreviousRecords('mne_datacollectiontool_quizes', $where);
                    updateRecord('mne_datacollectiontool_quizes', $prev_records, $where, $questions_data, $user_id);
                    DB::table('mne_quizesanswer_options')->where(array('question_id' => $id))->delete();
                    for ($i = 1; $i <= $options_count; $i++) {
                        $option = $request->input('answer_option' . $i);
                        $label_name = $request->input('label_option' . $i);
                        /*if ($answer_type == 3 || $answer_type == 4 || $answer_type == 5 || $answer_type == 6) {
                            if (isset($label_name) && $label_name != '') {
                                $option = $label_name;
                            } else {
                                $option = 'No Label';
                            }
                        }*/
                        if ($answer_type == 3) {
                            if (isset($label_name) && $label_name != '') {
                                $value_range = $label_name;
                            }
                        }
                        $answer_options_data = array(
                            'question_id' => $id,
                            'answer_type_id' => $answer_type,
                            'option_id' => $option,
                            'value_range' => $value_range,
                            'option_label' => $label_name,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                        DB::table('mne_quizesanswer_options')->insert($answer_options_data);
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Question updated successfully!!'
                    );
                } else {
                    $res = insertRecord('mne_datacollectiontool_quizes', $questions_data, $user_id);
                    if ($res['success'] == true) {
                        $question_id = $res['record_id'];
                        if (is_numeric($question_id)) {
                            for ($i = 1; $i <= $options_count; $i++) {
                                $option = $request->input('answer_option' . $i);
                                $label_name = $request->input('label_option' . $i);
                                /*if ($answer_type == 3 || $answer_type == 4 || $answer_type == 5 || $answer_type == 6) {
                                    if (isset($label_name) && $label_name != '') {
                                        $option = $label_name;
                                    } else {
                                        $option = 'No Label';
                                    }
                                }*/
                                if ($answer_type == 3) {
                                    if (isset($label_name) && $label_name != '') {
                                        $value_range = $label_name;
                                    }
                                }
                                $answer_options_data = array(
                                    'question_id' => $question_id,
                                    'answer_type_id' => $answer_type,
                                    'option_id' => $option,
                                    'value_range' => $value_range,
                                    'option_label' => $label_name,
                                    'created_at' => Carbon::now(),
                                    'created_by' => $user_id
                                );
                                DB::table('mne_quizesanswer_options')->insert($answer_options_data);
                            }
                        }
                    }
                }
            }, 5);
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
        return response()->json($res);
    }

    public function getDataCollectionToolQuizes(Request $request)
    {
        $datacollectiontool_id = $request->input('datacollectiontool_id');
        $section_id = $request->input('section_id');
        try {
            //Level 0
            $questions = array();
            $margin = 20;
            $currCount = 0;
            $quizes1 = $this->getQuestions('', 0, $datacollectiontool_id, '', $section_id);
            foreach ($quizes1 as $quize1) {
                if ($currCount == 0) {//start
                    //$prevCount=$currCount;
                    $quize1->prev_section = 0;
                } else {
                    $prevCount = ($currCount - 1);
                    $quize1->prev_section = $quizes1[$prevCount]->section_id;
                }
                //$quize1->prev_section = $quizes1[$prevCount]->section_id;
                $quize1->margin = $margin;
                $quize1->ans_options = $this->getQuestionAnsOptions($quize1->id, $quize1->answer_type);
                $questions[] = $quize1;
                //Level 1  -- has parent_id
                $quizes2 = $this->getQuestions($quize1->id, 1, $datacollectiontool_id, '', $section_id);
                foreach ($quizes2 as $quize2) {
                    $quize2->margin = $margin * 2;
                    $quize2->ans_options = $this->getQuestionAnsOptions($quize2->id, $quize2->answer_type);
                    $questions[] = $quize2;
                    //Level 2  -- has parent and child ids
                    $quizes3 = $this->getQuestions($quize2->id, 2, $datacollectiontool_id, $quize1->id, $section_id);
                    foreach ($quizes3 as $quize3) {
                        $quize3->margin = $margin * 3;
                        $quize3->ans_options = $this->getQuestionAnsOptions($quize3->id, $quize3->answer_type);
                        $questions[] = $quize3;
                    }
                }
                $currCount++;
            }
            $res = array(
                'success' => true,
                'results' => $questions,
                'message' => returnMessage($questions)
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
        return response()->json($res);
    }

    public function getQuestions($parent_id, $level, $datacollectiontool_id, $grand_parent_id = 0, $sectionId = '')
    {
        $qry = DB::table('mne_datacollectiontool_quizes as t1')
            ->join('mne_datacollectiontool_sections as t2', 't1.section_id', 't2.id')
            ->select(DB::raw("t1.*,CONCAT(t1.name,' [',t1.id,']') as quiz,t2.section as section_name"))
            //->select('t1.*', 't2.section as section_name')
            ->where('t1.level', $level)
            ->orderBy('t2.order_no')
            ->orderBy('t1.order_no');
        if (validateisNumeric($datacollectiontool_id)) {
            $qry->where('t1.datacollectiontool_id', $datacollectiontool_id);
        }
        if (validateisNumeric($sectionId)) {
            $qry->where('t1.section_id', $sectionId);
        }
        validateisNumeric($parent_id) ? $qry->where('t1.parent_id', $parent_id) : $qry->whereRaw('1=1');
        if ($level == 1) {
            $qry->addSelect(DB::raw("$parent_id as parent_id"));
        } else if ($level == 2) {
            $qry->addSelect(DB::raw("$grand_parent_id as parent_id,$parent_id as child_id"));
        }
        return $qry->get();
    }

    public function getQuestions2($recordId, $parent_id, $level, $datacollectiontool_id, $grand_parent_id = 0, $sectionId = '')
    {
        $qry = DB::table('mne_datacollectiontool_quizes as t1')
            ->join('mne_datacollectiontool_sections as t2', 't1.section_id', 't2.id')
            ->leftJoin('mne_unstructuredquizes_dataentryinfo as t3', function ($join) use ($recordId) {
                $join->on('t1.id', '=', 't3.question_id')
                    ->where('t3.record_id', $recordId);
            })
            ->select(DB::raw("t1.*,t1.id as questionId,t2.section as section_name,t3.id as entry_id,t3.response,t3.remark"))
            ->where('t1.level', $level)
            ->orderBy('t2.order_no')
            ->orderBy('t1.order_no');
        if (validateisNumeric($datacollectiontool_id)) {
            $qry->where('t1.datacollectiontool_id', $datacollectiontool_id);
        }
        if (validateisNumeric($sectionId)) {
            $qry->where('t1.section_id', $sectionId);
        }
        validateisNumeric($parent_id) ? $qry->where('t1.parent_id', $parent_id) : $qry->whereRaw('1=1');
        if ($level == 1) {
            $qry->addSelect(DB::raw("$parent_id as parent_id"));
        } else if ($level == 2) {
            $qry->addSelect(DB::raw("$grand_parent_id as parent_id,$parent_id as child_id"));
        }
        return $qry->get();
    }

    public function getQuestionAnsOptions($question_id, $answer_type_id)
    {
        $options_string = '';
        $label = '';
        $qry = DB::table('mne_quizesanswer_options as t1')
            ->leftJoin('mne_checklist_options as t2', 't1.option_id', '=', 't2.id')
            ->select('t1.*', 't2.option_name')
            ->where('t1.question_id', $question_id);
        $data = $qry->get();
        if ($data->count() > 0) {
            $label = $data[0]->option_label;
        }
        //if ($data->count() > 0) {
        if ($answer_type_id == 1) {//multiple select
            foreach ($data as $datum) {
                $options_string .= '<input type="checkbox" disabled="disabled">' . $datum->option_name . '<br/>';
            }
        } else if ($answer_type_id == 2) {//single select
            foreach ($data as $datum) {
                $options_string .= '<input type="radio" disabled="disabled">' . $datum->option_name . '<br/>';
            }
        } else if ($answer_type_id == 3) {//number
            //$options_string = $label . ' <input type="number" disabled="disabled">';
            $options_string = ' <input type="number" disabled="disabled">';
        } else if ($answer_type_id == 4) {//date
            $options_string = ' <input type="date" disabled="disabled">';
        } else if ($answer_type_id == 5) {//textfield
            $options_string = ' <input type="text" disabled="disabled">';
        } else if ($answer_type_id == 6) {//textarea
            $options_string = ' <textarea disabled="disabled"></textarea>';
        }
        //}
        return $options_string;
    }

    function getDataCollectionToolAnswerOptionsSetup(Request $req)
    {
        $question_id = $req->input('question_id');
        $ans_type_id = $req->input('answer_type_id');
        $where = array(
            'question_id' => $question_id,
            'answer_type_id' => $ans_type_id
        );
        try {
            $qry = DB::table('mne_quizesanswer_options as t1')
                ->leftJoin('mne_checklist_options as t2', 't1.option_id', '=', 't2.id')
                ->select('t1.*', 't2.option_name')
                ->where($where);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getConsolidatedSchLevelBackgroundInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_consolidatedschlevel_background_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getConsolidatedSchLevelBackgroundInfoAnalysis(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        $school_id = $request->input('school_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_consolidatedschlevel_background_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            foreach ($results as $key => $result) {
                $results[$key]->kgsgirls_paidfor_mis = $this->getBeneficiaryPaymentsEnrollments($school_id, $result->gradeId, $year_id, $term_id, 1);
                $results[$key]->kgsgirls_enrolled_mis = $this->getBeneficiaryPaymentsEnrollments($school_id, $result->gradeId, $year_id, $term_id);
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getConsolidatedSchLevelBackgroundInfoSC(Request $request)//SpotCheck
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $school_id = $request->input('school_id');
        try {
            $qry = DB::table('mne_consolidatedschlevel_background_info as t1')
                ->join('mne_datacollectiontool_dataentry_basicinfo as t2', 't1.record_id', 't2.id')
                ->leftJoin('school_grades as t3', 't1.grade_id', '=', 't3.id')
                ->select('t1.*', 't3.id as gradeId', 't2.*')
                ->select('t3.*', 't3.id as gradeId', 't1.*')
                ->where('t1.year_id', $year_id)
                ->where('t1.term_id', $term_id)
                ->where('t2.school_id', $school_id)
                ->where('t2.datacollection_tool_id', 1)
                ->where('t3.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getBeneficiaryPaymentsEnrollments($school_id, $grade, $year, $term, $is_payment = 0)
    {
        $where = array(
            'school_id' => $school_id,
            'year_of_enrollment' => $year,
            'school_grade' => $grade
        );
        if ($is_payment == 1) {
            $where['is_validated'] = 1;
        } else {
            $where['has_signed'] = 1;
        }
        $count = DB::table('beneficiary_enrollments as t1')
            ->where($where)
            ->count();
        return $count;
    }

    public function getPupilsStatisticsInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_pupilsstatistics_info as t2', function ($join) use ($record_id, $year_id, $term_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.year_id' => $year_id, 't2.term_id' => $term_id, 't2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'results' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'results' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPupilsStatisticsInfoAnalysis(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        $school_id = $request->input('school_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_pupilsstatistics_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            foreach ($results as $key => $result) {
                $results[$key]->total_kgsgirls_mis = $this->getBeneficiaryPaymentsEnrollments($school_id, $result->gradeId, $year_id, $term_id);
            }
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'results' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'results' => $throwable->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getConsolidatedSchLevelProgressionInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades_transitions as t1')
                ->leftJoin('mne_progression_info as t2', function ($join) use ($record_id, $year_id, $term_id) {
                    $join->on('t1.id', '=', 't2.transition_id')
                        ->where(array('t2.year_id' => $year_id, 't2.term_id' => $term_id, 't2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as transitionId', 't2.*')
                ->where('t1.kgs_eligible', 1)
                ->orWhere('t1.id', '12');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getConsolidatedSchLevelProgressionInfoAnalysis(Request $request)
    {
        $analysis_year = $request->input('analysis_year');
        try {
            $qry = DB::table('school_grades_transitions as t1')
                ->leftJoin('mne_progression_info as t2', 't1.id', '=', 't2.transition_id')
                ->select('t1.*', 't1.id as transitionId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getBeneficiaryPerformanceAttendanceInfo(Request $request)
    {
        try {
            $year = $request->input('year_id');
            $term = $request->input('term_id');
            $school_id = $request->input('school_id');
            $record_id = $request->input('record_id');
            $where = array(
                't2.school_id' => $school_id,
                't2.year_of_enrollment' => $year
            );
            if ($year < 2019) {
                $where['t2.term_id'] = $term;
            }
            $qry = DB::table('beneficiary_information as t1')
                ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
                ->leftJoin('beneficiary_school_statuses as t3', 't1.beneficiary_school_status', '=', 't3.id')
                ->leftJoin('mne_performanceattendance_info as t4', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't4.girl_id')
                        ->where('t4.record_id', $record_id);
                })
                ->select(DB::raw("t1.id as girlId,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,t1.beneficiary_id,t2.school_grade,
                            t3.name as sch_status,t1.current_school_grade,t4.*,t2.has_signed"))
                ->where($where)
                ->groupBy('t1.id');

            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getEducationQualityInfo(Request $request)
    {
        $dataCollectionToolId = $request->input('dataCollectionToolId');
        try {
            $qry = DB::table('mne_datacollectiontool_quizes as t1')
                ->where('t1.datacollectiontool_id', $dataCollectionToolId);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function getToolQuizes(Request $request)
    {
        $dataCollectionToolId = $request->input('datacollection_tool_id');
        $sectionId = $request->input('section_id');
        $recordId = $request->input('record_id');
        $row = $this->getQuestions2($recordId, '', 0, $dataCollectionToolId, 0, $sectionId);
        $quizes = '{"quizes": "."';
        $quizes .= ',';
        $quizes .= '"children": [';
        if (count($row)) {
            $menu_count = count($row);
            $menu_counter = 0;

            foreach ($row as $item) {
                $menu_counter++;
                $entry_id = $item->entry_id;
                $question_id = $item->questionId;
                $answer_type = $item->answer_type;
                $name = trim($item->name);
                $level = $item->level;
                $order_no = $item->order_no;
                $response = $item->response;
                $remark = $item->remark;
                $min_value = $item->min_value;
                $max_value = $item->max_value;

                $quizes .= '{';
                $quizes .= '"entry_id": "' . $entry_id . '",';
                $quizes .= '"question_id": ' . $question_id . ',';
                $quizes .= '"answer_type": ' . $answer_type . ',';
                $quizes .= '"name": "' . $order_no . '. ' . $name . '",';
                $quizes .= '"level": ' . $level . ',';
                $quizes .= '"order_no": "' . $order_no . '",';
                $quizes .= '"response": "' . $response . '",';
                $quizes .= '"remark": "' . $remark . '",';
                $quizes .= '"min_value": "' . $min_value . '",';
                $quizes .= '"max_value": "' . $max_value . '",';

                $children = $this->getQuestions2($recordId, $question_id, 1, $dataCollectionToolId, 0, $sectionId);
                if (count($children) > 0) {
                    $children_count = count($children);
                    $children_counter = 0;
                    $quizes .= '"expanded": true,';
                    $quizes .= '"children": [';
                    foreach ($children as $child) {
                        $children_counter++;
                        $child_entry_id = $child->entry_id;
                        $child_question_id = $child->questionId;
                        $child_answer_type = $child->answer_type;
                        $child_name = trim($child->name);
                        $child_level = $child->level;
                        $child_order_no = $child->order_no;
                        $child_parent_id = $child->parent_id;
                        $child_response = $child->response;
                        $child_remark = $child->remark;
                        $child_min_value = $child->min_value;
                        $child_max_value = $child->max_value;

                        $quizes .= '{';
                        $quizes .= '"entry_id": "' . $child_entry_id . '",';
                        $quizes .= '"question_id": ' . $child_question_id . ',';
                        $quizes .= '"answer_type": ' . $child_answer_type . ',';
                        $quizes .= '"name": "' . $child_name . '",';
                        $quizes .= '"level": ' . $child_level . ',';
                        $quizes .= '"order_no": "' . $child_order_no . '",';
                        $quizes .= '"parent_id": "' . $child_parent_id . '",';
                        $quizes .= '"response": "' . $child_response . '",';
                        $quizes .= '"remark": "' . $child_remark . '",';
                        $quizes .= '"min_value": "' . $child_min_value . '",';
                        $quizes .= '"max_value": "' . $child_max_value . '",';

                        //level 2 menu items
                        $grandchildren = $this->getQuestions2($recordId, $child_question_id, 2, $dataCollectionToolId, 0, $sectionId);
                        if (count($grandchildren) > 0) {
                            $grandchildren_count = count($grandchildren);
                            $grandchildren_counter = 0;
                            $quizes .= '"expanded": true,';
                            $quizes .= '"children": [';
                            foreach ($grandchildren as $grandchild) {
                                $grandchildren_counter++;
                                $grandchild_entry_id = $grandchild->entry_id;
                                $grandchild_question_id = $grandchild->questionId;
                                $grandchild_answer_type = $grandchild->answer_type;
                                $grandchild_name = trim($grandchild->name);
                                $grandchild_level = $grandchild->level;
                                $grandchild_order_no = $grandchild->order_no;
                                $grandchild_parent_id = $child->parent_id;
                                $grandchild_child_id = $grandchild->parent_id;
                                $grandchild_response = $grandchild->response;
                                $grandchild_remark = $grandchild->remark;
                                $grandchild_min_value = $grandchild->min_value;
                                $grandchild_max_value = $grandchild->max_value;

                                $quizes .= '{';
                                $quizes .= '"entry_id": "' . $grandchild_entry_id . '",';
                                $quizes .= '"question_id": ' . $grandchild_question_id . ',';
                                $quizes .= '"answer_type": ' . $grandchild_answer_type . ',';
                                $quizes .= '"name": "' . $grandchild_name . '",';
                                $quizes .= '"level": ' . $grandchild_level . ',';
                                $quizes .= '"order_no": "' . $grandchild_order_no . '",';
                                $quizes .= '"parent_id": "' . $grandchild_parent_id . '",';
                                $quizes .= '"child_id": "' . $grandchild_child_id . '",';
                                $quizes .= '"response": "' . $grandchild_response . '",';
                                $quizes .= '"remark": "' . $grandchild_remark . '",';
                                $quizes .= '"min_value": "' . $grandchild_min_value . '",';
                                $quizes .= '"max_value": "' . $grandchild_max_value . '",';
                                $quizes .= '"leaf": true';

                                if ($grandchildren_counter == $grandchildren_count) {
                                    //Last Child in this level. Level=2
                                    $quizes .= '}';
                                } else {
                                    $quizes .= '},';
                                }
                            }
                            $quizes .= '],';
                        } else {
                            $quizes .= '"leaf": true';
                        }
                        if ($children_counter == $children_count) {
                            //Last Child in this level. Level=1
                            $quizes .= '}';
                        } else {
                            $quizes .= '},';
                        }
                    }
                    $quizes .= '],';

                } else {
                    $quizes .= '"leaf": true';
                }

                if ($menu_counter == $menu_count) {
                    $quizes .= '}';
                } else {
                    $quizes .= '},';
                }
            }
        }
        $quizes .= ']}';
        return $quizes;
    }

    public function getToolQuizesAnalysis(Request $request)
    {
        $dataCollectionToolId = $request->input('datacollection_tool_id');
        $sectionId = $request->input('section_id');
        $recordId = $request->input('record_id');

        $row = $this->getQuestions2($recordId, '', 0, $dataCollectionToolId, 0, $sectionId);
        $quizes = '{"quizes": "."';
        $quizes .= ',';
        $quizes .= '"children": [';
        if (count($row)) {
            $menu_count = count($row);
            $menu_counter = 0;

            foreach ($row as $item) {
                $mis_value = '';
                $menu_counter++;
                $entry_id = $item->entry_id;
                $question_id = $item->questionId;
                $answer_type = $item->answer_type;
                $name = trim($item->name);
                $level = $item->level;
                $order_no = $item->order_no;
                $response = $item->response;
                $remark = $item->remark;
                if ($item->has_mis_value == 1) {
                    $mis_value = $this->getMISValue($question_id, $request, $recordId);
                }

                $quizes .= '{';
                $quizes .= '"entry_id": "' . $entry_id . '",';
                $quizes .= '"question_id": ' . $question_id . ',';
                $quizes .= '"answer_type": ' . $answer_type . ',';
                $quizes .= '"name": "' . $order_no . '. ' . $name . '",';
                $quizes .= '"level": ' . $level . ',';
                $quizes .= '"order_no": "' . $order_no . '",';
                $quizes .= '"response": "' . $response . '",';
                $quizes .= '"mis_value": "' . $mis_value . '",';
                $quizes .= '"remark": "' . $remark . '",';

                $children = $this->getQuestions2($recordId, $question_id, 1, $dataCollectionToolId, 0, $sectionId);
                if (count($children) > 0) {
                    $children_count = count($children);
                    $children_counter = 0;
                    $quizes .= '"expanded": true,';
                    $quizes .= '"children": [';
                    foreach ($children as $child) {
                        $child_mis_value = '';
                        $children_counter++;
                        $child_entry_id = $child->entry_id;
                        $child_question_id = $child->questionId;
                        $child_answer_type = $child->answer_type;
                        $child_name = trim($child->name);
                        $child_level = $child->level;
                        $child_order_no = $child->order_no;
                        $child_parent_id = $child->parent_id;
                        $child_response = $child->response;
                        $child_remark = $child->remark;
                        if ($child->has_mis_value == 1) {
                            $child_mis_value = $this->getMISValue($child_question_id, $request, $recordId);
                        }

                        $quizes .= '{';
                        $quizes .= '"entry_id": "' . $child_entry_id . '",';
                        $quizes .= '"question_id": ' . $child_question_id . ',';
                        $quizes .= '"answer_type": ' . $child_answer_type . ',';
                        $quizes .= '"name": "' . $child_name . '",';
                        $quizes .= '"level": ' . $child_level . ',';
                        $quizes .= '"order_no": "' . $child_order_no . '",';
                        $quizes .= '"parent_id": "' . $child_parent_id . '",';
                        $quizes .= '"response": "' . $child_response . '",';
                        $quizes .= '"mis_value": "' . $child_mis_value . '",';
                        $quizes .= '"remark": "' . $child_remark . '",';

                        //level 2 menu items
                        $grandchildren = $this->getQuestions2($recordId, $child_question_id, 2, $dataCollectionToolId, 0, $sectionId);
                        if (count($grandchildren) > 0) {
                            $grandchildren_count = count($grandchildren);
                            $grandchildren_counter = 0;
                            $quizes .= '"expanded": true,';
                            $quizes .= '"children": [';
                            foreach ($grandchildren as $grandchild) {
                                $grandchild_mis_value = '';
                                $grandchildren_counter++;
                                $grandchild_entry_id = $grandchild->entry_id;
                                $grandchild_question_id = $grandchild->questionId;
                                $grandchild_answer_type = $grandchild->answer_type;
                                $grandchild_name = trim($grandchild->name);
                                $grandchild_level = $grandchild->level;
                                $grandchild_order_no = $grandchild->order_no;
                                $grandchild_parent_id = $child->parent_id;
                                $grandchild_child_id = $grandchild->parent_id;
                                $grandchild_response = $grandchild->response;
                                $grandchild_remark = $grandchild->remark;
                                if ($grandchild->has_mis_value == 1) {
                                    $grandchild_mis_value = $this->getMISValue($grandchild_question_id, $request, $recordId);
                                }

                                $quizes .= '{';
                                $quizes .= '"entry_id": "' . $grandchild_entry_id . '",';
                                $quizes .= '"question_id": ' . $grandchild_question_id . ',';
                                $quizes .= '"answer_type": ' . $grandchild_answer_type . ',';
                                $quizes .= '"name": "' . $grandchild_name . '",';
                                $quizes .= '"level": ' . $grandchild_level . ',';
                                $quizes .= '"order_no": "' . $grandchild_order_no . '",';
                                $quizes .= '"parent_id": "' . $grandchild_parent_id . '",';
                                $quizes .= '"child_id": "' . $grandchild_child_id . '",';
                                $quizes .= '"response": "' . $grandchild_response . '",';
                                $quizes .= '"mis_value": "' . $grandchild_mis_value . '",';
                                $quizes .= '"remark": "' . $grandchild_remark . '",';
                                $quizes .= '"leaf": true';

                                if ($grandchildren_counter == $grandchildren_count) {
                                    //Last Child in this level. Level=2
                                    $quizes .= '}';
                                } else {
                                    $quizes .= '},';
                                }
                            }
                            $quizes .= '],';
                        } else {
                            $quizes .= '"leaf": true';
                        }
                        if ($children_counter == $children_count) {
                            //Last Child in this level. Level=1
                            $quizes .= '}';
                        } else {
                            $quizes .= '},';
                        }
                    }
                    $quizes .= '],';

                } else {
                    $quizes .= '"leaf": true';
                }

                if ($menu_counter == $menu_count) {
                    $quizes .= '}';
                } else {
                    $quizes .= '},';
                }
            }
        }
        $quizes .= ']}';
        return $quizes;
    }

    public
    function getDataCollectionToolQuizMultipleAnswerOptions(Request $req)
    {
        $question_id = $req->input('question_id');
        $answer_type_id = $req->input('answer_type_id');
        $where = array(
            'question_id' => $question_id,
            'answer_type_id' => $answer_type_id
        );
        $data = DB::table('mne_quizesanswer_options as t1')
            ->leftJoin('mne_checklist_options as t2', 't1.option_id', '=', 't2.id')
            ->select('t1.*', 't2.option_name')
            ->where($where)->get();
        $res = array(
            'results' => $data
        );
        return response()->json($res);
    }

    public function getMonitoredConsolidatedSchLevelTool($school_id, $entry_year, $entry_term)//Consolidated school level (Head Teacher)
    {
        $where = array(
            'school_id' => $school_id,
            'entry_year' => $entry_year,
            'entry_term' => $entry_term,
            'workflow_stage_id' => 3
        );
        $monitored_record_details = DB::table('mne_datacollectiontool_dataentry_basicinfo')
            ->where($where)
            ->first();
        return $monitored_record_details;
    }

    public function saveDataCollectionToolDataEntryBasicInfo(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $user_id = $this->user_id;
                $record_id = $request->input('record_id');
                $process_id = $request->input('process_id');
                $district_id = $request->input('district_id');
                $datacollection_tool_id = $request->input('datacollection_tool_id');
                $workflow_stage_id = $request->input('workflow_stage_id');
                $entry_year = $request->input('entry_year');
                $entry_term = $request->input('entry_term');
                $school_id = $request->input('school_id');
                //Maureen
                $school_geo_type = $request->input('school_geo_type');
                $school_fund_type = $request->input('school_fund_type');
                $school_terms = $request->input('school_terms');

                $table_name = 'mne_datacollectiontool_dataentry_basicinfo';
                $monitoring_table_name='mne_spotcheck_institutionalinfo';

                $table_data = array(
                    'datacollection_tool_id' => $datacollection_tool_id,
                    'province_id' => $request->input('province_id'),
                    'district_id' => $request->input('district_id'),
                    'cwac_id' => $request->input('cwac_id'),
                    'school_id' => $school_id,
                    'process_id' => $process_id,
                    'workflow_stage_id' => $workflow_stage_id,
                    'entry_year' => $entry_year,
                    'entry_term' => $entry_term,
                    //Maureen
                    'school_geo_type' => $school_geo_type,
                    'school_fund_type' => $school_fund_type,
                    'school_terms' => $school_terms
                );
                $monitoring_data = array(
                                
                                'intro' => $request->input('intro'),
                                'monitoring_purpose' => $request->input('monitoring_purpose'),
                                'objectives' => $request->input('objectives'),
                                'benlvl' => $request->input('benlvl'),
                                'schlvl' => $request->input('schlvl'),
                                'wkboardfac'=>$request->input('wkboardfac'),
                                'Attendance' => $request->input('Attendance'),
                                'mainob_perfomance' => $request->input('mainob_perfomance'),
                                'challenges' => $request->input('challenges'),
                                'suggestions'=>$request->input('suggestions'),
                                'conclusion'=>$request->input('conclusion')
                                
                        );
                $where = array(
                    'id' => $record_id
                );
                //Monitor tool Condition
                $monitor_where = array(
                    'record_id' => $record_id
                );


                if (isset($record_id) && $record_id != "") {
                    $previous_data = array();
                    if (recordExists($table_name, $where)) {
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $table_data['curr_from_userid'] = $user_id;
                        $table_data['curr_to_userid'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        $res['record_id'] = $record_id;
                        //update Monitoring details
                        if($res['success'] == true && $datacollection_tool_id==6){
                            $monitoring_data['record_id'] =  $record_id;
                            $monitoring_data['updated_at'] = Carbon::now();
                            $monitoring_data['updated_by'] = $user_id;
                            $monitoring_previous_data = getPreviousRecords($monitoring_table_name, $monitor_where);
                            $institute_where = array(
                                'id' => $institute_where=$monitoring_previous_data[0]['id']
                            );
                           // $monitoring_data['id'] = $monitoring_previous_data[0]['id'];
                            $monitor_res = updateRecord($monitoring_table_name, $monitoring_previous_data,$institute_where , $monitoring_data, $user_id);
                        }
                       
                    }
                    $ref_no = $previous_data[0]['reference_no'];
                    $folder_id = $previous_data[0]['folder_id'];
                } else {
                    //Check if data has been captured already
                    $proceed = $this->checkForAlreadyCapturedData($request);
                    if ($proceed['success'] == false) {
                        echo json_encode($proceed);
                        exit();
                    }

                    //monitoring only
                    if ($datacollection_tool_id == 6) {
                        $monitored_record_details = $this->getMonitoredConsolidatedSchLevelTool($school_id, $entry_year, $entry_term);

                        /*if (is_null($monitored_record_details)) {
                            $res = array(
                                'success' => false,
                                'message' => 'There is no matching \'Consolidated school level (Head Teacher)\' record for this period!!'
                            );
                            echo json_encode($res);
                            exit();
                        }*/
                        $table_data['is_monitoring'] = 1;
                        $table_data['monitored_record_id'] =$record_id;//$monitored_record_details->id;
                        $table_data['monitored_tool_id'] = $request->input('datacollection_tool_id');//$monitored_record_details->datacollection_tool_id;

                    }

                    $codes_array = array(
                        'tool_code' => getSingleRecordColValue('mne_datacollection_tools', array('id' => $datacollection_tool_id), 'code'),
                        'district_code' => getSingleRecordColValue('districts', array('id' => $district_id), 'code')
                    );
                    $ref_details = generateRecordRefNumber(4, $process_id, $district_id, $codes_array, $table_name, $user_id);
                    if ($ref_details['success'] == false) {
                        return \response()->json($ref_details);
                    }
                    $ref_no = $ref_details['ref_no'];
                    //todo DMS
                    $parent_id = 258379;
                    $folder_id = createDMSParentFolder($parent_id, '', $ref_no, '', $this->dms_id);
                    createDMSModuleFolders($folder_id, 35, $this->dms_id);
                    //end DMS
                    $table_data['folder_id'] = $folder_id;
                    $table_data['view_id'] = generateRecordViewID();
                    $table_data['reference_no'] = $ref_no;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;

                    $res = insertRecord($table_name, $table_data, $user_id);

                    //Maureen additional fields for Monitoring
                    if($res['success'] == true && $datacollection_tool_id == 6){
                        $monitoring_data['record_id'] = $res['record_id'];
                        $monitoring_data['created_at'] = Carbon::now();
                        $monitoring_data['created_by'] = $user_id;
                       $monitoring_res= insertRecord($monitoring_table_name, $monitoring_data, $user_id);
                       //Update Monitoring id(retaining previous structure)
                       DB::table($table_name)
                        ->where('id',$res['record_id'])
                        ->update(['monitored_record_id' => $res['record_id']]);
                    }
                }

                $request->request->add(['record_id' => $res['record_id']]);
                /* if ($datacollection_tool_id == 2) {
                     $institutionInfoSave = $this->saveDebsDistrictLevelBackgroundInfo($request);
                 } else {
                     $institutionInfoSave = $this->saveInstitutionalInfo($request);
                 }*/

                $institutionInfoSave = $this->saveInstitutionalInfo($request);
                if ($institutionInfoSave->getData()->success == false) {
                    $res = array(
                        'success' => $institutionInfoSave->getData()->success,
                        'message' => $institutionInfoSave->getData()->message
                    );
                    return response()->json($institutionInfoSave->getData());
                }
                $res['ref_no'] = $ref_no;
                $res['folder_id'] = $folder_id;
                $res['institutionInfoId'] = $institutionInfoSave->getData()->record_id;
            }, 5);
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
        return response()->json($res);
    }

    public function checkForAlreadyCapturedData(Request $request)
    {
        //check if data have been captured for the same period
        $datacollection_tool_id = $request->input('datacollection_tool_id');
        $entry_year = $request->input('entry_year');
        $entry_term = $request->input('entry_term');
        $district_id = $request->input('district_id');
        $school_id = $request->input('school_id');
        $where_check = array(
            'datacollection_tool_id' => $datacollection_tool_id,
            'entry_year' => $entry_year
        );
        $term_field = getSingleRecordColValue('mne_datacollection_tools', array('id' => $datacollection_tool_id), 'term_field');
        if ($term_field == 1) {
            $where_check['entry_term'] = $entry_term;
        }
        if ($datacollection_tool_id == 1 ) {//school level or spotcheck level changed 10/03/2022
            $where_check['school_id'] = $school_id;
        } else if ($datacollection_tool_id == 2|| $datacollection_tool_id == 6) {//district level
            $where_check['district_id'] = $district_id;
        }
        $captured = getTableData('mne_datacollectiontool_dataentry_basicinfo', $where_check);
        if (!is_null($captured)) {
            $res = array(
                'success' => false,
                'message' => 'Information captured already, with ref number: ' . $captured->reference_no
            );
            return $res;
        }
        return array(
            'success' => true,
            'message' => 'Proceed'
        );
    }

    public function saveInstitutionalInfo(Request $request)
    {
        $user_id = $this->user_id;
        $table_name = 'mne_institutional_info';
        $record_id=$request->input('record_id');
        $id=$request->input('id');
        if($request->input('datacollection_tool_id')==6 && (isset($id) && $id != "")){
            $getId= DB::table($table_name)->where('record_id', $record_id)->first();
             $id =  $getId->id;
         }else{
            $id;
         }

        $res = array();
        $knows_gbv_services = $request->input('knows_gbv_services');
        $known_gbv_services = $request->input('known_gbv_services');
        $gbv_complaint_received = $request->input('gbv_complaint_received');
        $action_on_gbv = $request->input('action_on_gbv');

        try {
            $table_data = array(
                'record_id' => $request->input('record_id'),
                'province_id' => $request->input('province_id'),
                'district_id' => $request->input('district_id'),
                'ward_id' => $request->input('ward_id'),
                'cwac_id' => $request->input('cwac_id'),
                'school_id' => $request->input('school_id'),
                'validator_name' => $request->input('validator_name'),
                'head_teacher_name' => $request->input('head_teacher_name'),
                'head_teacher_sex' => $request->input('head_teacher_sex'),
                'head_teacher_landline' => $request->input('head_teacher_landline'),
                'head_teacher_mobile' => $request->input('head_teacher_mobile'),
                'head_teacher_email' => $request->input('head_teacher_email'),
                'debs_name' => $request->input('debs_name'),
                'debs_sex' => $request->input('debs_sex'),
                'debs_landline' => $request->input('debs_landline'),
                'debs_mobile_phone' => $request->input('debs_mobile_phone'),
                'debs_mobile' => $request->input('debs_mobile'),
                'debs_email' => $request->input('debs_email'),
                'survey_completion_date' => $request->input('survey_completion_date'),
                'complaintbox_exists' => $request->input('complaintbox_exists'),
                'complaintbox_location_id' => $request->input('complaintbox_location_id'),
                'gewel_focalperson' => $request->input('gewel_focalperson'),
                'special_comments' => $request->input('special_comments')
            );
            $where = array(
                'id' => $id
            );

            if (isset($knows_gbv_services) && $knows_gbv_services != "") {
                $table_data['knows_gbv_services'] = $knows_gbv_services;
            }
            if (isset($known_gbv_services) && $known_gbv_services != "") {
                $table_data['known_gbv_services'] = $known_gbv_services;
            }
            if (isset($gbv_complaint_received) && $gbv_complaint_received != "") {
                $table_data['gbv_complaint_received'] = $gbv_complaint_received;
            }
            if (isset($action_on_gbv) && $action_on_gbv != "") {
                $table_data['action_on_gbv'] = $action_on_gbv;
            }

            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $id;
                }
            } else {
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
            }
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
        return response()->json($res);
    }

    public function getMandEEntriesInfo(Request $request)
    {

        try {
            $year_id= $request->input('year_id');
            $workflow_stage_id = $request->input('workflow_stage_id');
            $reqObj = $request->toArray();
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_datacollection_tools as t2', 't1.datacollection_tool_id', '=', 't2.id')
                ->leftJoin('mne_workflow_stages as t3', 't1.workflow_stage_id', '=', 't3.id')
                ->leftJoin('users as t4', 't1.created_by', '=', 't4.id')
                ->leftJoin('districts as t5', 't1.district_id', '=', 't5.id')
                ->leftJoin('mne_spotcheck_institutionalinfo as mne_spotcheck_institutionalinfo', 'mne_spotcheck_institutionalinfo.record_id', '=', 't1.id')
                ->leftJoin('school_information as t6', 't1.school_id', '=', 't6.id')
 
               /* ->leftJoin('school_geo_type as t7','t1.school_geo_type','=','t7.id')
                ->leftjoin('school_fund_type as t8','t1.school_fund_type','=','t8.id')
                ->leftJoin('school_terms as t9','t1.school_terms','=','t9.id')
                ->select(DB::raw("t1.*,t1.id as recordId,t2.name as tool_name,t2.form_xtype,t2.analysis_interface_xtype,t3.name as workflow_stage,t2.term_field,
                                  CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as entry_by,t1.created_at as entry_date,t5.name as district,
                                  t6.name as school,t7.name as sch_geotype,t8.name as sch_fundtype,t9.name as sch_term"));*/
              ->select(DB::raw("t1.*,t1.id as recordId,t2.name as tool_name,t2.form_xtype,t2.analysis_interface_xtype,t3.name as workflow_stage,t2.term_field,
                                  CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.last_name)) as entry_by,t1.created_at as entry_date,t5.name as district,
                                mne_spotcheck_institutionalinfo.*, t6.name as school"));
            if (validateisNumeric($workflow_stage_id)) {
                $qry->where('t1.workflow_stage_id', $workflow_stage_id);
            }
            if (array_key_exists('tool_id', $reqObj)) {
                $qry->where('t1.datacollection_tool_id', $reqObj['tool_id']);
            }
            if (validateisNumeric($year_id)) {
                $qry->where('t1.entry_year',$year_id);
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function saveConsolidatedSchLevelBackgroundInfo(Request $request)
    {
        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_consolidatedschlevel_background_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgsgirls_without_disability' => $value['kgsgirls_without_disability'],
                    'kgsgirls_with_disability' => $value['kgsgirls_with_disability'],
                    'non_kgsgirls_without_disability' => $value['non_kgsgirls_without_disability'],
                    'non_kgsgirls_with_disability' => $value['non_kgsgirls_with_disability'],
                    'boys_without_disability' => $value['boys_without_disability'],
                    'boys_with_disability' => $value['boys_with_disability']
                    //'total_no_disability' => $value['total_no_disability']
                    //Previous data
                    // 'kgsgirls_paidfor' => $value['kgsgirls_paidfor'],
                    // 'kgsgirls_enrolled' => $value['kgsgirls_enrolled'],
                    // 'kgsgirls_boarding_manbysch' => $value['kgsgirls_boarding_manbysch'],
                    // 'kgsgirls_wboarding_manbysch' => $value['kgsgirls_wboarding_manbysch'],
                    // 'kgsgirls_wboarding_private' => $value['kgsgirls_wboarding_private'],
                    // 'kgsgirls_dayscholars' => $value['kgsgirls_dayscholars']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }

    public function savePupilsStatisticsInfo(Request $request)
    {
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);

        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_pupilsstatistics_info';
        //$prevTermDetails = getPreviousTerm($year_id, $term_id);

        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'year_id' => $year_id,//$value['year_id'],
                    'term_id' => $term_id,//$value['term_id'],
                    'record_id' => $record_id,
                    'total_kgsgirls' => $value['total_kgsgirls'],
                    'total_othergirls' => $value['total_othergirls'],
                    'total_boys' => $value['total_boys']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }

    public function savePupilsProgressionInfo(Request $request)
    {
        $term_id = 1;//$request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_progression_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'transition_id' => $value['transitionId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgs_enrolled' => $value['kgs_enrolled'],
                    'kgs_finished' => $value['kgs_finished'],
                    'kgs_started' => $value['kgs_started'],
                    'nonkgs_enrolled' => $value['nonkgs_enrolled'],
                    'nonkgs_finished' => $value['nonkgs_finished'],
                     'nonkgs_started' => $value['nonkgs_started']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }

    public function savePupilsPerformanceAttendanceInfo(Request $request)
    {
        try {
            $term_id = $request->input('single_term_id');
            $year_id = $request->input('single_year_id');
            $record_id = $request->input('single_record_id');
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'mne_performanceattendance_info';
            foreach ($data as $key => $value) {
                $table_data = array(
                    'girl_id' => $value['girlId'],
                    'grade' => $value['grade'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'enrollment_status' => $value['enrollment_status'],
                    'mathematics_score' => $value['mathematics_score'],
                    'mathsclass_average' => $value['mathsclass_average'],
                    'english_score' => $value['english_score'],
                    'engclass_average' => $value['engclass_average'],
                    'science_score' => $value['science_score'],
                    'scienceclass_average' => $value['scienceclass_average'],
                    'benficiary_attendance' => $value['benficiary_attendance'],
                    'absence_reason_id' => $value['absence_reason_id']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }

    public function saveMandEUnstructuredQuizesInfo(Request $request)
    {
        $term_id = $request->input('term_id');
        $year_id = $request->input('year_id');
        $record_id = $request->input('record_id');
        $quizes = json_decode($request->input('quizes'));

        $table_name = 'mne_unstructuredquizes_dataentryinfo';
        try {

            foreach ($quizes as $quize) {

                $table_data = array(
                    'record_id' => $record_id,
                    'year_id' => $year_id,
                    'term_id' => $term_id,
                    'question_id' => $quize->question_id,
                    'response' => $quize->response,
                    'remark' => $quize->remark
                );
                //where data
                $where_data = array(
                    'id' => $quize->id
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)
                        ->where($where_data)
                        ->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)
                        ->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }

    public function getNextMandEWorkflowStageDetails(Request $request)
    {
        $current_stage_id = $request->input('workflow_stage_id');
        try {
            $qry = DB::table('mne_workflow_stages as t1')
                ->where('t1.order', function ($query) use ($current_stage_id) {
                    $query->select(DB::raw('t2.order+1'))
                        ->from('mne_workflow_stages as t2')
                        ->where('t2.id', $current_stage_id);
                });
            $nextStageId = $qry->value('t1.id');
            $res = array(
                'success' => true,
                'nextStageId' => $nextStageId
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
        return response()->json($res);
    }

    public function processMandERecordSubmission(Request $request)
    {
        $record_id = $request->input('record_id');
        $datacollection_tool_id = $request->input('datacollection_tool_id');
        $process_id = $request->input('process_id');
        $table_name = $request->input('table_name');
        $prev_stage = $request->input('prevstage_id');
        $to_stage = $request->input('nextstage_id');
        $remarks = $request->input('remarks');
        $responsible_user = $request->input('responsible_user');
        $user_id = $this->user_id;
        DB::beginTransaction();
        try {
            $record_details = DB::table($table_name)
                ->where('id', $record_id)
                ->first();
            if (is_null($record_details)) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while fetching record details!!'
                );
                return \response()->json($res);
            }

            $where = array(
                'id' => $record_id
            );
            $app_update = array(
                'workflow_stage_id' => $to_stage,
                'isRead' => 0,
                'curr_from_userid' => $user_id,
                'curr_to_userid' => $responsible_user,
                'current_stage_entry_date' => Carbon::now()
            );

            $prev_data = getPreviousRecords($table_name, $where);
            $update_res = updateRecord($table_name, $prev_data, $where, $app_update, $user_id);
            if ($update_res['success'] == false) {
                return \response()->json($update_res);
            }
            $transition_params = array(
                'record_id' => $record_id,
                'process_id' => $process_id,
                'from_stage' => $prev_stage,
                'to_stage' => $to_stage,
                'from_user' => $user_id,
                'to_user' => $responsible_user,
                'author' => $user_id,
                'remarks' => $remarks,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('records_workflow_transitions')->insert($transition_params);
            if ($datacollection_tool_id == 1 || $datacollection_tool_id === 1) {//Consolidated school level (Head Teacher)
                if ($to_stage == 3 || $to_stage === 3) {//data analysis
                    //update other beneficiary details
                    $this->updateBeneficiaryInfo($record_id);
                }
            }
            DB::commit();
            $res = array(
                'success' => true,
                'message' => 'Record submitted successfully!!'
            );
        } catch (\Exception $exception) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return \response()->json($res);
    }

    public function updateBeneficiaryInfo($record_id)
    {
        //get beneficiary performance details
        $performance_details = DB::table('mne_performanceattendance_info')
            ->where('record_id', $record_id)
            ->get();
        foreach ($performance_details as $performance_detail) {
            $girl_id = $performance_detail->girl_id;
            $confirmed_grade = $performance_detail->grade;
            $enrolled = $performance_detail->enrollment_status;
            $not_enrolled_reason = $performance_detail->absence_reason_id;
            //get current beneficiary details
            $curr_ben_details = DB::table('beneficiary_information')
                ->where('id', $girl_id)
                ->first();
            //update grade
            if (($confirmed_grade > 7 && $confirmed_grade < 12) && $confirmed_grade != $curr_ben_details->current_school_grade) {
                $grade_log = array(
                    'girl_id' => $girl_id,
                    'grade' => $confirmed_grade,
                    'year' => date('Y'),
                    'created_at' => Carbon::now(),
                    'created_by' => $this->user_id
                );
                DB::table('beneficiary_grade_logs')
                    ->insert($grade_log);
                DB::table('beneficiary_information')
                    ->where('id', '=', $girl_id)
                    ->update(array('current_school_grade' => $confirmed_grade, 'updated_at' => Carbon::now(), 'updated_by' => $this->user_id));
            }
            //update enrollment status
            if (($enrolled == 2 || $enrolled === 2) && ($curr_ben_details->enrollment_status == 1 || $curr_ben_details->enrollment_status === 1)) {
                //request suspension
                $susp_params = array(
                    'girl_id' => $girl_id,
                    'reason_id' => $not_enrolled_reason,
                    'user_reason' => 'From M&E module',
                    'request_by' => $this->user_id,
                    'request_date' => Carbon::now(),
                    'created_by' => $this->user_id
                );
                DB::table('suspension_requests')
                    ->insert($susp_params);
                DB::table('beneficiary_information')
                    ->where('id', '=', $girl_id)
                    ->update(array('enrollment_status' => 5, 'updated_at' => Carbon::now(), 'updated_by' => $this->user_id));
            }
        }
    }

    public function prepareToolInstitutionalInfo(Request $request)
    {
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_institutional_info as t2', 't1.id', '=', 't2.record_id')
                ->select('t2.*','t1.school_geo_type','t1.school_fund_type','t1.school_terms')
                ->where('t1.id', $record_id);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results
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
        return \response()->json($res);
    }

    public function prepareDebsDistrictLevelTool(Request $request)
    {
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_debsdistrictlevel_background_info as t2', 't1.id', '=', 't2.record_id')
                ->select('t2.*')
                ->where('t1.id', $record_id);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results
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
        return \response()->json($res);
    }

    public function getKPIsForAnalysis(Request $request)
    {
        $display= $request->input('display');
        $kpi_type_id = $request->input('kpi_type_id');
/*        $category_id = $request->input('category_id');
        $section_id = $request->input('section_id');*/
/*        if(!$display){   
        $components = '[';
        $qry = DB::table('mne_kpis as t1')
         ->where('t1.active',1);*/
         //  ->where(array('t1.active'=>1,'t1.kpi_type_id'=>1));
/*        if (validateisNumeric($category_id)) {
            $qry->where('t1.category_id', $category_id);
        }
        if (validateisNumeric($section_id)) {
            $qry->where('t1.section_id', $section_id);
        }*/
  /*        if (validateisNumeric($kpi_type_id)) {
            $qry->where('t1.kpi_type_id', $kpi_type_id);
        }
        $results = $qry->get();
        $totalRecords = $results->count();
        $count = 0;
       print_r($results);
        return;
       foreach ($results as $result) {
            $count++;
            $components .= "{";
            $components .= "hidden:true,";
            $components .= "},";
            $components .= "{";
            $components .= "title:'" . $result->kpi . " (Target: " . $result->target . ")',";
            $components .= "graph_xtype:'" . $result->graph_xtype . "',";
            $components .= "frame:true,";
            $components .= "scrollable:true,";
            $components .= "bodyPadding: 10,";
            $components .= "kpi_id: " . $result->id;
            if (isset($result->child_xtype)) {
                $components .= ",";
                $components .= "items: [{";
                $components .= "xtype:'" . $result->child_xtype . "'";
                $components .= "}]";
            }
   
            $components .= " }";
            $components .= $count == $totalRecords ? '' : ',';
        }
        $components .= "]";
        return $components;
        }else{*/
            $qry = DB::table('mne_kpis as t1')
                    ->where('t1.active',1);
            if (validateisNumeric($kpi_type_id)) {
                $qry->where('t1.kpi_type_id', $kpi_type_id);
            }
            $qry->selectRaw('kpi as kpi_name,child_xtype as childXtype,id as kpi_id');
            $results = $qry->get();
            return $results;
      //  }
    }

    public function calculateCoreIndicatorMatrix(Request $request)
    {

        $kpi_id = $request->input('kpi_id');
        $results = array();

       // try {
            if ($kpi_id == 1) {//todo: KPI 1
                $results = $this->calculateKPI1($request);
            } else if ($kpi_id == 2) {//todo: KPI 2
                $results = $this->calculateKPI2($request);
            } else if ($kpi_id == 3) {//todo: KPI 3
                $results = $this->calculateKPI3($request);
            } else if ($kpi_id == 4) {//todo: KPI 4
                $results = $this->calculateKPI4($request);
            } else if ($kpi_id == 5) {//todo: KPI 5
                $results = $this->calculateKPI5($request);
            } else if ($kpi_id == 6) {//todo: KPI 6
                $results = $this->calculateKPI6($request);
            } else if ($kpi_id == 7) {//todo: KPI 7
                $results = $this->calculateKPI7($request);
            } else if ($kpi_id == 8) {//todo: KPI 8
                $results = $this->calculateKPI8($request);
            } else if ($kpi_id == 9) {//todo: KPI 9
                $results = $this->calculateKPI9($request);
            } else if ($kpi_id == 10) {//todo: KPI 10
                $results = $this->calculateKPI10($request);
            } else if ($kpi_id == 11) {//todo: KPI 11
                $results = $this->calculateKPI11($request);
            } else if ($kpi_id == 12) {//todo: KPI 12
                $results = $this->calculateKPI12($request);
            } else if ($kpi_id == 13) {//todo: KPI 13
                $results = $this->calculateKPI5($request);
            } else if ($kpi_id == 14) {//todo: KPI 14
                $results = $this->calculateKPI14($request);
            } else if ($kpi_id == 15) {//todo: KPI 15
                $results = $this->calculateKPI15($request);
            } else if ($kpi_id == 16) {//todo: KPI 16
                $results = $this->calculateKPI16($request);
            } else if ($kpi_id == 17) {//todo: KPI 17
                $results = $this->calculateKPI17($request);
            } else if ($kpi_id == 18) {//todo: KPI 18
                $results = $this->calculateKPI18($request);
            } else if ($kpi_id == 19) {//todo: KPI 19
                $results = $this->calculateKPI19($request);
            } else if ($kpi_id == 20) {//todo: KPI 20
                $results = $this->calculateKPI20($request);
            } else if ($kpi_id == 21) {//todo: KPI 21
                $results = $this->calculateKPI21($request);
            } else if ($kpi_id == 22) {//todo: KPI 22
                $results = $this->calculateKPI22($request);
            } else if ($kpi_id == 23) {//todo: KPI 23
                $results = $this->calculateKPI23($request);
            } else if ($kpi_id == 24) {//todo: KPI 24
                $results = $this->calculateKPI24($request);
            }else if($kpi_id==33)
            {
                $results=$this->calculateKPI33($request);
            }else if($kpi_id==34)
            {
                $results=$this->calculateKPI34($request);
            }else if($kpi_id==35)
            {
                $results=$this->calculateKPI35($request);
            }else if($kpi_id==36)
            {
                $results=$this->calculateKPI36($request);
            }else if($kpi_id==37)
            {
                $results=$this->calculateKPI37($request); 
            }else if($kpi_id==38)
            {
                $results=$this->calculateKPI38($request); 
            }else if($kpi_id==39)
            {
                $results=$this->calculateKPI39($request);
            }else if($kpi_id==40)
            {
                $results=$this->calculateKPI40($request);
            }else if($kpi_id==41)
            {
                $results=$this->calculateKPI41($request);
            }else if($kpi_id==42)
            {
                $results=$this->calculateKPI42($request);
            }else if($kpi_id==43)
            {
                $results=$this->calculateKPI43($request);
            }else if($kpi_id==44)
            {
                $results=$this->calculateKPI44($request);
            }else if($kpi_id==45)
            {
                $results=$this->calculateKPI45($request);
            }else if($kpi_id==46)
            {
                $results=$this->calculateKPI46($request);
            }else if($kpi_id==47)
            {
                $results=$this->calculateKPI47($request);
            }
            else if($kpi_id==51)
            {
                $results=$this->calculateKPI51($request);
            }
            else if($kpi_id==53)
            {
                $results=$this->calculateKPI53($request);
            }
            else if($kpi_id==55)
            {
                $results=$this->calculateKPI55($request);
            }
            else if($kpi_id==56)
            {
                $results=$this->calculateKPI56($request);
            }
            else if($kpi_id==57)
            {
                $results=$this->calculateKPI57($request);
            }  
            else if($kpi_id==58)
            {
                $results=$this->calculateKPI58($request);
            }
            else if($kpi_id==59)
            {
                $results=$this->calculateKPI59($request);
            }
            else if($kpi_id==60)
            {
                $results=$this->calculateKPI60($request);
            }
            else if($kpi_id==61)
            {
                $results=$this->calculateKPI55($request);
            }
            else if($kpi_id==62)
            {
                $results=$this->calculateKPI62($request);
            }
            else if($kpi_id==63)
            {
                $results=$this->calculateKPI63($request);
            }
            else if($kpi_id==66)
            {
                $results=$this->calculateKPI55($request);
            }
            $res = array(
                'success' => true,
                'results' => $results
            );
        // } catch (\Exception $exception) {
        //     $res = array(
        //         'success' => false,
        //         'message' => $exception->getMessage()
        //     );
        // } catch (\Throwable $throwable) {
        //     $res = array(
        //         'success' => false,
        //         'message' => $throwable->getMessage()
        //     );
        // }
        return \response()->json($res);
    }

    //frank
    public function prepareBeneficiaryLevelTool(Request $request)
    {
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_beneficiary_info as t2', 't1.id', '=', 't2.record_id')
                ->select('t2.*')
                ->where('t1.id', $record_id);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results
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
        return \response()->json($res);
    }

    public function getBeneficiaryData(Request $request)
    {
        $table_name = $request->input('table_name');
        try {
            $qry = DB::table($table_name);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
    
    public function saveDqaEnrollmentInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_enrolment_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgs_reported' => $value['kgs_reported'],
                    'non_kgs_reported' => $value['non_kgs_reported'],
                    'kgs_recounted' => $value['kgs_recounted'],
                    'non_kgs_recounted' => $value['non_kgs_recounted'],
                    'kgs_discrepancy' => $value['kgs_discrepancy'],
                    'non_kgs_discrepancy' => $value['non_kgs_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaEnrollmentInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_enrolment_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }

    public function saveDqaEnrollmentAgeSpecificInfo(Request $request)
    {
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_kgsgirlenrolments_agespecific';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'age_group_id' => $value['age_group_main_id'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_eight_reported' => $value['grade_eight_reported'],
                    'grade_nine_reported' => $value['grade_nine_reported'],
                    'grade_ten_reported' => $value['grade_ten_reported'],
                    'grade_eleven_reported' => $value['grade_eleven_reported'],
                    'grade_twelve_reported' => $value['grade_twelve_reported'],
                    'grade_eight_recounted' => $value['grade_eight_recounted'],
                    'grade_nine_recounted' => $value['grade_nine_recounted'],
                    'grade_ten_recounted' => $value['grade_ten_recounted'],
                    'grade_eleven_recounted' => $value['grade_eleven_recounted'],
                    'grade_twelve_recounted' => $value['grade_twelve_recounted'],
                    'discrepancy' => $value['discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaEnrollmentAgeSpecificInfo(Request $request){
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        $school_id = $request->input('school_id');
        try {
            $qry = DB::table('mne_spotcheck_agegroups as t1')
                ->leftJoin('mne_dqa_kgsgirlenrolments_agespecific as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.age_group_id')
                    ->where(array('t2.record_id' => $record_id));
                })
                /*->leftJoin('mne_spotcheck_kgs_girl_enrolments as t2','t1.id', '=', 't2.age_group_id') */
                ->select('t1.*', 't1.id as age_group_main_id', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
    
    public function saveDqaBordingGrlsPaidFor(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_paidforgrls_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_id' => $value['gradeId'],
                    'kgs_paidfor_reported' => $value['kgs_paidfor_reported'],
                    'kgs_paidfor_recounted' => $value['kgs_paidfor_recounted'],
                    'kgs_paidfor_discrepancy' => $value['kgs_paidfor_discrepancy'],
                    'kgs_frmlbrd_reported' => $value['kgs_frmlbrd_reported'],
                    'kgs_frmlbrd_recounted' => $value['kgs_frmlbrd_recounted'],
                    'kgs_frmlbrd_discrepancy' => $value['kgs_frmlbrd_discrepancy'],
                    'kgs_wklybrd_reported' => $value['kgs_wklybrd_reported'],
                    'kgs_wklybrd_recounted' => $value['kgs_wklybrd_recounted'],
                    'kgs_wklybrd_discrepancy' => $value['kgs_wklybrd_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaBordingGrlsPaidFor(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_paidforgrls_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaGrlsInPrivBrding(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_grlsinpriv_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_id' => $value['gradeId'],
                    'kgs_prvtownd_reported' => $value['kgs_prvtownd_reported'],
                    'kgs_prvtownd_recounted' => $value['kgs_prvtownd_recounted'],
                    'kgs_prvtownd_discrepancy' => $value['kgs_prvtownd_discrepancy'],
                    'kgs_dayschlas_reported' => $value['kgs_dayschlas_reported'],
                    'kgs_dayschlas_recounted' => $value['kgs_dayschlas_recounted'],
                    'kgs_dayschlas_discrepancy' => $value['kgs_dayschlas_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaGrlsInPrivBrding(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_grlsinpriv_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaAvgTrmlyAttendance(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_avgtrmlyattndce_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'grade_id' => $value['gradeId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kgs_attndce_reported' => $value['kgs_attndce_reported'],
                    'kgs_attndce_recounted' => $value['kgs_attndce_recounted'],
                    'kgs_attndce_discrepancy' => $value['kgs_attndce_discrepancy'],
                    'nonkgs_attndce_reported' => $value['nonkgs_attndce_reported'],
                    'nonkgs_attndce_recounted' => $value['nonkgs_attndce_recounted'],
                    'nonkgs_attndce_discrepancy' => $value['nonkgs_attndce_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaAvgTrmlyAttendance(Request $request)
    {
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_avgtrmlyattndce_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaKgsGrlsAvgTrmlyPrfrmnce(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_kgsgrlsavgprfmnce_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_id' => $value['gradeId'],
                    'avg_math_reported' => $value['avg_math_reported'],
                    'avg_math_recounted' => $value['avg_math_recounted'],
                    'avg_math_discrepancy' => $value['avg_math_discrepancy'],
                    'avg_eng_reported' => $value['avg_eng_reported'],
                    'avg_eng_recounted' => $value['avg_eng_recounted'],
                    'avg_eng_discrepancy' => $value['avg_eng_discrepancy'],
                    'avg_sci_reported' => $value['avg_sci_reported'],
                    'avg_sci_recounted' => $value['avg_sci_recounted'],
                    'avg_sci_discrepancy' => $value['avg_sci_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaKgsGrlsAvgTrmlyPrfrmnce(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_kgsgrlsavgprfmnce_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaNonKgsGrlsAvgTrmlyPrfrmance(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_nonkgsgrlsavgprfmnce_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_id' => $value['gradeId'],
                    'avg_math_reported' => $value['avg_math_reported'],
                    'avg_math_recounted' => $value['avg_math_recounted'],
                    'avg_math_discrepancy' => $value['avg_math_discrepancy'],
                    'avg_eng_reported' => $value['avg_eng_reported'],
                    'avg_eng_recounted' => $value['avg_eng_recounted'],
                    'avg_eng_discrepancy' => $value['avg_eng_discrepancy'],
                    'avg_sci_reported' => $value['avg_sci_reported'],
                    'avg_sci_recounted' => $value['avg_sci_recounted'],
                    'avg_sci_discrepancy' => $value['avg_sci_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaNonKgsGrlsAvgTrmlyPrfrmance(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_nonkgsgrlsavgprfmnce_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaDropOutsInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_dropouts_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'dropout_reason_id' => $value['dropoutReasonId'],
                    'kgs_dropouts_reported' => $value['kgs_dropouts_reported'],
                    'kgs_dropouts_recounted' => $value['kgs_dropouts_recounted'],
                    'kgs_dropouts_discrepancy' => $value['kgs_dropouts_discrepancy'],
                    'nonkgs_dropouts_reported' => $value['nonkgs_dropouts_reported'],
                    'nonkgs_dropouts_recounted' => $value['nonkgs_dropouts_recounted'],
                    'nonkgs_dropouts_discrepancy' => $value['nonkgs_dropouts_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaDropOutsInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_dropout_reasons as t1')
                ->leftJoin('mne_dqa_dropouts_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.dropout_reason_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as dropoutReasonId', 't2.*')
                ->where('t1.dqa_tool', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaPaymentsInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_payments_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'payment_id' => $value['paymentId'],
                    'payments_reported' => $value['payments_reported'],
                    'payments_recounted' => $value['payments_recounted'],
                    'payments_discrepancy' => $value['payments_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaPaymentsInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_dqa_payment_categories as t1')
                ->leftJoin('mne_dqa_payments_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.payment_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as paymentId', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaRptingLrnersInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_rptlearners_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'grade_id' => $value['gradeId'],
                    'kgs_grlsrptng_reported' => $value['kgs_grlsrptng_reported'],
                    'kgs_grlsrptng_recounted' => $value['kgs_grlsrptng_recounted'],
                    'kgs_grlsrptng_discrepancy' => $value['kgs_grlsrptng_discrepancy'],
                    'nonkgs_grlsrptng_reported' => $value['nonkgs_grlsrptng_reported'],
                    'nonkgs_grlsrptng_recounted' => $value['nonkgs_grlsrptng_recounted'],
                    'nonkgs_grlsrptng_discrepancy' => $value['nonkgs_grlsrptng_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaRptingLrnersInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('school_grades as t1')
                ->leftJoin('mne_dqa_rptlearners_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.grade_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as gradeId', 't2.*')
                ->where('t1.kgs_eligible', 1);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
        
    public function saveDqaGrmInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $quater_id = $request->input('single_quater_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        dd($request);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_grm_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'complaint_id' => $value['complaintId'],
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'quater_id' => $quater_id,
                    'complaints_reported' => $value['complaints_reported'],
                    'complaints_recounted' => $value['complaints_recounted'],
                    'complaints_discrepancy' => $value['complaints_discrepancy'],
                    'rslvd_complaints_reported' => $value['rslvd_complaints_reported'],
                    'rslvd_complaints_recounted' => $value['rslvd_complaints_recounted'],
                    'rslvd_complaints_discrepancy' => $value['rslvd_complaints_discrepancy']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaGrmInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        $quater_id = $request->input('quater_id') ? $request->input('quater_id'): 1;
        try {
            $qry = DB::table('grm_complaint_categories as t1')
                ->leftJoin('mne_dqa_grm_info as t2', function ($join) use ($record_id,$quater_id) {
                    $join->on('t1.id', '=', 't2.complaint_id')
                        ->where(array('t2.record_id' => $record_id))
                        ->where(array('t2.quater_id' => $quater_id));
                })
                ->select('t1.*', 't1.id as complaintId', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
    
    public function saveDqaDataSrcInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_datasrc_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'datasrc_id' => $value['dataSrcId'],
                    'school_level' => $value['school_level'],
                    'district_level' => $value['district_level'],
                    'provincial_level' => $value['provincial_level']
                );
                //where data
                $where_data = array(
                    // 'id' => $value['id']
                    'id' => $value['dataSrcId']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaDataSrcInfo(Request $request)
    {
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_dqa_dataelements as t1')
                ->leftJoin('mne_dqa_datasrc_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.datasrc_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as dataSrcId', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
    
    public function saveDqaKeyIndicatorInfo(Request $request)
    {        
        $term_id = $request->input('single_term_id');
        $year_id = $request->input('single_year_id');
        $record_id = $request->input('single_record_id');
        $postdata = file_get_contents("php://input");
        $data = json_decode($postdata);
        // the post data is array thus handle as array data
        if (is_array($data)) {
            $data = json_decode($postdata, true);
        } else {
            $data = array();
            $data[] = json_decode($postdata, true);
        }
        $table_name = 'mne_dqa_keyindicators_info';
        try {
            foreach ($data as $key => $value) {
                $table_data = array(
                    'term_id' => $term_id,
                    'year_id' => $year_id,
                    'record_id' => $record_id,
                    'kpi_id' => $value['kpiId'],
                    'dq_numerators' => $value['dq_numerators'],
                    'dq_denominators' => $value['dq_denominators']
                );
                //where data
                $where_data = array(
                    'id' => $value['id']
                );
                if (recordExists($table_name, $where_data)) {
                    $table_data['updated_by'] = $this->user_id;
                    DB::table($table_name)->where($where_data)->update($table_data);
                } else {
                    $table_data['created_by'] = $this->user_id;
                    DB::table($table_name)->insert($table_data);
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Details Updated Successfully!!'
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
        return response()->json($res);
    }
    
    public function getDqaKeyIndicatorInfo(Request $request)
    {
        $term_id = $request->input('term_id');
        $year_id = $request->input('year_id');
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_dqa_key_indicators as t1')
                ->leftJoin('mne_dqa_keyindicators_info as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.kpi_id')
                        ->where(array('t2.record_id' => $record_id));
                })
                ->select('t1.*', 't1.id as kpiId', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
    }
    //End Frank
    
    //Job 22/2/2022
    public function  getKPITargets(Request $request)
    {
        $kpi_id=$request->input('kpi_id');
        $qry = Db::table('mne_kpis_targets as t1')
        ->where('t1.kpi_id', $kpi_id)
        ->selectraw('t1.id,t1.year as year_val,t1.target_type,t1.target_val,t1.baseline_val');
        $results = $qry->get();
        $res = array(
        'success' => true,
        'results' => $results
        );
        return \response()->json($res);
    }
    public function saveKPItarget(Request $request)
    {
       $kpi_id=$request->input('kpi_id');
       $year=$request->input('year');
       $target_type=$request->input('target_type'); 
       $target_val=$request->input('target_val'); 
       $baseline_val=$request->input('baseline_val');
       $user_id= $this->user_id;
       $id=$request->get('id');
       if(isset($id) && $id!="")
       {
        $previous_data = array();
        $table_name='mne_kpis_targets';
        $where=array(
            "id"=>$id
        );
        if (recordExists($table_name, $where)) {
            $table_data=array(
                "target_type"=>$target_type,
                "target_val"=>$target_val,
                "baseline_val"=>$baseline_val
            );
            $table_data['updated_at'] = Carbon::now();
            $table_data['updated_by'] = $user_id;
            $previous_data = getPreviousRecords($table_name, $where);
            $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
            //$res['record_id'] = $record_id;
            return response()->json($res);
        }
       }
       $count=Db::table('mne_kpis_targets')->where([
           "kpi_id"=>$kpi_id,
           'year'=>$year
       ])->count();
       if($count>0)
       {
           $res=[
               "success"=>false,
               "msg"=>"Year has target already",
           ];
            return response()->json($res);
       }
       $table_data=array(
           "kpi_id"=>$kpi_id,
           "year"=>$year,
           "target_type"=>$target_type,
           "baseline_val"=>$baseline_val,
           "target_val"=>$target_val,
           "created_at"=>Carbon::now(),
           "created_by"=>$user_id
       );
       
       $res = insertRecord('mne_kpis_targets', $table_data, $user_id);
       return response()->json($res);
    }
    public function saveDataCollectionToolBeneficiaryBasicInfo(Request $request)
    {
        try {
            $res = array();
            DB::transaction(function () use ($request, &$res) {
                $user_id = $this->user_id;
                $record_id = $request->input('record_id');
                $process_id = $request->input('process_id');
                $district_id = $request->input('district_id');
                $datacollection_tool_id = $request->input('datacollection_tool_id');
                $workflow_stage_id = $request->input('workflow_stage_id');
                $girl_id = $request->input('girl_id');
                $entry_year = $request->input('entry_year');
                $table_name = 'mne_datacollectiontool_dataentry_basicinfo';
                $table_data = array(
                    'datacollection_tool_id' => $datacollection_tool_id,
                    'process_id' => $process_id,
                    'workflow_stage_id' => $workflow_stage_id,
                    'entry_year' => $entry_year,
                    'entry_term' => $request->input('entry_term'),
                    "school_id" => $request->input('sch_code'),
                    "district_id" => $request->input('district_id')
                );
                $where = array(
                    'id' => $record_id
                );
                if (isset($record_id) && $record_id != "") {
                    $previous_data = array();
                    if (recordExists($table_name, $where)) {
                        $table_data['updated_at'] = Carbon::now();
                        $table_data['updated_by'] = $user_id;
                        $table_data['curr_from_userid'] = $user_id;
                        $table_data['curr_to_userid'] = $user_id;
                        $previous_data = getPreviousRecords($table_name, $where);
                        $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                        $res['record_id'] = $record_id;
                    }
                    $ref_no = $previous_data[0]['reference_no'];
                    $folder_id = $previous_data[0]['folder_id'];
                } else {
                    //Check if data has been captured already
                    $where_check = array(
                        'girl_id' => $girl_id,
                        'entry_year' => $entry_year
                    );
                    $captured = getTableData('mne_beneficiary_info', $where_check);
                    if (!is_null($captured)) {
                        $res = array(
                            'success' => false,
                            'message' => 'Information for: ' . $captured->beneficiary_name . ' already captured,
                            for year: ' . $captured->entry_year
                        );
                    }
                    $codes_array = array(
                        'tool_code' => getSingleRecordColValue('mne_datacollection_tools', array('id' => $datacollection_tool_id), 'code'),
                        'district_code' => getSingleRecordColValue('districts', array('id' => $district_id), 'code')
                    );
                    $ref_details = generateRecordRefNumber(4, $process_id, $district_id, $codes_array, $table_name, $user_id);
                    if ($ref_details['success'] == false) {
                        return \response()->json($ref_details);
                    }
                    $ref_no = $ref_details['ref_no'];
                    //todo DMS
                    $parent_id = 258379;
                    $folder_id = createDMSParentFolder($parent_id, '', $ref_no, '', $this->dms_id);
                    createDMSModuleFolders($folder_id, 35, $this->dms_id);
                    //end DMS
                    $table_data['folder_id'] = $folder_id;
                    $table_data['view_id'] = generateRecordViewID();
                    $table_data['reference_no'] = $ref_no;
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
                $request->request->add(['record_id' => $res['record_id']]);
                $beneficiaryInfoSave = $this->saveBeneficiaryInfo($request);

                $res['ref_no'] = $ref_no;
                $res['folder_id'] = $folder_id;
                $res['beneficiaryInfoId'] = $beneficiaryInfoSave->getData()->record_id;
            }, 5);
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
        return response()->json($res);
    }

    public function saveBeneficiaryInfo(Request $request)
    {
        $user_id = $this->user_id;
        $id = $request->input('id');
        $table_name = 'mne_beneficiary_info';
        try {
            $table_data = array(
                'beneficiary_id' => $request->input('beneficiary_id'),
                'girl_id' => $request->input('girl_id'),
                'record_id' => $request->input('record_id'),
                'beneficiary_name' => $request->input('beneficiary_name'),
                'dob' => Carbon::parse($request->input('dob')),
                'entry_year' => $request->input('entry_year'),
                'district_name' => $request->input('district_name'),
                'sch_code' => $request->input('sch_code'),
                'school' => $request->input('school'),
                'district_id' => $request->input('district_id'),
                'special_comments' => $request->input('special_comments')
            );
            $where = array(
                'id' => $id
            );
            if (isset($id) && $id != "") {
                if (recordExists($table_name, $where)) {
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $id;
                } else {
                    $table_data['created_at'] = Carbon::now();
                    $table_data['created_by'] = $user_id;
                    $res = insertRecord($table_name, $table_data, $user_id);
                }
            } else {
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);
            }
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
        return response()->json($res);
    }

    public function getMnEDashboardKPIsGraphData(Request $request)
    {
        try {
            $data = $this->getMnEDashboardKPIs($request);
            $data = json_decode($data->getContent());
            $results = $data->results;
            $requireAttentionCount = 0;
            $laggingBehindCount = 0;
            $ontrackCount = 0;
            $unclassifiedCount = 0;
            foreach ($results as $result) {
                if ($result->kpistatus_id == 1) {
                    $requireAttentionCount++;
                }
                if ($result->kpistatus_id == 2) {
                    $laggingBehindCount++;
                }
                if ($result->kpistatus_id == 3) {
                    $ontrackCount++;
                }
                if ($result->kpistatus_id == 4) {
                    $unclassifiedCount++;
                }
            }
            $visualDetails = array(
                array(
                    'kpi_status' => 'Requires Attention',
                    'count' => $requireAttentionCount
                ),
                array(
                    'kpi_status' => 'Lagging Behind',
                    'count' => $laggingBehindCount
                ),
                array(
                    'kpi_status' => 'On Track',
                    'count' => $ontrackCount
                ),
                array(
                    'kpi_status' => 'Unclassified',
                    'count' => $unclassifiedCount
                )
            );
            $res = array(
                'success' => true,
                'results' => $visualDetails,
                'message' => 'Successful'
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
        return response()->json($res);
    }


    public function getMnEDashboardKPIsOLD(Request $request)
    {
        $frequency_id = $request->input('frequency_id');
        $kpistatus_id = $request->input('kpistatus_id');
        $year = $request->input('year_id');
        $request->request->add(['year_from' => $year]);
        $request->request->add(['year_to' => $year]);
        try {
            $qry = Db::table('mne_kpis as t1')
                ->where(array(//'t1.frequency_id'=>$frequency_id,
                              't1.active'=>1,
                               't1.is_visible_dashboard'=>1));
            $qryResults = $qry->get();
            $results = $this->determineKPIPerformance($qryResults, $request, $kpistatus_id);
            $res = array(
                'success' => true,
                'results' => $results
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
        return response()->json($res);
    }
    public function getMnEDashboardKPIs(Request $request)
    {

        try {
                //get filtered results
                $filterdatefrom =$request->input('datefrom');
                $filterdateto=$request->input('dateto');
                $filterprovince=$request->input('province_id');
                $filterdistrict=$request->input('district_id');

                if($filterdatefrom && $filterdateto){
                  $year_from =date('Y', strtotime($filterdatefrom));
                  $year_to=date('Y', strtotime($filterdateto));
                  $years=$this->getallyears($year_from,$year_to);
                }else{
                    $year_from='';
                    $year_to='';
                    $years=$this->getallyears($year_from,$year_to);
                }
                $qry = Db::table('mne_kpis as t1')
                        ->select('id','kpi')
                        ->where(array('t1.active'=>1,
                                       't1.is_visible_dashboard'=>1));
                $qryResults = $qry->get();
                 foreach($qryResults as $key=>$res){
                    foreach($years as $year){
                        $results[]=array(
                        'kpi'=>$res->kpi,
                        'target'=> $this->getKPITarget($res->id,$year),
                         'achieved'=>$this->determineKPIPerformance($res->id,$year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict),
                        'year' => $year);
                      }
                 }


                $res = array(
                    'success' => true,
                    'results' => $results
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
        return response()->json($res);
    }

    public function determineKPIPerformanceOLD($results, Request $request, $kpistatus_id)
    {

        foreach ($results as $key => $result) {
            $details = array(
                'target_met' => '',
                'val' => '',
                'kpistatus_id' => '',
                'kpistatus' => ''
            );
            //New KPIS
            if ($result->id == 33) {
                $details = $this->determineKPI33Performance($request, $result);
            }else if ($result->id == 41) {
                $details = $this->determineKPI41Performance($request, $result);
            }else if ($result->id == 46) {
                $details = $this->determineKPI46Performance($request, $result);
            }else if ($result->id == 48) {
                $details = $this->determineKPI48Performance($request, $result);
            }else if ($result->id == 49) {
                $details = $this->determineKPI49Performance($request, $result);
            }else if ($result->id == 50) {
                $details = $this->determineKPI50Performance($request, $result);
            }else if ($result->id == 51) {
                $details = $this->determineKPI51Performance($request, $result);
            }
            if (validateisNumeric($kpistatus_id)) {
                if ($details['kpistatus_id'] != $kpistatus_id) {
                    unset($results[$key]);
                    continue;
                }
            }
            if ($details['kpistatus_id'] == '') {
                unset($results[$key]);
                continue;
            }
            $results[$key]->target_met = $details['target_met'];
            $results[$key]->val = $details['val'];
            $results[$key]->kpistatus_id = $details['kpistatus_id'];
            $results[$key]->kpistatus = $details['kpistatus'];
        }
        return array_values(convertStdClassObjToArray($results));
    }
    public function determineKPIPerformance($kpi_id,$year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict)
    {
        $val='';

            //New KPIS
            if ($kpi_id == 67) {
                //previous Calculations determineKPI33Performance
                $val = $this->determineKPI67Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else 
            if ($kpi_id == 68) {
                  //KPI 46 Calculations
                $val = $this->determineKPI46Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 69) {
                 //KPI 41 Calculations
                $val = $this->determineKPI41Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 70) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }
            // else if ($kpi_id == 71) {
            //     //KPI 11 Calculations
            //     $val = $this->determineKPI51Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            // }
            else if ($kpi_id == 72) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 73) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 74) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 75) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }else if ($kpi_id == 76) {
                $val = $this->determineKPI50Performance($year,$filterdatefrom,$filterdateto,$filterprovince,$filterdistrict);
            }
        return $val;
    }
       public function getKPITarget($kpi_id,$year){
        //Get Target
        $targetqry=Db::table('mne_kpis_targets')
                    ->select('target_val','baseline_val')
                    ->where('year',$year)
                    ->where('kpi_id',$kpi_id)
                    ->get();
        $targetresult=json_decode($targetqry);
        
        if(!empty($targetresult)){

               $target=$targetresult[0]->target_val;
            }else{
                $target='No Traget set';
            }
        return $target;

    }
    public function getKPIsGraphDetails(Request $request)
    {
        try {
            $results = $this->getKPI1Graph($request);
            $res = array(
                'success' => true,
                'results' => $results
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
        return response()->json($res);
    }

    public function validateMnERecords(Request $request)
    {
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_datacollectiontool_quizes as t1')
                ->leftJoin('mne_unstructuredquizes_dataentryinfo as t2', 't1.id', '=', 't2.question_id')
                ->leftJoin('mne_quizesanswer_options as t3', 't1.id', '=', 't3.question_id')
                ->selectRaw('t1.*, t2.response, t3.value_range')
                ->where('t2.record_id', $record_id);
            $results = $qry->first();
            $returnMsg = array();
            if (is_null($results)) {
                $returnMsg['success'] = false;
                $returnMsg['message'] = 'Numeric range not found';
            } else {
                if (array_key_exists('value_range', $results)) {
                    if (is_null($results->value_range)) {
                        $returnMsg['success'] = false;
                        $returnMsg['message'] = 'Numeric range not set';
                    } else {
                        $value = $results->value_range;
                        if (Str::contains($value, '-')) {
                            $minValue = trim(Str::before($value, '-'));
                            $maxValue = trim(Str::after($value, '-'));
                            if (validateisNumeric($minValue) && validateisNumeric($maxValue)) {
                                if ($value >= $minValue && $value <= $minValue) {
                                    $returnMsg['success'] = true;
                                    $returnMsg['message'] = 'Numeric values validated';
                                } else {
                                    $returnMsg['success'] = false;
                                    $returnMsg['message'] = 'Numeric values out of range';
                                }
                            } else {
                                $returnMsg['success'] = false;
                                $returnMsg['message'] = 'Set range not numeric';
                            }
                        } else {
                            $returnMsg['success'] = false;
                            $returnMsg['message'] = 'wrong range set';
                        }
                    }
                } else {
                    $returnMsg['success'] = false;
                    $returnMsg['message'] = 'Column not found';
                }
            }
            $res = array(
                'success' => true,
                'message' => returnMessage($returnMsg)
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

    public function prepareSpotCheckTool(Request $request)
    {
        $record_id = $request->input('record_id');
        try {
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->join('mne_institutional_info as t2', 't1.id', '=', 't2.record_id')
                ->join('mne_spotcheck_institutionalinfo as mne_spotcheck_institutionalinfo', 'mne_spotcheck_institutionalinfo.record_id', '=', 't2.record_id')
                ->select('t2.*','mne_spotcheck_institutionalinfo.*')
                ->where('t1.id', $record_id);
            $results = $qry->first();
            $res = array(
                'success' => true,
                'results' => $results
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
        return \response()->json($res);
    }
    
    public function saveRecordSubmissionReport(Request $request)
    {        
        $user_id = $this->user_id;
        $record_id = $request->input('record_id');
        $is_complete = $request->input('is_complete');
        $blank_cell_count = $request->input('blank_cell_count');
        $grid_title = $request->input('grid_title');
        $grid_xtype = $request->input('grid_xtype');
        $table_name = 'mne_record_submission_report';
        $res = array();
        try {
            $table_data = array(
                'record_id' => $record_id,
                'is_complete' => $is_complete,
                'blank_cell_count' => $blank_cell_count,
                'grid_title' => $grid_title,
                'grid_xtype' => $grid_xtype
            );
            //where data
            $where_data = array(
                'record_id' => $record_id,
                'grid_xtype' => $grid_xtype
            );
            if (recordExists($table_name, $where_data)) {
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $this->user_id;
                DB::table($table_name)->where($where_data)->update($table_data);
            } else {
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $this->user_id;
                DB::table($table_name)->insert($table_data);
            }
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
        return response()->json($res);
    }
    
    public function getRecordSubmissionReportDetails(Request $request)
    {
        $record_id = $request->input('record_id');
        $grid_xtype = $request->input('grid_xtype');
        $tool_id = $request->input('tool_id');
        $results = array();
        $allow_submit = true;
        try {
            $where_data = array(
                'record_id' => $record_id
            );
            $where_not_complete = array(
                'is_complete' => 0
            );
            $qry = DB::table('mne_record_submission_report as t1')->select('t1.*')->where($where_data);
            $query = clone $qry;
            $query_not_complete = $qry->where($where_not_complete)->get();
            $query_not_complete_count = $query_not_complete->count();
            $query_total = $query->get();
            $query_total_count = $query_total->count();
            if($query_not_complete_count > 0) {
                $results = $query_not_complete;
                $allow_submit = false;
            } else {
                if($query_total_count >= 0) {
                    if($tool_id == 1) {
                        //changed 6thjan2024 - Maureen
                       //entry term progression grid is only filled in Term 1 only
                        $entrytermqry=DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')->select('t1.*')->where('id', $record_id)->get();
                         $entryTermValue = $entrytermqry[0]->entry_term;
                         $created_at = $entrytermqry[0]->created_at;
                         $rec_created_at = date('Y', strtotime($created_at));

                        if($query_total_count < 7 && $entryTermValue > 1) {
                            if($entryTermValue > 1){
                                $allow_submit = true;
                            }else{
                                if($rec_created_at<=2021 && $entryTermValue =1){
                                    $allow_submit = true;
                                }else{
                                    $allow_submit = false;
                                } 
                                   
                            }
                            
                        }
                    } else if($tool_id == 7) {
                        if($query_total_count < 11) {
                            $allow_submit = false;
                        }
                    }
                }
                $results = $query_total;
            }
            $res = array('success' => true, 'results' => $results, 'allow_submit' => $allow_submit);
        } catch (\Exception $e) {
            $res = array('success' => false, 'message' => $e->getMessage());
        } catch (\Throwable $throwable) {
            $res = array('success' => false, 'message' => $throwable->getMessage());
        }
        return response()->json($res);
    }
    
    public function saveDqatNotes(Request $req)
    {
        try {
            $user_id = $this->user_id;
            $post_data = $req->all();
            $table_name = $post_data['table_name'];
            $record_id = $post_data['record_id'];
            $notes = $post_data['notes'];
            $res = array();
            //unset unnecessary values
            unset($post_data['_token']);
            unset($post_data['table_name']);
            unset($post_data['id']);
            $table_data = array('notes' => $notes);
            $table_data['created_by'] = $user_id;
            //add extra params
            $table_data['created_at'] = Carbon::now();
            $table_data['created_by'] = $user_id;
            $where = array(
                'id' => $record_id
            );
            if (isset($record_id) && $record_id != "") {
                if (recordExists($table_name, $where)) {
                    unset($table_data['created_at']);
                    unset($table_data['created_by']);
                    $table_data['updated_at'] = Carbon::now();
                    $table_data['updated_by'] = $user_id;
                    $previous_data = getPreviousRecords($table_name, $where);
                    $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                    $res['record_id'] = $record_id;
                }
            } else {
                $res = insertRecord($table_name, $table_data, $user_id);
            }
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
        return response()->json($res);
    }
    //end frank
    //Start Maureen
    public function detkpi(Request $request)
    {
        $kpi_no = $request->input('kpi_id');
        $params = array($request);
        if ($kpi_no == 1) {
            $this->getkpiGendertermlySummary($request);
        } else if ($kpi == 2) {
            $this->getkpitermlysummary($request);
        }

    }

    public function getkpigendertermlySummary(Request $request)
    {
        $province_id = $request->input('province_id');
        $year = $request->input('year');
        $term = $request->input('term_id');
        if (isset($province_id)) {
            $table = 'districts';
            $field = 't1.district_id';
            $groupBy = 't1.district_id';

        } else {
            $table = 'provinces';
            $field = 't1.province_id';
            $groupBy = 't3.id';
        }

        try {
            $qry = DB::table('mne_datacollectiontool_dataentry_basicinfo as t1')
                ->leftJoin('mne_pupilsstatistics_info as t2', 't1.id', '=', 't2.record_id')
                ->join($table . ' as t3', $field, '=', 't3.id')
                ->select('*', DB::raw('SUM(t2.total_boys) as num_boys'), DB::raw('SUM(t2.total_othergirls) as num_girls'), 't3.name AS province_name');
            if (isset($province_id) && isset($term) && isset($year)) {
                $qry->where(array('t1.province_id' => $province_id,
                    't2.term_id' => $term,
                    't2.year_id' => $year));
            } else if (isset($province_id) && isset($term)) {
                $qry->where(array('t1.province_id' => $province_id,
                    't2.term_id' => $term
                ));

            } else if (isset($province_id) && isset($year)) {
                $qry->where(array(
                    't1.province_id' => $province_id,
                    't2.year_id' => $year
                ));
            } else if (isset($province_id)) {
                $qry->where(array(
                    't1.province_id' => $province_id
                ));
            } else if (isset($term) && isset($year)) {
                $qry->where(array('t2.term_id' => $term,
                    't2.year_id' => $year
                ));
            }
            $qry->groupBy($groupBy);
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results
            );
        } catch (\Exception $e) {
            $res = array('success' => false,
                'message' => $e->getMessage()
            );

        } catch (\Throwable $throwable) {
            $res = array(
                'success' => true,
                'message' => $throwable->getMessage()
            );

        }
        return response()->json($res);
    }
    
    public function getkpitermlysummary(Request $request)
    {
        try {
            $qry = DB::table('mne_progression_info as t1')
                ->select('t1.*',
                    DB::raw('SUM(if(t1.term_id=1,t1.kgs_enrolled_thisyear,0)) as term_one'),
                    DB::raw('SUM(if(t1.term_id=2,t1.kgs_enrolled_thisyear,0)) as term_two'),
                    DB::raw('SUM(if(t1.term_id=3,t1.kgs_enrolled_thisyear,0)) as term_three'), 't3.name as province_name')
                ->join('mne_datacollectiontool_dataentry_basicinfo as t2', 't1.record_id', '=', 't2.id')
                ->join('provinces as t3', 't2.province_id', '=', 't3.id')
                ->join('districts as t4', 't2.district_id', '=', 't4.id')
                ->groupBy('t2.province_id');
            $results = $qry->get();
            $res = array('success' => true, 'results' => $results);
        } catch (\Exception $e) {
            $res = array('success' => false, 'message' => $e->getMessage());
        } catch (\Throwable $throwable) {
            $res = array('success' => false, 'message' => $throwable->getMessage());
        }
        return response()->json($res);
    }

    public function exportMandERecords(Request $request)
    {
        try {
            $extraParams = urldecode($request->input('extraparams'));
            $extraParams = json_decode($extraParams, true);
            if (empty($extraParams)) {
                $extraParams = array();
            }
            $route = urldecode($request->input('route'));
            $routeArray = explode('/', $route);
            $function = last($routeArray);

            $myRequest = new Request($extraParams);
            $results = json_decode($this->$function($myRequest)->content(), true);

            $data = $results['results'];
            return exportSystemRecords($request, $data);
        } catch (\Exception $exception) {
            return array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            return array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
    }
    //Start Maureen
    public function getspotCheckKgsenrolment(Request $request){
        $year_id = $request->input('year_id');
        $term_id = $request->input('term_id');
        $record_id = $request->input('record_id');
        $school_id = $request->input('school_id');
        try {
            $qry = DB::table('mne_spotcheck_agegroups as t1')
                ->leftJoin('mne_spotcheck_kgs_girl_enrolments as t2', function ($join) use ($record_id) {
                    $join->on('t1.id', '=', 't2.age_group_id')
                    ->where(array('t2.record_id' => $record_id));
                })
                /*->leftJoin('mne_spotcheck_kgs_girl_enrolments as t2','t1.id', '=', 't2.age_group_id') */
                ->select('t1.*', 't1.id as age_group_main_id', 't2.*');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);

    }
    public function getEnrolledSummation($record_id,$gradeId){
        $total_record = 0;
        if($gradeId==8){
            $column_name='kgsgirls_grade_eight';
        }else if($gradeId==9){
            $column_name='kgsgirls_grade_nine';
        }else if($gradeId==10){
            $column_name='kgsgirls_grade_ten';
        }else if($gradeId==11){
            $column_name='kgsgirls_grade_eleven';
        }else if($gradeId==12){
            $column_name='kgsgirls_grade_twelve';
        }
        $record= DB::table('mne_spotcheck_kgs_girl_enrolments')
                ->select(DB::raw("sum($column_name) as total_record"))
                ->where('record_id',$record_id)
                ->first();
                if($record){
                    $total_record = $record->total_record;
                }
                return intval($total_record);
    }

     public function getspotCheckBoardingFacility(Request $request)
        {
            $year_id = $request->input('year_id');
            $term_id = $request->input('term_id');
            $record_id = $request->input('record_id');
            try {
                //create an array of the required data 
                // and then get the summations based on sub-functionn
                $qry = DB::table('school_grades as t1')
                    ->leftJoin('mne_spotcheck_boardingfacility as t2', function ($join) use ($record_id) {
                        $join->on('t1.id', '=', 't2.grade_id')
                            ->where(array('t2.record_id' => $record_id));
                    })
                    ->select('t1.*', 't1.id as gradeId', 't2.*')
                    ->where('t1.kgs_eligible', 1)
                    ->orderBy('t1.id', 'ASC');
                $qry_res = $qry->get();
                foreach($qry_res as $row){
                     $results[]=array(
                        'id'=>$row->id,
                        'gradeId'=>$row->gradeId,
                        'kgsgirls_enrolled'=>intval($this->getEnrolledSummation($record_id,$row->gradeId)),
                        'kgsgirls_paidfor'=>$row->kgsgirls_paidfor,
                        'kgsgirls_fm_boarding'=>$row->kgsgirls_fm_boarding,
                        'kgsgirls_managed_sch'=>$row->kgsgirls_managed_sch,
                        'kgsgirls_wkly_facility'=>$row->kgsgirls_wkly_facility,
                        'kgsgirls_daySch'=>$row->kgsgirls_daySch,
                        'created_by'=>$row->created_by,
                        'updated_by'=>$row->updated_by,
                        'updated_at'=>$row->updated_at,
                        'created_at'=>$row->created_at,
                        'year_id'=>$row->year_id,
                        'term_id'=>$row->term_id,
                        'record_id'=>$row->record_id,
                        'name'=>$row->name);
                }
                $res = array(
                    'success' => true,
                    'results' => $results,
                    'message' => 'All is well'//returnMessage($results)
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
            return response()->json($res);
        }
        public function getspotcheckdropoutInfo(Request $request){

            $year_id = $request->input('year_id');
            $term_id = $request->input('term_id');
            $record_id = $request->input('record_id');
            try {
                $qry = DB::table('mne_dropout_reasons as t1')
                    ->leftJoin('mne_spotcheck_dropouts_info as t2', function ($join) use ($record_id) {
                        $join->on('t1.id', '=', 't2.reason_id')
                            ->where(array('t2.record_id' => $record_id));
                    })
                     /*->leftJoin('mne_spotcheck_dropouts_info as t2', 't1.id', '=', 't2.reason_id')*/
                    ->select('t1.*', 't1.id as reason_main_id', 't2.*');
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results,
                    'message' => returnMessage($results)
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
            return response()->json($res);
            
        }
        public function getspotCheckPerfomance(Request $request){
            $year_id = $request->input('year_id');
            $term_id = $request->input('term_id');
            $record_id = $request->input('record_id');
            try {
                $qry = DB::table('school_grades as t1')
                    ->leftJoin('mne_spotcheck_perfomance_info as t2', function ($join) use ($record_id) {
                        $join->on('t1.id', '=', 't2.grade_id')
                            ->where(array('t2.record_id' => $record_id));
                    })
                    /*->leftJoin('mne_spotcheck_perfomance_info as t2','t1.id', '=', 't2.grade_id')*/
                    ->select('t1.*', 't1.id as gradeId', 't2.*')
                    ->where('t1.kgs_eligible', 1);
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results,
                    'message' => returnMessage($results)
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
            return response()->json($res);
        }
        public function getspotcheckProgressionInfo(Request $request)
        {
            $year_id = $request->input('year_id');
            $term_id = $request->input('term_id');
            $record_id = $request->input('record_id');
            try {
                $qry = DB::table('school_grades_transitions as t1')
                    ->leftJoin('mne_spotcheck_progression_info as t2', function ($join) use ($record_id, $year_id, $term_id) {
                        $join->on('t1.id', '=', 't2.transition_id')
                            ->where(array('t2.year_id' => $year_id, 't2.term_id' => $term_id, 't2.record_id' => $record_id));
                    })
                    ->select('t1.*', 't1.id as transitionId', 't2.*')
                    ->where('t1.kgs_eligible', 1);
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results,
                    'message' => returnMessage($results)
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
            return response()->json($res);
        }
        public function savespotcheckdropoutInfo(Request $request)
        {
            $term_id = $request->input('single_term_id');
            $year_id = $request->input('single_year_id');
            $record_id = $request->input('single_record_id');

            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'mne_spotcheck_dropouts_info';
            try {
                foreach ($data as $key => $value) {
                    $table_data = array(
                        'reason_id' => $value['reason_main_id'],
                        'term_id' => $term_id,
                        'year_id' => $year_id,
                        'record_id' => $record_id,
                        'kgs_grade_eight' => $value['kgs_grade_eight'],
                        'kgs_grade_nine' => $value['kgs_grade_nine'],
                        'kgs_grade_ten' => $value['kgs_grade_ten'],
                        'kgs_grade_eleven' => $value['kgs_grade_eleven'],
                        'kgs_grade_twelve' => $value['kgs_grade_twelve'],
                        'non_kgs_grade_eight' => $value['non_kgs_grade_eight'],
                        'non_kgs_grade_nine' => $value['non_kgs_grade_nine'],
                        'non_kgs_grade_ten' => $value['non_kgs_grade_ten'],
                        'non_kgs_grade_eleven' => $value['non_kgs_grade_eleven'],
                        'non_kgs_grade_twelve' => $value['non_kgs_grade_twelve']
                    );
                    //where data
                    $where_data = array(
                        'id' => $value['id']
                    );
                   
                    if (recordExists($table_name, $where_data)) {
                        echo "recordExists";
                        $table_data['updated_by'] = $this->user_id;
                        DB::table($table_name)
                            ->where($where_data)
                            ->update($table_data);
                    } else {
                        // echo 'records do not exist';
                        // echo $table_name;
                        //   print_r($table_data);
                        //   exit();
                        $table_data['created_by'] = $this->user_id;
                        DB::table($table_name)
                            ->insert($table_data);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Details Updated Successfully!!'
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
            return response()->json($res);
        }
        public function savespotCheckKgsenrolment(Request $request)
        {

            $term_id = $request->input('single_term_id');
            $year_id = $request->input('single_year_id');
            $record_id = $request->input('single_record_id');
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'mne_spotcheck_kgs_girl_enrolments';
            try {
                foreach ($data as $key => $value) {
                    $table_data = array(
                        'age_group_id' => $value['age_group_main_id'],
                        'term_id' => $term_id,
                        'year_id' => $year_id,
                        'record_id' => $record_id,
                        'kgsgirls_grade_eight' => $value['kgsgirls_grade_eight'],
                        'kgsgirls_grade_nine' => $value['kgsgirls_grade_nine'],
                        'kgsgirls_grade_ten' => $value['kgsgirls_grade_ten'],
                        'kgsgirls_grade_eleven' => $value['kgsgirls_grade_eleven'],
                        'kgsgirls_grade_twelve' => $value['kgsgirls_grade_twelve']
                    );
                    //where data
                    $where_data = array(
                        'id' => $value['id']
                    );
                    if (recordExists($table_name, $where_data)) {
                        $table_data['updated_by'] = $this->user_id;
                        DB::table($table_name)->where($where_data)->update($table_data);
                    } else {
                        $table_data['created_by'] = $this->user_id;
                        DB::table($table_name)->insert($table_data);
                    }
                }

                $res = array(
                    'success' => true,
                    'message' => 'Details Updated Successfully!!'
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
            return response()->json($res);
        }
        public function savespotCheckBoardingFacility(Request $request)
        {
            $term_id = $request->input('single_term_id');
            $year_id = $request->input('single_year_id');
            $record_id = $request->input('single_record_id');
            $postdata = file_get_contents("php://input");
            $data = json_decode($postdata);
            // the post data is array thus handle as array data
            if (is_array($data)) {
                $data = json_decode($postdata, true);
            } else {
                $data = array();
                $data[] = json_decode($postdata, true);
            }
            $table_name = 'mne_spotcheck_boardingfacility';
            try {
                foreach ($data as $key => $value) {
                    $table_data = array(
                        'grade_id' => $value['gradeId'],
                        'term_id' => $term_id,
                        'year_id' => $year_id,
                        'record_id' => $record_id,
                        'kgsgirls_enrolled' => $value['kgsgirls_enrolled'],
                        'kgsgirls_paidfor' => $value['kgsgirls_paidfor'],
                        'kgsgirls_fm_boarding' => $value['kgsgirls_fm_boarding'],
                        'kgsgirls_managed_sch' => $value['kgsgirls_managed_sch'],
                        'kgsgirls_wkly_facility' => $value['kgsgirls_wkly_facility'],
                        'kgsgirls_daySch' => $value['kgsgirls_daySch']
                    );
                    //where data
                    $where_data = array(
                        'id' => $value['id']
                    );
                    if (recordExists($table_name, $where_data)) {
                        $table_data['updated_by'] = $this->user_id;
                        DB::table($table_name)
                            ->where($where_data)
                            ->update($table_data);
                    } else {
                        $table_data['created_by'] = $this->user_id;
                        DB::table($table_name)
                            ->insert($table_data);
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Details Updated Successfully!!'
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
            return response()->json($res);
        }   
        public function savespotCheckPerfomance(Request $request)
            {
                $term_id = $request->input('single_term_id');
                $year_id = $request->input('single_year_id');
                $record_id = $request->input('single_record_id');
                $postdata = file_get_contents("php://input");
                $data = json_decode($postdata);
                // the post data is array thus handle as array data
                if (is_array($data)) {
                    $data = json_decode($postdata, true);
                } else {
                    $data = array();
                    $data[] = json_decode($postdata, true);
                }
                $table_name = 'mne_spotcheck_perfomance_info';
                try {
                    foreach ($data as $key => $value) {
                        $table_data = array(
                            'grade_id' => $value['gradeId'],
                            'term_id' => $value['term_id'],
                            'year_id' => $value['year_id'],
                            'record_id' => $record_id,
                            'kgs_mathematics' => $value['kgs_mathematics'],
                            'kgs_english' => $value['kgs_english'],
                            'kgs_science' => $value['kgs_science'],
                            'non_kgs_mathematics' => $value['non_kgs_mathematics'],
                            'non_kgs_english' => $value['non_kgs_english'],
                             'non_kgs_science' => $value['non_kgs_science']
                        );
                        //where data
                        $where_data = array(
                            'id' => $value['id']
                        );
                        if (recordExists($table_name, $where_data)) {
                            $table_data['updated_by'] = $this->user_id;
                            DB::table($table_name)
                                ->where($where_data)
                                ->update($table_data);
                        } else {
                            $table_data['created_by'] = $this->user_id;
                            DB::table($table_name)
                                ->insert($table_data);
                        }
                    }
                    $res = array(
                        'success' => true,
                        'message' => 'Details Updated Successfully!!'
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
                return response()->json($res);
            }

        public function getgrmsummarygraph(Request $req)
        {
            // code...

            $year_from = $req->year_from;
             $year_to = $req->year_to;
             $province_id = $req->province_id;
             $district_id = $req->district_id;
         
            try {
                  $qry = DB::table('grm_grievance_statuses as grm_grievance_statuses')
                ->select(DB::raw("grm_grievance_statuses.name as status,YEAR (grm_complaint_details.complaint_lodge_date) as year,
                    COUNT(distinct(if(grm_complaint_details.record_status_id=1,grm_complaint_details.id,0))) as ongoing,
                    COUNT(distinct(if(grm_complaint_details.record_status_id=2,grm_complaint_details.id,0))) as resolved,
                    COUNT(distinct(if(grm_complaint_details.record_status_id=3,grm_complaint_details.id,0))) as referred,
                    COUNT(distinct(if(grm_complaint_details.record_status_id=4,grm_complaint_details.id,0))) AS appealed ,
                    COUNT(distinct(if(grm_complaint_details.record_status_id=5,grm_complaint_details.id,0))) as pendingg"))
                ->join('grm_complaint_details as grm_complaint_details' ,'grm_grievance_statuses.id', '=', 'grm_complaint_details.record_status_id')
                ->where('grm_complaint_details.complaint_lodge_date','>=','2018-01-01 13:50:52')
               // ->groupBy('year', 'status');
                  ->groupBy('year');

               $year_from = $req->year_from;
             $year_to = $req->year_to;
             $province_id = $req->province_id;
             $district_id = $req->district_id;
                if(validateisNumeric($year_from)){
                    $qry->whereRaw("YEAR(grm_complaint_details.complaint_lodge_date) >= $year_from");

                }
                if(validateisNumeric($year_to)){
                    $qry->whereRaw("YEAR(grm_complaint_details.complaint_lodge_date) <= $year_to");
                }
                if(validateisNumeric($province_id)){
                    $qry->where('grm_complaint_details.province_id',$province_id);
                    
                }
                if(validateisNumeric($district_id)){
                    $qry->where('grm_complaint_details.district_id',$district_id);
                    
                }
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results
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
            return \response()->json($res);
        }
        public function getgrmtotalsummarygrid(Request $req)
        {
            // code...
            try {
                  $qry = DB::table('grm_grievance_statuses as grm_grievance_statuses')
                ->select(DB::raw("grm_grievance_statuses.name as status,YEAR(grm_complaint_details.complaint_lodge_date) as year,
                   COUNT(if((grm_complaint_details.record_status_id=1||grm_complaint_details.record_status_id=2|| grm_complaint_details.record_status_id=3||grm_complaint_details.record_status_id=4||grm_complaint_details.record_status_id=5),grm_complaint_details.id,0)) as total"))
                ->join('grm_complaint_details as grm_complaint_details' ,'grm_grievance_statuses.id', '=', 'grm_complaint_details.record_status_id')
                 ->where('grm_complaint_details.complaint_lodge_date','>=','2018-01-01 13:50:52')
                ->groupBy('year', 'status');
                $year_from = $req->year_from;
             $year_to = $req->year_to;
             $province_id = $req->province_id;
             $district_id = $req->district_id;
                if(validateisNumeric($year_from)){
                    $qry->whereRaw("YEAR(grm_complaint_details.complaint_lodge_date) >= $year_from");

                }
                if(validateisNumeric($year_to)){
                    $qry->whereRaw("YEAR(grm_complaint_details.complaint_lodge_date) <= $year_to");
                }
                if(validateisNumeric($province_id)){
                    $qry->where('grm_complaint_details.province_id',$province_id);
                    
                }
                if(validateisNumeric($district_id)){
                    $qry->where('grm_complaint_details.district_id',$district_id);
                    
                }
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results
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
            return \response()->json($res);
        }
        public function getBenSupportedGraph()
        {
            // code...
            try {
                  $qry = DB::table('beneficiary_information AS t1')
                ->select(DB::raw("COUNT(t1.beneficiary_id) AS eligible,
                                    COUNT(t2.annual_fees) as supported,
                                    (COUNT(t2.annual_fees)/COUNT( t1.beneficiary_id) * 100 ) as percentage,
                                    YEAR (t1.kgs_takeup_date ) as year"))
                ->join('beneficiary_enrollments as t2' ,'t1.id', '=', 't2.beneficiary_id')
                ->where('t1.payment_eligible',1)
                ->groupBy('year');
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results
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
            return \response()->json($res);
        }
        Public function getProgressionRatesGraph(){
            try {
                   $qry = DB::table('mne_progression_info AS t1')
                    ->select(DB::raw("(SUM(t1.kgs_finished)/ SUM( t1.kgs_enrolled) *100) AS kgs_progression,(SUM(t1.nonkgs_finished)/ SUM( t1.nonkgs_enrolled) *100) AS non_kgs_progression,YEAR (t1.created_at ) as year"))
                    ->groupBy('year');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results,
                        'message' => returnMessage($results)
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
            return response()->json($res);

        }
    public function getGraduationRatesGraph(){
       try {
                $qry = DB::table('mne_progression_info AS t1')
                    ->select(DB::raw(" ((SUM(t1.kgs_finished)/SUM(t1.kgs_enrolled))*100) as kgs_grad,
                                     ((SUM(t1.nonkgs_finished)/SUM(t1.nonkgs_enrolled))*100) as nonkgs_grad,
                                      YEAR (t1.created_at ) as year"))
                    ->where('t1.transition_id','>=',11)
                    ->groupBy('year');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results,
                        'message' => returnMessage($results)
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
            return response()->json($res);
     }
     public function getdropoutsummaryGraph(Request $req){
             $province_id = $req->province_id;
             $district_id = $req->district_id;
             $year_from= $req->year_from;
             $year_to= $req->year_to;
             /*$datefrom= $req->datefrom;
             $dateto= $req->dateto;*/

        try{
             $years=$this->getallyears($year_from,$year_to);
            //$results=array();
            foreach($years as $year)
                {   
                    $term_one=json_decode($this->dropoutsummarySubquery($year,1,$province_id,$district_id));
                    $term_two=json_decode($this->dropoutsummarySubquery($year,2,$province_id,$district_id)); 
                    $term_three=json_decode($this->dropoutsummarySubquery($year,3,$province_id,$district_id));

                      $results[]=array(
                        "year"=> $year,
                        "term_one"=>$term_one[0]->value,
                        "term_two"=>$term_two[0]->value,
                        "term_three"=>$term_three[0]->value
                    );
                }
                    $res = array(
                        'success' => true,
                        'results' => $results,
                        'message' => returnMessage($results)
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
            return response()->json($res);

     }
     public function dropoutsummarySubquery($year,$term,$province_id,$district_id){
            if($term==1){
                $monthfrom= date($year . '-01-01');
                $monthto=date($year . '-04-30');
            }else if ($term==2){
                $monthfrom=date($year.'-05-01');
                $monthto=date($year.'-08-31');
            }else if($term==3){
                $monthfrom=date($year . '-09-01');
                $monthto=date($year . '-12-31');

            }
         $qry = DB::table('case_basicdataentry_details')
                    ->select(DB::raw("YEAR(case_formrecording_date) as year, 
                        SUM(DATE(case_formrecording_date) BETWEEN '$monthfrom' AND '$monthto')  AS value"))
                    ->where('target_group_id',1);

                    if(validateisNumeric($province_id)){
                        $qry->where('province_id',$province_id);   
                    }
                    if(validateisNumeric($district_id)){
                        $qry->where('district_id',$district_id);  
                    }
            $response=$qry->get(); 
            return json_encode($response);

     }
    public function getdropoutLinkedsummaryGraph(Request $req){
             $province_id = $req->province_id;
             $district_id = $req->district_id;
             $datefrom= $req->datefrom;
             $dateto= $req->dateto;

        try{
          $qry = DB::table('case_basicdataentry_details AS t1')
                    ->select(DB::raw("COUNT(t1.target_group_id) as kgs_dropouts,
                                     (Select COUNT(service_id) From case_implemetation_details WHERE service_id=6) as kgs_linked,
                                      YEAR (t1.case_formrecording_date) as year"))
                    ->where('t1.target_group_id',3)
                    ->groupBy('year');
                if($datefrom && $dateto){
                    $qry->whereBetween('t1.created_at', [$datefrom, $dateto]);
                }
                if(validateisNumeric($province_id)){
                    $qry->where('t1.province_id',$province_id);   
                }
                if(validateisNumeric($district_id)){
                    $qry->where('t1.district_id',$district_id);  
                }
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results,
                        'message' => returnMessage($results)
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
            return response()->json($res);

     }
    public function getmnedropoutreasonsummaryGraph()
    {
        // code...
         try{
          $qry = DB::table('mne_spotcheck_dropouts_info AS t1')
                    ->select(DB::raw("t1.year_id as year,
                            SUM(if(t1.reason_id=1,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Marriage,
                            SUM(if(t1.reason_id=2,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Pregnancy,
                            SUM(if(t1.reason_id=3,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Financial_challenges,
                            SUM(if(t1.reason_id=4,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Death,
                            SUM(if(t1.reason_id=5,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Transfer,
                            SUM(if(t1.reason_id=6,COALESCE(t1.kgs_grade_eight,0)+COALESCE(t1.kgs_grade_nine,0)+COALESCE(t1.kgs_grade_ten,0),0)) AS Lack_of_interest"))
                    ->groupBy('t1.year_id');
                    $results = $qry->get();
                    $res = array(
                        'success' => true,
                        'results' => $results,
                        'message' => returnMessage($results)
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
            return response()->json($res);
    }
    //Training
    
     public function getMandETrainingInfo(){

        try {
            $qry = Db::table('mne_trainingdata_entry as t1')
                ->leftJoin('provinces as t2', 't1.province_id', '=', 't2.id')
                ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                ->leftJoin('thematic_areas as t4', 't1.thematic_area', '=', 't4.id')
                ->select('t1.*','t2.name as province_name','t3.name as district_name','t4.name as thematic_name');
               
/*           ->where('t1.active',$kpi_status);  
            if (validateisNumeric($category_id)) {
                $qry->where(array('t1.category_id'=>$category_id,'t1.active'=>1));
                echo "1";
            }*/
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);

     }
    public function getmandetrainingparticipantinfo(Request $request){
        try {
            $training_id=$request->input('training_id');
            $qry = Db::table('mne_training_attendance as t1')
                  ->leftJoin('mne_trainingdata_entry as t2', 't1.training_id', '=', 't2.id')
                ->where('t1.training_id',$training_id)
                ->select('t1.*');

            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);

     }

     //Communiction 
     public function getcommunicationcominfo()
     {
          try {
            $qry = Db::table('mne_communication_dataentry as t1')
                ->leftJoin('mne_communication_channels as t2', 't1.com_channel_id', '=', 't2.id')
                ->select('t1.*','t2.channel_name as com_channel_name');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);     
     }
    //weekly boarding
     public function getmneboardingdata(){
        try {
            $qry = Db::table('mne_weeklyboarding_dataentry as t1')
                    ->leftJoin('provinces as t2', 't1.province_id', '=', 't2.id')
                    ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                    ->leftJoin('school_types as t4', 't1.type_of_school', '=', 't4.id')
                    ->select('t1.*','t2.name as province_name','t3.name as district_name','t4.name as type_of_school');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);
     }
    //Survey
     public function getmneSurveydata(){
        try {
            $qry =  Db::table('mne_survey_dataentry as t1')
            ->leftJoin('provinces as t2', 't1.province_id', '=', 't2.id')
                    ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
                    ->select('t1.*','t2.name as province_name','t3.name as district_name');
            $results = $qry->get();
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => returnMessage($results)
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
        return response()->json($res);

     }
     //Communication

        public function getcommunicationsummarygraph()
        {
            // code...
            try {
                  $qry = DB::table('mne_communication_dataentry as t1')
                ->select(DB::raw("YEAR(t1.com_date) as year,
                                    SUM(if(t1.com_channel_id=1,1,0)) as radiosports,
                                    SUM(if(t1.com_channel_id=2,1,0)) as Jiggles,
                                    SUM(if(t1.com_channel_id=3,1,0)) as documentary,
                                    SUM(if(t1.com_channel_id=4,1,0)) as radiophone,
                                    SUM(if(t1.com_channel_id=5,1,0)) as tvshows,
                                    SUM(if(t1.com_channel_id=6,1,0)) as mediastatements,
                                    SUM(if(t1.com_channel_id=7,1,0)) as mediaposts,
                                    SUM(if(t1.com_channel_id=8,1,0)) as sensitizationmeetings"))
                ->leftjoin('mne_communication_channels as t2' ,'t2.id', '=', 't1.com_channel_id')
                ->groupBy('year');

                          /*      $results = $qry->toSql();
                print_r($results);*/
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results
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
            return \response()->json($res);
        }
        public function getcommunicationtotalsummarygrid()
        {
            // code...
            try {
                  $qry = DB::table('mne_communication_dataentry as t1')
                ->select(DB::raw("YEAR(t1.com_date) as year,t2.channel_name,
                   COUNT(if((t1.com_channel_id=1||t1.com_channel_id=2|| t1.com_channel_id=3||t1.com_channel_id=4
                                  ||t1.com_channel_id=5),t1.com_channel_id,0)) as total"))
                ->join('mne_communication_channels as t2' ,'t2.id', '=', 't1.com_channel_id')
                ->groupBy('t2.channel_name');
                $results = $qry->get();
                $res = array(
                    'success' => true,
                    'results' => $results
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
            return \response()->json($res);
        }

        public function uploadMnEDocument(Request $request){
        try {
            echo "Imefika";
            $res = array();
            $table_data = array();
            if ($request->hasFile('upload_file')) {
                $origFileName = $request->file('upload_file')->getClientOriginalName();
                if (validateExcelUpload($origFileName)) {
                    $data = Excel::toArray([],$request->file('upload_file'));
                    if(count($data) > 0) {
                        $table_data = $data[0];
                    }
                } else {
                    $res = array(
                        "success"=>false,
                        "message"=>"Invalid File Type"
                    );
                }
            }
            
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
        return response()->json($res);
    }

    //End Maureen

}
