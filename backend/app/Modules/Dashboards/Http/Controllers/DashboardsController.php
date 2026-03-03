<?php

namespace App\Modules\Dashboards\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardsController extends BaseController
{

    public function index()
    {
        return view('dashboards::index');
    }

    public function getBeneficiaryEnrollmentsRpt1()
    {
        try {
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $qry = DB::table('beneficiary_enrollement_statuses')
                ->join('beneficiary_information', function ($join) {
                    $join->on('beneficiary_enrollement_statuses.id', '=', 'beneficiary_information.enrollment_status')
                        ->on('beneficiary_information.beneficiary_status', '=', DB::raw(4));
                })
                ->selectRaw("beneficiary_enrollement_statuses.name as enrollment_status, count(*) as count")
                ->groupBy('beneficiary_enrollement_statuses.id');
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('beneficiary_information.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
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
                'results' => array(),
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    private function _search_array_by_value($array,$key,$value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }
    
            foreach ($array as $subarray) {
                $results = array_merge($results,$this->_search_array_by_value($subarray, $key, $value));
            }
        }
    
        return $results;
    }

    public function getBeneficiaryEnrollmentsRpt()
    {
        try {
            $qry = DB::table('beneficiary_enrollment_graphs as t1')
                ->select('t1.*')                
                -> orderBy('t1.id', 'DESC')->limit(1);            
            $formatted_count = $qry->get();
            $formatted_array = array(
                array('enrolment_status' => 'Active', 'no_of_girls' => $formatted_count[0]->active_count, 'take_up_status' => 0),
                array('enrolment_status' => 'Pending', 'no_of_girls' => $formatted_count[0]->pending_count, 'take_up_status' => 1),
                array('enrolment_status' => 'Suspended', 'no_of_girls' => $formatted_count[0]->suspended_count, 'take_up_status' => 1),
                array('enrolment_status' => 'Completed', 'no_of_girls' => $formatted_count[0]->completed_count, 'take_up_status' => 1)
            );
            $res = array(
                'success' => true,
                'results' => $formatted_array,
                'message' => 'All is well',
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => array(),
                'message' => $e->getMessage(),
            );
        }
        return response()->json($res);
    }
    
    /*public function getBeneficiaryEnrollmentsRpt()
    {
                try {
                        // Get default limit
                        $normalTimeLimit = ini_get('max_execution_time');//Job on 18/10/2022

                        // Set new limit
                          ini_set('max_execution_time', 0); //Job on 18/10/2022
                            $normalTimeLimit = ini_get('max_execution_time');//Job
                           // dd( $normalTimeLimit );

                    // $qry = DB::table('beneficiary_enrollments as t1')
                    //     ->join('beneficiary_information as t2', 't2.id', '=', 't1.beneficiary_id')
                    //     ->groupby('t1.beneficiary_id')
                    //     ->where('is_validated',1)
                    //     ->where('t2.beneficiary_status', 4)
                    //     ->where('t2.kgs_takeup_status', 1)
                    //     ->selectraw('t1.beneficiary_id as ben_id,t2.enrollment_status as enrollment_status');
                    $qry = DB::table('vw_maindash_graph as t1')
                        ->selectraw('t1.*');
                        $formatted_array=$qry->get();
                    $formatted_array=convertStdClassObjToArray($formatted_array);
                    $active_ben =$this-> _search_array_by_value($formatted_array,'enrollment_status',1);
                    $suspended_count=$this-> _search_array_by_value($formatted_array,'enrollment_status',2);
                    $completed_count=$this-> _search_array_by_value($formatted_array,'enrollment_status',4);
                    $pending_count=$this-> _search_array_by_value($formatted_array,'enrollment_status',5);
            

            $formatted_array = array(
                array('enrolment_status' => 'Active', 'no_of_girls' => count($active_ben), 'take_up_status' => 0),
                array('enrolment_status' => 'Pending', 'no_of_girls' => count($pending_count), 'take_up_status' => 1),
                array('enrolment_status' => 'Suspended', 'no_of_girls' => count($suspended_count), 'take_up_status' => 1),
                array('enrolment_status' => 'Completed', 'no_of_girls' => count($completed_count), 'take_up_status' => 1),
            );

                    $res = array(
                        'success' => true,
                        //'results1' => $results,
                        'results' => $formatted_array,
                        'message' => 'All is well'
                    );
                    ini_set('max_execution_time', $normalTimeLimit); //Job on 18/10/2022
                } catch (\Exception $e) {
                    $res = array(
                        'success' => false,
                        'results' => array(),
                        'message' => $e->getMessage()
                    );
                }
                return response()->json($res);
    }*/
    public function getBeneficiaryEnrollmentsRptLiveLatestlastv()
    {
        try {
            $logged_in_user = $this->user_id;
            $user_access_point = Auth::user()->access_point_id;
            $qry = DB::table('beneficiary_enrollement_statuses as t1')
                ->join('beneficiary_information as t2', 't1.id', '=', 't2.enrollment_status')
                ->selectRaw("t1.name as enrollment_status,
                    SUM(IF(t2.enrollment_status=1,1,0)) as active_count,
                    SUM(IF(t2.enrollment_status=2,1,0)) as suspended_count,
                    SUM(IF(t2.enrollment_status=4,1,0)) as completed_count,
                    SUM(IF(t2.enrollment_status=5,1,0)) as pending_count"
                )
                ->where('t2.beneficiary_status', 4)
                ->where('t2.kgs_takeup_status', 1);
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->whereIn('t2.district_id', function ($query) use ($logged_in_user) {
                    $query->select(DB::raw('user_district.district_id'))
                        ->from('user_district')
                        ->whereRaw('user_district.user_id=' . $logged_in_user);
                });
            }
            // $qry->groupBy('t1.id');
            $results = $qry->get();
            $formatted_array = array();
            foreach ($results as $result) {
                $formatted_array = array(
                    array('enrolment_status' => 'Active', 'no_of_girls' => $result->active_count, 'take_up_status' => 0),
                    array('enrolment_status' => 'Pending', 'no_of_girls' => $result->pending_count, 'take_up_status' => 1),
                    array('enrolment_status' => 'Suspended', 'no_of_girls' => $result->suspended_count, 'take_up_status' => 1),
                    array('enrolment_status' => 'Completed', 'no_of_girls' => $result->completed_count, 'take_up_status' => 1)
                );
            }
            $res = array(
                'success' => true,
                'results1' => $results,
                'results' => $formatted_array,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'results' => array(),
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBeneficiaryAnnualEnrollmentsRpt()
    {
        try {
            $logged_in_user = Auth::user()->id;
            $user_access_point = Auth::user()->access_point_id;

            $qry = DB::table('beneficiary_enrollments as t1')
                ->select(DB::raw('t1.year_of_enrollment,count(DISTINCT t1.beneficiary_id) as no_of_girls'));
            if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
                $qry->join('beneficiary_information_archive as t2', 't2.id', '=', 't1.beneficiary_id')
                    ->whereIn('t2.district_id', function ($query) use ($logged_in_user) {
                        $query->select(DB::raw('t3.district_id'))
                            ->from('user_district as t3')
                            ->whereRaw('t3.user_id=' . $logged_in_user);
                    });
            }
            $qry->where('t1.is_validated', 1)
                ->groupBy('t1.year_of_enrollment');
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

    public function getAnnualBeneficiaryCount($year, $term)
    {
        $logged_in_user = \Auth::user()->id;
        $user_access_point = \Auth::user()->access_point_id;
        $where = array(
            'year_of_enrollment' => $year,
            'term_id' => $term
        );
        $qry = DB::table('beneficiary_enrollments')
            ->where($where);
        if ($user_access_point == 3 || $user_access_point == 4) {//assigned districts
            $qry->whereIn('beneficiary_information_archive.district_id', function ($query) use ($logged_in_user) {
                $query->select(DB::raw('user_district.district_id'))
                    ->from('user_district')
                    ->whereRaw('user_district.user_id=' . $logged_in_user);
            });
        }
        $count = $qry->count();
        return $count;
    }

    public function getPayment_vericationsummaryStr()
    {
        //get the users details and also the districts for filter
        $result = DB::table('payment_verificationbatch as t1')
            ->select(DB::raw("count(t1.id) as no_of_records, t2.name as process_name"))
            ->join('payment_verification_statuses as t2', 't1.status_id', '=', 't2.id')
            ->groupBy('t1.status_id')
            ->orderBy('t2.order_no')
            ->get();
        json_output(array('results' => $result));

    }

    public function getPayment_requestssubsummaryStr()
    {
        //get the users details and also the districts for filter
        $result = DB::table('payment_request_details as t1')
            ->select(DB::raw("count(t1.id) as no_of_records, t2.name as process_name"))
            ->join('payment_verification_statuses as t2', 't1.status_id', '=', 't2.id')
            ->groupBy('t1.status_id')
            ->orderBy('t2.order_no')
            ->get();
        json_output(array('results' => $result));
    }

    public function logDelete()
    {
        try {
            $data = DB::table('beneficiary_information as t1')
                ->select('t1.id', 't1.enrollment_status', 't2.created_at')
                ->join('beneficiary_grade_logs as t2', 't1.id', '=', 't2.girl_id')
                ->where('t1.enrollment_status', '=', 4)
                ->where('t1.kgs_takeup_status', '=', 1)
                ->where('t2.grade', '=', 12)
                ->groupBy('t1.id')
                ->get();
            foreach ($data as $datum) {
                $log_data = array(
                    'girl_id' => $datum->id,
                    'from_stage' => 1,
                    'to_stage' => $datum->enrollment_status,
                    'reason' => 'Completed grade 12--ADMIN late update',
                    'created_at' => $datum->created_at,
                    'created_by' => 4,
                    'manual_update' => 1
                );
                DB::table('beneficiaries_transitional_report')->insert($log_data);
            }
            print_r('Success');
        } catch (\Exception $exception) {
            print_r($exception->getMessage());
        } catch (\Throwable $throwable) {
            print_r($throwable->getMessage());
        }
    }

}
