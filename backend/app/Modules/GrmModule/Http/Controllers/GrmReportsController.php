<?php


namespace App\Modules\GrmModule\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Mail\ComplaintResponseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\IOFactory;
use PDF;

class GrmReportsController extends Controller
{

    protected $user_id;
    protected $user_email;
    protected $dms_id;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user_id = Auth::user()->id;
            $this->user_email = Auth::user()->email;
            $this->dms_id = Auth::user()->dms_id;
            return $next($request);
        });
    }

    /**
     * @param $lodge_date_from
     * @param $lodge_date_to
     * @param $record_date_from
     * @param $record_date_to
     * @param string $program_type_id
     * @param string $province_id
     * @param string $district_id
     * @param string $category_id
     * @param string $access_point
     * @param string $group_by
     * @return \Illuminate\Database\Query\Builder
     */
    public function getCountSubQuery($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $program_type_id = '', $province_id = '', $district_id = '', $category_id = '', $access_point = '', $group_by = '')
    {
        $subQry = DB::table('grm_complaint_details as k1')
            ->select(DB::raw("COUNT(DISTINCT k1.id)"));
        if (isset($lodge_date_from) && isset($lodge_date_to)) {
            $subQry->whereBetween('k1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
        }
        if (isset($record_date_from) && isset($record_date_to)) {
            $subQry->whereBetween('k1.complaint_record_date', [$record_date_from, $record_date_to]);
        }
        if (validateisNumeric($program_type_id)) {
            $subQry->where('k1.programme_type_id', $program_type_id);
        }
        if (validateisNumeric($program_type_id)) {
            $subQry->where('k1.programme_type_id', $program_type_id);
        }
        if (validateisNumeric($province_id)) {
            $subQry->where('k1.province_id', $province_id);
        }
        if (validateisNumeric($district_id)) {
            $subQry->where('k1.district_id', $district_id);
        }
        if (validateisNumeric($category_id)) {
            $subQry->where('k1.category_id', $category_id);
        }
        if (validateisNumeric($access_point)) {
            $subQry->join('users as k2', 'k1.complaint_recorded_by', '=', 'k2.id')
                ->where('k2.access_point_id', $access_point);
        }
        if ($group_by == 2) {
            $subQry->join('grm_gewel_programmes as k3', 'k1.programme_type_id', '=', 'k3.id')
                ->where('k3.is_gewel', '<>', 1);
        }
        return $subQry;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGrmReportsPerProgram(Request $request)
    {
        try {
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $access_point = $request->input('access_point');
            $group_by = $request->input('group_by');

            $sub = $this->getCountSubQuery($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, '', '', '', '', $access_point, $group_by);
            $qry = DB::table('grm_complaint_details as t1')
                ->join('grm_gewel_programmes as t2', 't1.programme_type_id', '=', 't2.id')
                ->select(DB::raw("t1.programme_type_id,COUNT(DISTINCT t1.id) as num_of_complaints, ROUND((COUNT(DISTINCT t1.id)/(" .
                    $sub->toSql()
                    . "))*100,2) AS percentage_count "))
                ->mergeBindings($sub);
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            if (validateisNumeric($access_point)) {
                $qry->join('users as t3', 't1.complaint_recorded_by', '=', 't3.id')
                    ->where('t3.access_point_id', $access_point);
            }
            if ($group_by == 2) {
                $qry->join('grm_nongewel_programmes as grp_by', 't1.nongewel_programme_type_id', '=', 'grp_by.id')
                    ->where('t2.is_gewel', '<>', 1)
                    ->addSelect('grp_by.name as program')
                    ->groupBy('t1.nongewel_programme_type_id');
            } else {
                $qry->addSelect('t2.name as program')
                    ->groupBy('t1.programme_type_id');
            }
            //$qry->groupBy('t1.programme_type_id');
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

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGrmReportsPerCategory(Request $request)
    {
        try {
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $program_type_id = $request->input('programme_type_id');
            $access_point = $request->input('access_point');
            //Maureen  nov2022
            $grievance_status_id=$request->input('grievance_status_id');
            $complaint_category_id=$request->input('complaint_category_id');
            $sub = $this->getCountSubQuery($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $program_type_id, '', '', '', $access_point);
            $qry = DB::table('grm_complaint_details as t1')
                ->leftjoin('grm_complaint_categories as t2', 't1.category_id', '=', 't2.id')
                ->select(DB::raw("t1.category_id,t1.programme_type_id,t2.name as category,COUNT(DISTINCT t1.id) as num_of_complaints, ROUND((COUNT(DISTINCT t1.id)/(" .
                    $sub->toSql()
                    . "))*100,2) AS percentage_count"))
                ->mergeBindings($sub);
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            if (validateisNumeric($program_type_id)) {
                $qry->where('t1.programme_type_id', $program_type_id);
            }
            if (validateisNumeric($access_point)) {
                $qry->join('users as t3', 't1.complaint_recorded_by', '=', 't3.id')
                    ->where('t3.access_point_id', $access_point);
            }
            if (validateisNumeric($complaint_category_id)) {
                $qry->where('t1.category_id', $complaint_category_id);
            }
            if (validateisNumeric($grievance_status_id)) {
                $qry->where('t1.record_status_id', $grievance_status_id);
            }
            $qry->groupBy('t1.category_id');
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

    public function getGrmReportsPerStatus(Request $request)
    {
        try {
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $program_type_id = $request->input('programme_type_id');
            $access_point = $request->input('access_point');

            $sub = $this->getCountSubQuery($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $program_type_id, '', '', '', $access_point);
            $qry = DB::table('grm_complaint_details as t1')
                ->join('grm_grievance_statuses as t2', 't1.record_status_id', '=', 't2.id')
                ->select(DB::raw("t1.record_status_id,t2.name as complaint_status,COUNT(DISTINCT t1.id) as num_of_complaints, ROUND((COUNT(DISTINCT t1.id)/(" .
                    $sub->toSql()
                    . "))*100,2) AS percentage_count"))
                ->mergeBindings($sub);
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            if (validateisNumeric($program_type_id)) {
                $qry->where('t1.programme_type_id', $program_type_id);
            }
            if (validateisNumeric($access_point)) {
                $qry->join('users as t3', 't1.complaint_recorded_by', '=', 't3.id')
                    ->where('t3.access_point_id', $access_point);
            }
            $qry->groupBy('t1.record_status_id');
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

    public function getGrmReportsPerLocation(Request $request)
    {
        try {
            $lodge_date_from = $request->input('lodge_date_from');
            $lodge_date_to = $request->input('lodge_date_to');
            $record_date_from = $request->input('record_date_from');
            $record_date_to = $request->input('record_date_to');
            $program_type_id = $request->input('programme_type_id');
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $category_id = $request->input('category_id');

            $sub = $this->getCountSubQuery($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $program_type_id, $province_id, $district_id, $category_id);
            $qry = DB::table('grm_complaint_details as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('cwac as t3', 't1.cwac_id', '=', 't3.id')
                ->select(DB::raw("t1.cwac_id,t2.name as district,t3.name as cwac,t1.district_id,
                      COUNT(DISTINCT t1.id) as num_of_complaints, ROUND((COUNT(DISTINCT t1.id)/(" .
                    $sub->toSql()
                    . "))*100,2) AS percentage_count"))
                ->mergeBindings($sub);
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            if (validateisNumeric($program_type_id)) {
                $qry->where('t1.programme_type_id', $program_type_id);
            }
            if (validateisNumeric($province_id)) {
                $qry->where('t1.province_id', $province_id);
            }
            if (validateisNumeric($district_id)) {
                $qry->where('t1.district_id', $district_id);
            }
            if (validateisNumeric($category_id)) {
                $qry->where('t1.category_id', $category_id);
            }
            $qry->groupBy('t1.cwac_id');
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

    /**
     * @param $lodge_date_from
     * @param $lodge_date_to
     * @param $record_date_from
     * @param $record_date_to
     * @param $category
     * @return int
     */
    public function getResolvedComplaintsCountPerCategory($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $category)
    {
        $subQry = DB::table('grm_complaint_details as k1')
            ->select(DB::raw("COUNT(DISTINCT k1.id)"))
            ->where('k1.record_status_id', 2)
            ->where('k1.category_id', $category);
        if (isset($lodge_date_from) && isset($lodge_date_to)) {
            $subQry->whereBetween('k1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
        }
        if (isset($record_date_from) && isset($record_date_to)) {
            $subQry->whereBetween('k1.complaint_record_date', [$record_date_from, $record_date_to]);
        }
        return $subQry->count();
    }

    public function getGrmReportsComplaintResolutionAvTime(Request $request)
    {
        $lodge_date_from = $request->input('lodge_date_from');
        $lodge_date_to = $request->input('lodge_date_to');
        $record_date_from = $request->input('record_date_from');
        $record_date_to = $request->input('record_date_to');
        try {
            $qry = DB::table('grm_complaint_details as t1')
                ->join('grm_complaint_categories as t3', 't1.category_id', '=', 't3.id')
                ->select(DB::raw("t1.category_id,t3.name as category, SUM(DATEDIFF(t1.complaint_resolution_date,t1.complaint_record_date)) AS total_time"))
                ->where('t1.record_status_id', 2);
            if (isset($lodge_date_from) && isset($lodge_date_to)) {
                $qry->whereBetween('t1.complaint_lodge_date', [$lodge_date_from, $lodge_date_to]);
            }
            if (isset($record_date_from) && isset($record_date_to)) {
                $qry->whereBetween('t1.complaint_record_date', [$record_date_from, $record_date_to]);
            }
            $qry->groupBy('t1.category_id');
            $results = $qry->get();
            foreach ($results as $key => $result) {
                $average_time = $result->total_time / $this->getResolvedComplaintsCountPerCategory($lodge_date_from, $lodge_date_to, $record_date_from, $record_date_to, $result->category_id);
                $results[$key]->average_time = round($average_time, 0);
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

    public function getGrmReportsComplaintResolutionLimitTime(Request $request)
    {
        try {
            $mainQry = DB::table('grm_complaint_details as t1')
                ->join('grm_complaint_categories as t3', 't1.category_id', '=', 't3.id')
                ->where('t1.record_status_id', 2);

            $maxQry = clone $mainQry;
            $maxQry->select(DB::raw("DATEDIFF(t1.complaint_resolution_date,t1.complaint_record_date) AS time_taken"))
                ->orderBy('time_taken', 'DESC')
                ->limit(1);
            $maxResults = $maxQry->first();

            $minQry = clone $mainQry;
            $minQry->select(DB::raw("DATEDIFF(t1.complaint_resolution_date,t1.complaint_record_date) AS time_taken"))
                ->orderBy('time_taken', 'ASC')
                ->limit(1);
            $minResults = $minQry->first();

            $max_time = '';
            $min_time = '';
            if (!is_null($maxResults)) {
                $max_time = $maxResults->time_taken;
            }
            if (!is_null($minResults)) {
                $min_time = $minResults->time_taken;
            }
            $results = array(
                array('text' => 'Longest Resolution Time', 'value' => $max_time),
                array('text' => 'Shortest Resolution Time', 'value' => $min_time)
            );

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

    public function generateComplaintResponseLetter(Request $request)
    {
        try {
            $complaint_id = $request->input('complaint_id');
            $template_id = $request->input('id');
            $email_notification = $request->input('email_notification');
            $primary_email = $request->input('primary_email');
            $secondary_emails = $request->input('secondary_emails');
            $secondary_emails = array_filter(explode(',', $secondary_emails));
            $email_subject = $request->input('email_subject');
            $email_body = $request->input('email_body');
            $focal_person = $request->input('focal_person');

            $this->saveGrievanceResponseLetterSnapShot($request);
            $complaintDetailsQry = DB::table('grm_complaint_details as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('grm_gewel_programmes as t3', 't1.programme_type_id', '=', 't3.id')
                ->select(DB::raw("t1.*,t2.name as district_name,t3.name as program_name,t3.email_address as program_email,t3.phone_no as program_phone"))
                ->where('t1.id', $complaint_id);
            $complaintDetails = $complaintDetailsQry->first();
            if (!$complaintDetails) {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered getting complaint details'
                );
                dd($res);
            }
            $program_name = $complaintDetails->program_name;
            $letterDetails = getTableData('grm_lettertypes', array('id' => $template_id));
            if (!$letterDetails) {
                die('<p style="color: red">Problem encountered getting letter details!!</p>');
            }
            $template_file_name = $letterDetails->template_saved_name;
            if ($template_file_name == '') {
                die('<p style="color: red">Problem encountered getting letter template!!</p>');
            }
            $templates_path = public_path('storage/Template/');
            $responses_path = public_path('storage/Response/');
            $template_file = $templates_path . $template_file_name;
            $file_name = time();
            $word_output_file = $responses_path . $file_name . '.docx';
            $pdf_output_file = $responses_path . $file_name . '.pdf';

            $phpword = new TemplateProcessor($template_file);

            $fullname = $complaintDetails->complainant_first_name . ' ' . $complaintDetails->complainant_last_name;
            $complaint_details = strip_tags($complaintDetails->complaint_details);

            // $systemResponses = array(
            //     'todaysDate' => date('d/m/Y'),
            //     'complainantName' => $fullname,
            //     'complaintFormNo' => $complaintDetails->complaint_form_no,
            //     'grmFocalPersonsName' => $focal_person,
            //     'dateOfComplaintSubmission' => converter22($complaintDetails->complaint_lodge_date),
            //     'summaryOfComplaint' => $complaint_details,
            //     'districtName' => $complaintDetails->district_name,
            //     'HQMailAddress' => $complaintDetails->program_email,
            //     'phoneNumbers' => $complaintDetails->program_phone
            // );
            // $userResponses = $this->getComplaintLetterUserResponses($complaint_id, $template_id);
            // $search_replace_array = array_merge($systemResponses, $userResponses);
            // $phpword->setValueAdvanced($search_replace_array);

            $phpword->setValue('todaysDate', date('d/m/Y'));//nameOfStaff
            $phpword->setValue('complainantName', $fullname);
            $phpword->setValue('complaintFormNo', $complaintDetails->complaint_form_no);
            $phpword->setValue('grmFocalPersonsName', $focal_person);
            $phpword->setValue('dateOfComplaintSubmission', converter22($complaintDetails->complaint_lodge_date));
            $phpword->setValue('summaryOfComplaint', $complaint_details);
            $phpword->setValue('districtName', $complaintDetails->district_name);
            $phpword->setValue('HQMailAddress', $complaintDetails->program_email);
            $phpword->setValue('phoneNumbers', $complaintDetails->program_phone);

            $userResponses = $this->getComplaintLetterUserResponses($complaint_id, $template_id);
            if($userResponses) {
                $userResponses['explanationOfFindings'] = $userResponses['explanationOfFindings'] ?? '';
                foreach ($userResponses as $i => $item) {
                    $phpword->setValue($i, $item);
                }
            }

            Settings::setPdfRendererPath(base_path('vendor/elibyy/tcpdf-laravel/src'));
            Settings::setPdfRendererName('TCPDF');

            $phpword->saveAs($word_output_file);
            $temp = IOFactory::load($word_output_file);
            $xmlWriter = IOFactory::createWriter($temp, 'PDF');
            $xmlWriter->save($pdf_output_file, TRUE);

            unlink($word_output_file);
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $file_name . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($pdf_output_file));
            flush();
            readfile($pdf_output_file);

            //email
            if ($email_notification == 1 || $email_notification === 1) {
                if (is_connected()) {
                    Mail::to($primary_email)
                        ->cc($secondary_emails)
                        ->send(new ComplaintResponseNotification($email_subject, $email_body, $pdf_output_file, $program_name));
                }
            }
            unlink($pdf_output_file);

        } catch (\Exception $exception) {
            die('<p style="color: red">' . $exception->getMessage() . '</p>');
        } catch (\Throwable $throwable) {
            die('<p style="color: red">' . $throwable->getMessage() . '</p>');
        }
    }

    public function saveGrievanceResponseLetterSnapShot(Request $request)
    {
        $params = array(
            'complaint_id' => $request->input('complaint_id'),
            'template_id' => $request->input('id'),
            'focal_person' => $request->input('focal_person'),
            'email_notification' => $request->input('email_notification'),
            'primary_email' => $request->input('primary_email'),
            'secondary_emails' => $request->input('secondary_emails'),
            'email_subject' => $request->input('email_subject'),
            'email_body' => $request->input('email_body'),
            'print_by' => aes_decrypt(Auth::user()->first_name) . ' ' . aes_decrypt(Auth::user()->last_name),
            'log_date' => Carbon::now(),
            'workflow_stage_id' => $request->input('workflow_stage_id')
        );
        DB::table('grm_responseletters_snapshot')->insert($params);
    }

    public function getComplaintLetterUserResponses($complaint_id, $template_id)
    {
        $mainQry = DB::table('grm_complaintletter_responses as t1')
            ->join('grm_responseletter_applicablesections as t2', function ($join) use ($complaint_id, $template_id) {
                $join->on('t1.applicable_section_id', '=', 't2.id')
                    ->where('t2.template_id', $template_id);
            })
            ->join('grm_responseletter_sections as t3', 't2.section_id', '=', 't3.id')
            ->where('t1.complaint_id', $complaint_id)
            ->orderBy('t2.id');

        $keysQry = clone $mainQry;
        $keys = $keysQry->get();
        $keys = convertStdClassObjToArray($keys);
        $keys = convertAssArrayToSimpleArray($keys, 'identifier');

        $valuesQry = clone $mainQry;
        $values = $valuesQry->get();
        $values = convertStdClassObjToArray($values);
        $values = convertAssArrayToSimpleArray($values, 'response');

        return array_combine($keys, $values);
    }

    public function previewResponseLetterTemplate(Request $request)
    {
        $template_id = $request->input('template_id');
        $templates_path = public_path('storage/Template/');
        try {
            $template_details = getTableData('grm_lettertypes', array('id' => $template_id));
            if (is_null($template_details)) {
                die('<p style="color: red">Problem getting template details!!</p>');
            }
            $template_initial_name = $template_details->template_initial_name;
            $template_saved_name = $template_details->template_saved_name;
            $template_file = $templates_path . $template_saved_name;

            if (file_exists($template_file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $template_initial_name . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($template_file));
                flush();
                readfile($template_file);
            } else {
                die('<p style="color: red">Template not found in the specified path!!</p>');
            }
        } catch (\Exception $exception) {
            die('<p style="color: red">' . $exception->getMessage() . '</p>');
        } catch (\Throwable $throwable) {
            die('<p style="color: red">' . $throwable->getMessage() . '</p>');
        }
    }

    public function getComplaintNumbersLog()
    {
        try {
            $qry = DB::table('grm_complaint_numberslog as t1')
                ->join('users as t2', 't1.created_by', '=', 't2.id')
                ->leftJoin('grm_complaint_details as t3', 't1.complaint_number', '=', 't3.complaint_form_no')
                ->select(DB::raw("t1.*,CONCAT_WS(' ',decrypt(t2.first_name),decrypt(t2.last_name)) as author,t3.id as recorded"))
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

    /**
     * @return string
     */
    public function generateGrmComplaintNumber()
    {
        $seconds = date('s');
        $rand = mt_rand(1, 999999);
        $complaint_no = $seconds . $rand;
        $complaint_no = str_pad($complaint_no, 8, '0', STR_PAD_RIGHT);
        return $complaint_no;
    }

    /**
     * @param Request $request
     */
    public function generateComplaintForms(Request $request)
    {
        $numOfCopies = $request->input('numOfCopies');
        if ($numOfCopies < 1) {
            die('<p style="color: red;text-align: center">Please specify valid number of copies!!</p>');
        }
        if ($numOfCopies > 800) {
            die('<p style="color: red;text-align: center">Maximum number of copies is 800!!</p>');
        }
        $complaintNumbers = array();
        for ($i = 0; $i <= $numOfCopies; $i++) {
            $complaint_no = $this->generateGrmComplaintNumber();
            $complaintNumbers[] = array(
                'complaint_number' => $complaint_no,
                'created_by' => $this->user_id
            );
            PDF::SetTitle('Complaint Forms');
            PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
            PDF::setMargins(10, 18, 10, true);
            PDF::AddPage('P');
            PDF::SetFont('helvetica', '', 10);
            //headers
            $complaintNoString = '<p style="font-weight: bold">Complaint No: ' . $complaint_no . '</p>';
            PDF::writeHTMLCell(0, 10, 10, 3, $complaintNoString, 0, 1, 0, true, 'R', true);
            $image_path = getcwd() . '\resources\images\kgs-logo.png';
            PDF::Image($image_path, 0, 0, 30, 20, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
            PDF::writeHTMLCell(0, 10, 10, 22, '<h3>GEWEL COMPLAINTS FORM</h3>', 0, 1, 0, true, 'C', true);
            PDF::writeHTMLCell(0, 0, 5, 27, '<hr/>', 0, 0, 0, true, 'C', true);

            $p1 = '<p>Please complete this form to report a problem or file a complaint with the GEWEL Programme. 
                      After you fill the form, tear off and keep the receipt at the bottom and put the form in the complaints box.</p>';
            PDF::writeHTMLCell(0, 0, 5, 32, $p1, 0, 0, 0, true, 'L', true);

            $h1 = '<h3 style="font-weight: bold">Programme</h3>';
            PDF::writeHTMLCell(0, 0, 5, 45, $h1, 0, 0, 0, true, 'L', true);

            $space_bar7 = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            $p2 = '<p>1.    What programme are you complaining about? Please tick the correct box.</p>';
            $p2 .= '<p>' . $space_bar7 . 'Supporting Women’s Livelihoods (SWL or GEWEL provides training and grants to women)&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;&nbsp;]</p>';
            $p2 .= '<p>' . $space_bar7 . 'Keeping Girls in School (KGS provides school fees for girls from Social Cash Transfer households)&nbsp;[&nbsp;&nbsp;&nbsp;]</p>';
            $p2 .= '<p>' . $space_bar7 . 'Others __________________________________________________________________________&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;&nbsp;]</p>';
            PDF::writeHTMLCell(0, 0, 5, 53, $p2, 0, 0, 0, true, 'L', true);

            $h2 = '<h3 style="font-weight: bold">Details of Complaint</h3>';
            PDF::writeHTMLCell(0, 0, 5, 89, $h2, 0, 0, 0, true, 'L', true);

            $p3 = '<p>2. Today’s date:   Day _____ Month __________________ Year _______ 3. District: __________________________ </p>';
            $p3 .= '<p>4. CWAC: ________________________________    5. School (KGS only) ________________________________</p>';
            $p3 .= '<p>6. Gender of person complaining (M/F): ________  7. Age of person complaining: ______</p>';
            PDF::writeHTMLCell(0, 0, 5, 97, $p3, 0, 0, 0, true, 'L', true);

            $p4 = '<p>Please tell us about your complaint so the program can investigate.  Please include as much information as possible. 
                      <span style="font-style: italic">If you are complaining about a wrong payment amount, please write how much you were actually paid, how much you think you should have been paid, 
                      and the name of your payment provider</span>.</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            $p4 .= '<p>_______________________________________________________________________________________________</p>';
            PDF::writeHTMLCell(0, 0, 5, 125, $p4, 0, 0, 0, true, 'L', true);

            $h3 = '<h3 style="font-weight: bold">Personal Details (Optional)</h3>';
            PDF::writeHTMLCell(0, 0, 5, 198, $h3, 0, 0, 0, true, 'L', true);

            $p5 = '<p>If you would like to receive a response from the program about your complaint, please fill in your details below. 
                       If you do not fill in these details, you will remain unknown and the program will not be able to contact you.</p>';
            $p5 .= '<p>10. First Name: ____________________________ 11. Last Name: ________________________________</p>';
            $p5 .= '<p>12.  Village: _______________________________    13. Mobile number: ________________________</p>';
            $p5 .= '<p>14. NRC number of SWL beneficiary or SCTS (KGS) Household Head:  _________________________</p>';
            PDF::writeHTMLCell(0, 0, 5, 206, $p5, 0, 0, 0, true, 'L', true);

            PDF::writeHTMLCell(0, 0, 5, 250, '<hr/>', 0, 0, 0, true, 'L', true);

            $h31 = '<h3 style="font-weight: bold">Receipt</h3>';
            $h32 = '<h3 style="font-weight: bold">Complaint Number: ' . $complaint_no . '</span></h3>';
            PDF::writeHTMLCell(0, 0, 5, 255, $h31, 0, 0, 0, true, 'L', true);
            PDF::writeHTMLCell(0, 0, 5, 255, $h32, 0, 0, 0, true, 'R', true);

            $p6 = '<p>Please tear off and keep this part of the form so you know your complaint number. 
                      You can use this number to ask District/program staff about the status of your complaint.</p>';
            PDF::writeHTMLCell(0, 0, 5, 263, $p6, 0, 0, 0, true, 'L', true);
        }
        DB::table('grm_complaint_numberslog')->insert($complaintNumbers);
        PDF::Output('complaint_form_' . time() . '.pdf', 'I');
    }

    public function printGrmMonitoringForm(Request $request)
    {
        $monitoring_id = $request->input('record_id');
        $params = array(
            'monitor_id' => $monitoring_id
        );
        $report = generateJasperReport('KgsMonitorForm', 'monitoring_form_' . time(), 'pdf', $params);
        return $report;
    }

    public function printComplaintDetails(Request $request)
    {
        $complaint_id = $request->input('complaint_id');

        //complaint details
        $qry = DB::table('grm_complaint_details as t1')
            ->join('grm_gewel_programmes as t2', 't1.programme_type_id', '=', 't2.id')
            ->join('districts as t3', 't1.district_id', '=', 't3.id')
            ->join('cwac as t4', 't1.cwac_id', '=', 't4.id')
            ->leftJoin('school_information as t5', 't1.school_id', '=', 't5.id')
            ->leftJoin('gender as t6', 't1.complainant_gender_id', '=', 't6.id')
            ->leftJoin('grm_complaint_categories as t7', 't1.category_id', '=', 't7.id')
            ->join('users as t8', 't1.created_by', '=', 't8.id')
            ->join('grm_complaint_subcategories as t9', 't1.sub_category_id', '=', 't9.id')
            ->leftJoin('grm_grievance_processingoptions as t10', 't1.processing_option_id', '=', 't10.id')
            ->select(DB::raw("t1.*, t2.name as programme_type,t3.name as district_name,t4.name as cwac_name,t5.name as school_name,
                      t6.name as gender,t7.name as category,CONCAT_WS(' ',decrypt(t8.first_name),decrypt(t8.last_name)) as recorder_by,
                      t9.name as sub_category,t10.name as processing_option"))
            ->where('t1.id', $complaint_id);
        $complaint_details = $qry->first();
        if (is_null($complaint_details)) {
            dd('Problem getting complaint details!!');
        }
        //complaint investigation details
        $qry3 = DB::table('grm_grievance_investigationdetails as t1')
            ->where('t1.complaint_id', $complaint_details->id);
        $action_items = $qry3->get();
        //Response Letter Print Logs
        $qry4 = DB::table('grm_responseletters_snapshot as t1')
            ->join('grm_lettertypes as t2', 't1.template_id', '=', 't2.id')
            ->select(DB::raw("t1.*,t2.name as template_name"))
            ->where('t1.complaint_id', $complaint_details->id);
        $letterPrintLogs = $qry4->get();

        $complaint_no = $complaint_details->complaint_form_no;
        $lodge_date = strtotime($complaint_details->complaint_lodge_date);

        PDF::SetTitle($complaint_no);
        PDF::SetAutoPageBreak(TRUE, 2);//true sets it to on and 0 means margin is zero from sides
        PDF::setMargins(10, 18, 10, true);
        PDF::AddPage('P');
        PDF::SetFont('helvetica', '', 10);
        //headers
        //PDF::writeHTMLCell(0, 10, 10, 3, $complaintRefString, 0, 1, 0, true, 'L', true);
        $complaintNoString = '<p style="font-weight: bold">Complaint No: ' . $complaint_no . '</p>';
        PDF::writeHTMLCell(0, 10, 10, 3, $complaintNoString, 0, 1, 0, true, 'R', true);
        $image_path = getcwd() . '\resources\images\kgs-logo.png';
        PDF::Image($image_path, 0, 0, 30, 20, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::writeHTMLCell(0, 10, 10, 22, '<h4>COMPLAINT DETAILS</h4>', 0, 1, 0, true, 'C', true);
        PDF::writeHTMLCell(0, 0, 5, 27, '<hr/>', 0, 0, 0, true, 'C', true);
        PDF::ln(3);

        $h1 = '<h3 style="font-weight: bold">Program</h3>';
        //PDF::writeHTMLCell(0, 0, 5, 30, $h1, 0, 0, 0, true, 'L', true);
        PDF::writeHTML($h1, true, 0, true, true);
        PDF::ln(2);
        $p2 = '<p>1. What programme are you complaining about? Please tick the correct box.';
        $p2 .= '<br>[<u>' . $complaint_details->programme_type . '</u>]</p>';
        //PDF::writeHTMLCell(0, 0, 5, 38, $p2, 0, 0, 0, true, 'L', true);
        PDF::writeHTML($p2, true, 0, true, true);
        PDF::ln(5);

        $h2 = '<h3 style="font-weight: bold">Details of Complaint</h3>';
        //PDF::writeHTMLCell(0, 0, 5, 89, $h2, 0, 0, 0, true, 'L', true);
        PDF::writeHTML($h2, true, 0, true, true);
        PDF::ln(2);
        $p3 = '<p>2. Today’s date:   Day [<u>' . date('d', $lodge_date) . '</u>] Month [<u>' . date('m', $lodge_date) . '</u>] Year [<u>' . date('Y', $lodge_date) . '</u>] 3. District: [<u>' . $complaint_details->district_name . '</u>] </p>';
        $p3 .= '<p>4. CWAC: [<u>' . $complaint_details->cwac_name . '</u>]  5. School (KGS only) [<u>' . $complaint_details->school_name . '</u>] </p>';
        $p3 .= '<p>6. Gender of person complaining (M/F): [<u>' . $complaint_details->gender . '</u>]   7. Age of person complaining: [<u>' . $complaint_details->complainant_age . '</u>] </p>';
        PDF::writeHTML($p3, true, 0, true, true);
        PDF::ln(3);
        $p4 = '<p>Please tell us about your complaint so the program can investigate.  Please include as much information as possible. 
                      <span style="font-style: italic">If you are complaining about a wrong payment amount, please write how much you were actually paid, how much you think you should have been paid, 
                      and the name of your payment provider</span>.</p>';
        $p4 .= '<p>[<u>' . $complaint_details->complaint_details . '</u>]</p>';
        PDF::writeHTML($p4, true, 0, true, true);
        PDF::ln(5);

        $h3 = '<h3 style="font-weight: bold">Personal Details (Optional)</h3>';
        PDF::writeHTML($h3, true, 0, true, true);
        PDF::ln(2);
        $p5 = '<p>If you would like to receive a response from the program about your complaint, please fill in your details below. 
                       If you do not fill in these details, you will remain unknown and the program will not be able to contact you.</p>';
        $p5 .= '<p>10. First Name: [<u>' . $complaint_details->complainant_first_name . '</u>]  11. Last Name: [<u>' . $complaint_details->complainant_last_name . '</u>]</p>';
        $p5 .= '<p>12.  Village: [<u>' . $complaint_details->complainant_village . '</u>]   13. Mobile number: [<u>' . $complaint_details->complainant_mobile . '</u>]</p>';
        $p5 .= '<p>14. NRC number of SWL beneficiary or SCTS (KGS) Household Head:  [<u>' . $complaint_details->complainant_nrc . '</u>]</p>';
        PDF::writeHTML($p5, true, 0, true, true);
        PDF::ln(5);

        //extra details
        PDF::writeHTML('<hr>', true, 0, true, true);
        $extra_details = '<table border="1" cellpadding="3">
                            <thead>
                            <tr>
                            <th>Lodge Date</th>
                            <th>Collection Date</th>
                            <th>Collected By</th>
                            <th>Recording Date</th>
                            <th>Recorded By</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                            <td>' . converter22($complaint_details->complaint_lodge_date) . '</td>
                            <td>' . converter22($complaint_details->complaint_collection_date) . '</td>
                            <td>' . $complaint_details->complaint_collector . '</td>
                            <td>' . converter22($complaint_details->complaint_record_date) . '</td>
                            <td>' . $complaint_details->recorder_by . '</td>
                            </tr>
                            </tbody>
                        </table>';
        PDF::writeHTML($extra_details, true, 0, true, true);
        PDF::ln(4);
        PDF::writeHTML('<hr>', true, 0, true, true);

        //PAGE 2
        PDF::AddPage('L');
        $h4 = '<h3 style="font-weight: bold">Complaint Categorization</h3>';
        PDF::writeHTML($h4, true, 0, true, true);
        PDF::ln(2);
        $p6 = '<table border="1" cellpadding="3">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Sub Category</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . $complaint_details->category . '</td>
                        <td>' . $complaint_details->sub_category . '</td>
                    </tr>
                </tbody>';
        $p6 .= '</table>';
        PDF::writeHTML($p6, true, 0, true, true);
        PDF::ln(5);

        $h5 = '<h3 style="font-weight: bold">Grievance Processing</h3>';
        PDF::writeHTML($h5, true, 0, true, true);
        PDF::ln(2);

        $p7 = '<table border="1" cellpadding="3">
                <thead>
                    <tr>
                        <th>Determination</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . $complaint_details->processing_option . '</td>
                        <td>' . $complaint_details->processing_remarks . '</td>
                    </tr>
                </tbody>';
        $p7 .= '</table>';
        PDF::writeHTML($p7, true, 0, true, true);
        PDF::ln(5);

        $h6 = '<h3 style="font-weight: bold">Grievance Investigation</h3>';
        PDF::writeHTML($h6, true, 0, true, true);
        PDF::ln(2);

        $p8 = '<table border="1" cellpadding="3">
                <thead>
                    <tr>
                        <th>Item To Investigate</th>
                        <th>Action Taken</th>
                        <th>Findings</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($action_items as $action_item) {
            $p8 .= '<tr>
                        <td>' . $action_item->item . '</td>
                        <td>' . $action_item->action . '</td>
                        <td>' . $action_item->findings . '</td>
                     </tr>';
        }
        $p8 .= '</tbody>
                </table>';
        PDF::writeHTML($p8, true, 0, true, true);
        PDF::ln(5);

        $h7 = '<h3 style="font-weight: bold">Response Letter Print Log</h3>';
        PDF::writeHTML($h7, true, 0, true, true);
        PDF::ln(2);

        $p8 = '<table border="1" cellpadding="3">
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Focal Person</th>
                        <th>email_notification</th>
                        <th>Primary Email</th>
                        <th>Secondary Email</th>
                        <th>Email Subject</th>
                        <th>Email Body</th>
                        <th>Print By</th>
                        <th>Print Date</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($letterPrintLogs as $letterPrintLog) {
            $email_notification = 'No';
            if ($letterPrintLog->email_notification == 1) {
                $email_notification = 'Yes';
            }
            $p8 .= '<tr>
                        <td>' . $letterPrintLog->template_name . '</td>
                        <td>' . $letterPrintLog->focal_person . '</td>
                        <td>' . $email_notification . '</td>
                        <td>' . $letterPrintLog->primary_email . '</td>
                        <td>' . $letterPrintLog->secondary_emails . '</td>
                        <td>' . $letterPrintLog->email_subject . '</td>
                        <td>' . $letterPrintLog->email_body . '</td>
                        <td>' . $letterPrintLog->print_by . '</td>
                        <td>' . $letterPrintLog->log_date . '</td>
                     </tr>';
        }
        $p8 .= '</tbody>
                </table>';
        PDF::writeHTML($p8, true, 0, true, true);
        PDF::ln(5);

        PDF::Output('complaint_details_' . $complaint_no . '.pdf', 'I');
    }

}
