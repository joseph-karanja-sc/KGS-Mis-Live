<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 7/26/2017
 * Time: 1:46 PM
 */

namespace App\Helpers;

use App\Exports\GenericExporter;
use App\SerialTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PHPJasper as JasperPHP;
use PDF;
use Illuminate\Support\Facades\DB;
use App\ProcessSerialTracker;
use Carbon\Carbon;
use \DateTime;

class UtilityHelper
{
    static function defaultreport_header($title)
    {
        PDF::SetFont('times', '', 8);
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 87, 8, 45, 35);
        PDF::cell(0, 35, '', 0, 1);
        PDF::SetFont('times', 'B', 13);
        PDF::cell(0, 8, strtoupper('Republic of Zambia'), 0, 1, 'C');
        PDF::cell(0, 8, strtoupper('Ministry of Education'), 0, 1, 'C');
        PDF::SetFont('times', 'B', 10);
        PDF::cell(0, 8, 'P.O. Box 50093 Lusaka Zambia,89 Corner of Chimanga and Mogadishu Road.', 0, 1, 'C');

        PDF::SetFont('times', 'B', 12);
        PDF::cell(0, 8, $title, 0, 1, 'C');

    }

    static function defaultreport_headerLandscape($title)
    {
        PDF::SetFont('times', '', 8);
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 130, 8, 45, 35);
        PDF::cell(0, 35, '', 0, 1);
        PDF::SetFont('times', 'B', 13);
        PDF::cell(0, 8, strtoupper('Republic of Zambia'), 0, 1, 'C');
        PDF::cell(0, 8, strtoupper('Ministry of Education'), 0, 1, 'C');
        PDF::SetFont('times', 'B', 10);
        PDF::cell(0, 8, 'P.O. Box 50093 Lusaka Zambia,89 Corner of Chimanga and Mogadishu Road.', 0, 1, 'C');

        PDF::SetFont('times', 'B', 12);
        PDF::cell(0, 8, $title, 0, 1, 'C');

    }

    static function landscapereport_header($form_id)
    {
        PDF::SetFont('times', '', 8);
        //PDF::ln(1);
        $image_path = '\resources\images\kgs-logo.png';
        if (isset($form_id) && $form_id != '') {
            PDF::cell(0, 2, 'FORM ID: ' . $form_id, 0, 1, 'R');
        }
        PDF::SetY(5);
        //the header details
        PDF::SetFont('times', 'B', 14);
        PDF::cell(120, 7, 'Ministry of Education', 0, 1, 'C');
        PDF::cell(120, 7, 'Keeping Girls in School Programme', 0, 1, 'C');
        PDF::SetFont('times', 'B', 10);
        PDF::cell(120, 7, 'P.O. Box 50093 Lusaka Zambia', 0, 1, 'C');
        PDF::cell(120, 7, '89 Corner of Chimanga and Mogadishu Road.', 0, 1, 'C');
        PDF::SetFont('times', '', 8);
        PDF::Image(getcwd() . $image_path, 195, 5, 35, 25);

    }


    static function landscapereport_header2($form_id)
    {
        PDF::SetFont('times', '', 8);
        //PDF::ln(1);
        $image_path = '\resources\images\kgs-logo.png';
        if (isset($form_id) && $form_id != '') {
            PDF::cell(0, 2, 'FORM ID: ' . $form_id, 0, 1, 'R');
        }
        PDF::SetY(5);
        //the header details
        PDF::SetFont('times', 'B', 14);
        PDF::cell(120, 7, 'Ministry of Education', 0, 1, 'C');
        PDF::cell(120, 7, 'Keeping Girls in School Programme', 0, 1, 'C');
        PDF::SetFont('times', 'B', 10);
        PDF::cell(120, 7, 'P.O. Box 50093 Lusaka Zambia', 0, 1, 'C');
        PDF::cell(120, 7, '89 Corner of Chimanga and Mogadishu Road.', 0, 1, 'C');
        PDF::SetFont('times', '', 8);
        PDF::Image(getcwd() . $image_path, 195, 5, 35, 25);

    }

    static function getTimeDiffHrs($time1, $time2)
    {
        $t1 = StrToTime($time1);
        $t2 = StrToTime($time2);
        $diff = $t1 - $t2;
        $hours = $diff / (60 * 60);
        return $hours;
    }

    static function is_connected()
    {
        $connected = @fsockopen("www.google.com", 80);
        //website, port  (try 80 or 443)
        if ($connected) {
            $is_conn = true; //action when connected
            // fclose($connected);
        } else {
            $is_conn = false; //action in connection failure
        }
        return $is_conn;

    }

    static function validateExcelUpload($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext == 'xls' || $ext == 'xlsx' || $ext == 'csv') {
            return true;
        }
        return false;
    }

    static function formatMoney($money)
    {
        if ($money == '' || $money == 0) {
            $money = '00';
        }
        return is_numeric($money) ? number_format((round($money, 2)), 2, '.', ',') : round($money, 2);
    }

    static function converter1($date)
    {
        $date = str_replace('/', '-', $date);
        $dateConverted = date('Y-m-d H:i:s', strtotime($date));
        return $dateConverted;
    }

    static function converter2($date)
    {
        $date = date_create($date);
        $dateConverted = date_format($date, "d/m/Y H:i:s");
        return $dateConverted;
    }

    static function converter11($date)
    {
        $date = str_replace('/', '-', $date);
        $dateConverted = date('Y-m-d', strtotime($date));
        return $dateConverted;
    }

    static function converter22($date)
    {
        $date = date_create($date);
        $dateConverted = date_format($date, "d/m/Y");
        return $dateConverted;
    }

    static function dateConverter($date, $format)
    {
        $date = date_create($date);
        $dateConverted = date_format($date, $format);
        return $dateConverted;
    }

    static function generateBatchNumber($year, $user_id)
    {
        $batch_number = '';
        try {
            $process_type_id = getProcessTypeID('batch number');
            if (is_null($process_type_id) || $process_type_id == '') {
                $process_type_id = 1;
            }
            $where = array(
                'year' => $year,
                'process_type' => $process_type_id
            );
            $serial_num_tracker = new SerialTracker();
            $serial_track = $serial_num_tracker->where($where)->first();
            if ($serial_track == '' || is_null($serial_track)) {
                $current_serial_id = 1;
                $serial_num_tracker->year = $year;
                $serial_num_tracker->process_type = $process_type_id;
                $serial_num_tracker->created_by = $user_id;
                $serial_num_tracker->last_serial_no = $current_serial_id;
                $serial_num_tracker->save();
            } else {
                $last_serial_id = $serial_track->last_serial_no;
                $current_serial_id = $last_serial_id + 1;
                $update_data = array(
                    'last_serial_no' => $current_serial_id
                );
                $serial_num_tracker->where($where)->update($update_data);
            }
            $batch_number = 'KGS-IMP-' . substr($year, -2) . '-' . str_pad($current_serial_id, 4, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $batch_number;
    }

    static function generateReconciliationOversightBatchNumber($year, $term)
    {
        $batch_number = '';
        try {
            $last_id = DB::table('reconciliation_oversight_batches')
                ->max('id');
            $current_serial_id = $last_id + 1;
            $batch_number = 'KGS/RCL/' . $year . '/TERM' . $term . '/' . str_pad($current_serial_id, 4, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $batch_number;
    }

    static function generatePaymentverificationBatchNo($is_reconciliation)
    {
        $year = date('Y');
        $serial_no = getReferenceserials('payverification_serial_no', $year);
        $serial_no = sprintf("%04d", $serial_no);
        $batch_no = "KGS/PAY/VER/" . $year . "/" . $serial_no;
        if (isset($is_reconciliation) && ($is_reconciliation == 1 || $is_reconciliation == true)) {
            $batch_no = "KGS/PAY/RECON/" . $year . "/" . $serial_no;
        }
        return $batch_no;
    }

    static function generateCaserefNo($code)
    {
        $year = date('Y');
        $serial_no = getReferenceserials('caseref_serial_no', $year);
        $serial_no = sprintf("%04d", $serial_no);
        return "KGS/" . $code . '/' . $year . "/" . $serial_no;
    }

    //hiram
    static function generatePaymentRequestRefNo($year)
    {
        //$year = date('Y');
        $serial_no = getReferenceserials('payrequest_serial_nos', $year);
        $serial_no = sprintf("%04d", $serial_no);
        return "KGS/PAY/REQ/" . $year . "/" . $serial_no;
    }

    static function generateReceiptFileNo()
    {
        $year = date('Y');
        $serial_no = getReferenceserials('receipting_fileserial_no', $year);
        $serial_no = sprintf("%04d", $serial_no);
        return "KGS/SCH/RECEIPT/" . $year . "/" . $serial_no;
    }

    static function getReferenceserials($table_name, $year)
    {
        $where_data = array(
            'year' => $year
        );
        $serial_data = DB::table($table_name)
            ->where($where_data)
            ->orderBy('id', 'DESC')
            ->value('serial_no');
        $serial_no = 1;
        if (validateisNumeric($serial_data)) {
            $serial_no = $serial_data + 1;
            $current_data = array(
                'serial_no' => $serial_no,
                'updated_at' => date('Y-m-d H:i:s')
            );
            DB::table($table_name)->where($where_data)->update($current_data);
        } else {
            $current_data = array(
                'serial_no' => $serial_no,
                'year' => $year,
                'created_at' => date('Y-m-d H:i:s')
            );
            DB::table($table_name)->insert($current_data);
        }
        return $serial_no;
    }

    //hiram code
    static function generateJasperrpt($input_file, $output_filename, $file_type, $params)
    {
        //$input = public_path() . '\reports_templates\/' . $input_file;
        $input = getcwd() . '\jasper_reports\/' . $input_file;
        $output = public_path() . '\reports_templates\/' . $output_filename . '.pdf';
        $options = [
            'format' => [$file_type],
            'locale' => 'en',
            'params' => $params,
            'db_connection' => array(
                'driver' => 'mysql',
                'username' => Config('database.mysql.database'),
                'password' => Config('database.mysql.password'),
                'host' => Config('database.mysql.username'),
                'database' => Config('database.mysql.database'),
                'port' => Config('database.mysql.port')
            )
        ];
        $jasper = new JasperPHP;
        //array('pdf'),
        $jasper->process(
            $input,
            $output,
            $options
        )->execute();

        if ($file_type == 'pdf') {
            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=" . $output_filename . ".pdf");
            @readfile($output . '.pdf');
            exit();
        } else if ($file_type == 'xls') {
            header('Content-Type: application/vnd.ms-excel');
            header("Content-Disposition: attachment;filename=" . $output_filename . ".xls");
            header('Cache-Control: max-age=0');
            ob_clean();
            flush();
            readfile($output . '.xls');
            exit();
            // If you're serving to IE 9, then the
        } else if ($file_type == 'csv') {
            header('Content-Type: application/vnd.ms-excel');
            header("Content-Disposition: attachment;filename=" . $output_filename . ".csv");
            header('Cache-Control: max-age=0');
            ob_clean();
            flush();
            readfile($output . '.xls');
            exit();
            // If you're serving to IE 9, then the
        } else {
            header('Content-Description: File Transfer');
            header('Content-Type: application/msword');
            header("Content-Disposition: attachment; filename=" . $output_filename . ".docx");
            ob_clean();
            flush();
            readfile($output . '.docx');
            exit();
        }
    }

    //handler the encoding issue reponce

    static function json_output($data = array(), $content_type = 'json')
    {

        if ($content_type == 'html') {
            header('Content-Type: text/html; charset=utf-8');
        } else {
            header('Content-type: text/plain');
        }

        $data = utf8ize($data);
        echo json_encode($data);

    }

    static function utf8ize($d)
    {
        if (is_array($d))
            foreach ($d as $k => $v)
                $d[$k] = utf8ize($v);

        else if (is_object($d))
            foreach ($d as $k => $v)
                $d->$k = utf8ize($v);

        else
            return utf8_encode($d);

        return $d;
    }

    static function formatDate($date)
    {
        if ($date == '0000-00-00 00:00:00' || $date == '0000-00-00' || strstr($date, '1970-00') != false || strstr($date, '1970') != false) {
            return '';
        } else {
            return ($date == '' or $date == null) ? '0000-00-00' : date('Y-m-d', strtotime($date));
        }
    }

    static function formatDaterpt($date)
    {
        if ($date == '0000-00-00 00:00:00' || $date == '0000-00-00' || strstr($date, '1970-00') != false || strstr($date, '1970') != false) {
            return '';
        } else {
            return ($date == '' or $date == null) ? '' : date('d-m-Y', strtotime($date));
        }
    }

    static function getEnquiryfilter($value, $key)
    {
        $data = array();
        if (validateisNumeric($value)) {
            $data[$key] = $value;
        }
        return $data;

    }

    static function returnUniqueArray($arr, $key)
    {
        $uniquekeys = array();
        $output = array();
        foreach ($arr as $item) {
            if (!in_array($item[$key], $uniquekeys)) {
                $uniquekeys[] = $item[$key];
                $output[] = $item;
            }
        }
        return $output;
    }

    static function getExportReportHeader($colspan, $title)
    {
        $ministry = Config('constants.sys.ministry_name');
        $program_name = Config('constants.sys.program_name');
        $postal_address = Config('constants.sys.postal_address');

        $str = "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $colspan . ">" . $ministry . "</td></tr>";
        $str .= "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $colspan . ">" . $program_name . "</td></tr>";
        $str .= "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $colspan . ">" . 'P.O. Box ' . $postal_address . "</td></tr>";
        $str .= "<tr align='center' style='font-weight: bold; font-type: 'Bookman Old Style'; font-size:14;'><td colspan = " . $colspan . ">" . $title . "</td></tr>";

        return $str;
    }

    //the payment reconciliation module
    static function getSchBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $school_id, $payment_request_id)
    {
        $where_statement = array(
            'year_of_enrollment' => $year_of_enrollment,
            'term_id' => $term_id,
            'school_id' => $school_id,
            'is_validated' => 1
        );
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("count(t1.id) as beneficiary_counter, COALESCE(sum(t1.school_fees),0) as total_fees"));
        if ($payment_request_id != '') {
            $qry->join('beneficiary_payment_records as t2', 't1.id', '=', 't2.enrollment_id')
                ->where('t2.payment_request_id', $payment_request_id);
        }
        $qry->where($where_statement);
        $data = $qry->first();
        return $data;
    }

    static function getBenefiaryenrollmentcounter($year_of_enrollment, $term_id, $payment_request_id, $is_validated)
    {
        $where_statement = array(
            'year_of_enrollment' => $year_of_enrollment,
            'term_id' => $term_id
        );
        $qry = DB::table('beneficiary_enrollments as t1')
            ->select(DB::raw("count(t1.id) as beneficiary_counter, COALESCE(sum(t1.school_fees),0) as total_fees"));
        if ($is_validated == 1) {
            $where_statement['is_validated'] = 1;
        }
        if ($payment_request_id != '') {
            $qry->join('beneficiary_payment_records as t2', 't1.id', 't2.enrollment_id')
                ->where('t2.payment_request_id', $payment_request_id);
        }
        $qry = $qry->where($where_statement);
        $data = $qry->first();
        return $data;
    }

    static function getBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $payment_request_id)
    {
        $where_statement = array(
            't2.payment_year' => $year_of_enrollment,
            't2.term_id' => $term_id
        );
        $qry = DB::table('payment_request_details as t2')
            ->join('beneficiary_payment_records as t3', 't3.payment_request_id', '=', 't2.id')
            ->join('beneficiary_enrollments as t4', 't3.enrollment_id', '=', 't4.id')
            ->select(DB::raw("count(t3.id) as beneficiary_counter, COALESCE(sum(t4.school_fees),0) as waiting_payments_total_fees"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t3.payment_request_id', $payment_request_id);
        }
        $data = $qry->first();
        return $data;
    }

    static function getPaymentdisbursements($year_of_enrollment, $term_id, $payment_request_id)
    {
        $where_statement = array(
            't2.payment_year' => $year_of_enrollment,
            't2.term_id' => $term_id
        );
        $qry = DB::table('payment_request_details as t2')
            ->join('payment_disbursement_details as t1', 't1.payment_request_id', '=', 't2.id')
            ->select(DB::raw("(select count(q.id) from beneficiary_payment_records  q  where q.payment_request_id = t2.id and t2.payment_year=$year_of_enrollment and t2.term_id=$term_id) as beneficiary_counter,
                               COALESCE(sum(decrypt(amount_transfered)),0) as total_fees"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t2.id', $payment_request_id);
        }
        $data = $qry->first();
        return $data;
    }

    static function getBeneficiaryReceiptCounter($year_of_enrollment, $term_id, $payment_request_id)
    {
        $where_statement = array(
            't1.payment_year' => $year_of_enrollment,
            't1.term_id' => $term_id,
            't1.status_id' => 10
        );
        $qry = DB::table('payment_receiptingbatch as t1')
            ->join('payments_receipting_details as t2', 't1.id', '=', 't2.payment_receipts_id')
            ->join('beneficiary_receipting_details as t3', 't2.id', '=', 't3.payment_receipt_id')
            ->select(DB::raw("COUNT(DISTINCT(t2.id)) as beneficiary_counter, COALESCE(sum(t3.receipt_amount),0) as total_fees"));
        if ($payment_request_id != '') {
            $qry->join('beneficiary_enrollments as t4', 't2.enrollment_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t5', 't4.id', '=', 't5.enrollment_id')
                ->where('t5.payment_request_id', $payment_request_id);
        }
        $qry = $qry->where($where_statement);
        $data = $qry->first();
        return $data;
    }

    static function getSchoolBeneficiaryReceiptCounter($year_of_enrollment, $term_id, $school_id, $payment_request_id)
    {
        $where_statement = array(
            't1.payment_year' => $year_of_enrollment,
            't1.term_id' => $term_id,
            't1.school_id' => $school_id,
            't1.status_id' => 10
        );
        $qry = DB::table('payment_receiptingbatch as t1')
            ->join('payments_receipting_details as t2', 't1.id', '=', 't2.payment_receipts_id')
            ->join('beneficiary_receipting_details as t3', 't2.id', '=', 't3.payment_receipt_id')
            ->select(DB::raw("COUNT(DISTINCT(t2.id)) as beneficiary_counter, COALESCE(sum(t3.receipt_amount),0) as total_fees"));
        if ($payment_request_id != '') {
            $qry->join('beneficiary_enrollments as t4', 't2.enrollment_id', '=', 't4.id')
                ->join('beneficiary_payment_records as t5', 't5.enrollment_id', '=', 't4.id')
                ->where('t5.payment_request_id', $payment_request_id);
        }
        $qry = $qry->where($where_statement);
        $data = $qry->first();
        return $data;
    }

    static function getSchoolPaymentdisbursements($year_of_enrollment, $term_id, $school_id, $payment_request_id)
    {
        $where_statement = array(
            't2.payment_year' => $year_of_enrollment,
            't2.term_id' => $term_id,
            't1.school_id' => $school_id
        );
        $qry = DB::table('payment_disbursement_details as t1')
            ->join('payment_request_details as t2', 't1.payment_request_id', '=', 't2.id')
            ->select(DB::raw("(select count(q.id) from beneficiary_payment_records q inner join beneficiary_enrollments t on q.enrollment_id = t.id where q.payment_request_id = t1.payment_request_id and t.school_id = $school_id) as beneficiary_counter, COALESCE(sum(decrypt(amount_transfered)),0) as total_fees"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t2.id', $payment_request_id);
        }
        $data = $qry->first();
        return $data;
    }

    static function getSchBeneficiaryPaymentRecords($year_of_enrollment, $term_id, $school_id, $payment_request_id)
    {
        $where_statement = array(
            't2.payment_year' => $year_of_enrollment,
            't2.term_id' => $term_id,
            't4.school_id' => $school_id
        );
        $qry = DB::table('payment_request_details as t2')
            ->join('beneficiary_payment_records as t3', 't3.payment_request_id', '=', 't2.id')
            ->join('beneficiary_enrollments as t4', 't3.enrollment_id', '=', 't4.id')
            ->select(DB::raw("count(t3.id) as beneficiary_counter, COALESCE(sum(t4.school_fees),0) as waiting_payments_total_fees"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t3.payment_request_id', $payment_request_id);
        }
        $data = $qry->first();
        return $data;
    }

    static function getSuspenseAmounts($year_of_enrollment, $term_id, $payment_request_id)
    {
        $where_statement = array(
            't1.payment_year' => $year_of_enrollment,
            't1.term_id' => $term_id
        );
        $qry = DB::table('payment_request_details as t1')
            ->join('reconciliation_suspense_account as t2', 't1.id', '=', 't2.payment_request_id')
            ->select(DB::raw("SUM(IF(t2.credit_debit=1,t2.amount,0-t2.amount)) as suspense_amount"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t1.id', $payment_request_id);
        }
        $amount = $qry->value('suspense_amount');
        return $amount;
    }

    static function getSchoolSuspenseAmounts($year_of_enrollment, $term_id, $school_id, $payment_request_id)
    {
        $where_statement = array(
            't1.payment_year' => $year_of_enrollment,
            't1.term_id' => $term_id,
            't2.school_id' => $school_id
        );
        $qry = DB::table('payment_request_details as t1')
            ->join('reconciliation_suspense_account as t2', 't1.id', '=', 't2.payment_request_id')
            ->select(DB::raw("SUM(IF(t2.credit_debit=1,t2.amount,0-t2.amount)) as suspense_amount"))
            ->where($where_statement);
        if ($payment_request_id != '') {
            $qry->where('t2.payment_request_id', $payment_request_id);
        }
        $amount = $qry->value('suspense_amount');
        return $amount;
    }

    static function checkPaymentValidationRuleStatus($rule_id)
    {
        $is_enabled = DB::table('payment_validation_rules')
            ->where('id', $rule_id)
            ->value('enabled_status');
        if ($is_enabled === 1) {
            return true;
        } else {
            return false;
        }
    }

    static function checkBeneficiaryRepetitionStatus($counter, $girl_id, $grade)
    {
        $where = array(
            'girl_id' => $girl_id,
            'grade' => $grade
        );
        $qry = DB::table('beneficiary_grade_logs as t1')
            ->select(DB::raw('t1.id,count(DISTINCT(t1.year)) as times'))
            ->where($where)
            ->groupBy('t1.girl_id')
            ->groupBy('t1.grade')
            ->havingRaw('COUNT(DISTINCT(t1.year)) >' . $counter);
        $count = $qry->count();
        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    static function getBeneficiaryEnrollmentYear($girl_id)
    {
        $admission_year = DB::table('beneficiary_information')
            ->select(DB::raw('YEAR(enrollment_date) as admission_year'))
            ->where('id', $girl_id)
            ->first();
        if (!is_null($admission_year)) {
            if ($admission_year->admission_year == '' || $admission_year->admission_year == '0000-00-00 00:00:00') {
                return 2016;
            } else {
                return $admission_year->admission_year;
            }
        } else {
            return 2016;
        }
    }

    static function checkMissingPayments($counter, $girl_id, $year, $term)
    {
        $prev_year = ($year - 1);
        $res = false;
        //get year of enrollment to be fair
        $admission_year = self::getBeneficiaryEnrollmentYear($girl_id);
        if ($admission_year == $year) {
            $res = false;
        } else {
            if ($term == 1) {
                //check for payments in term 3 and 2 of prev year
                $term_one = DB::table('beneficiary_enrollments')
                    ->where('beneficiary_id', $girl_id)
                    ->whereIn('term_id', array(2, 3))
                    ->where('year_of_enrollment', $prev_year)
                    ->where('is_validated', 1)
                    ->count();
                if ($term_one < $counter) {
                    $res = true;
                } else {
                    $res = false;
                }
            } else if ($term == 2) {
                //check for payments in term 1 of payment year and term 3 of prev year
                $term_two = DB::table('beneficiary_enrollments')
                    ->where('beneficiary_id', $girl_id)
                    ->where(function ($query) use ($year) {
                        $query->where('term_id', 1)
                            ->where('year_of_enrollment', $year);
                    })
                    ->where(function ($query) use ($prev_year) {
                        $query->where('term_id', 3)
                            ->where('year_of_enrollment', $prev_year);
                    })
                    ->where('is_validated', 1)
                    ->count();
                if ($term_two < $counter) {
                    $res = true;
                } else {
                    $res = false;
                }
            } else if ($term == 3) {
                //check for payments in term 1 and 2 of payment year
                $term_three = DB::table('beneficiary_enrollments')
                    ->where('beneficiary_id', $girl_id)
                    ->whereIn('term_id', array(1, 2))
                    ->where('year_of_enrollment', $year)
                    ->where('is_validated', 1)
                    ->count();
                if ($term_three < $counter) {
                    $res = true;
                } else {
                    $res = false;
                }
            }
        }
        return $res;
    }

    static function calculateAttendanceRate($girl_id, $year, $term)
    {
        $admission_year = self::getBeneficiaryEnrollmentYear($girl_id);
        if ($term == 1) {
            if ($admission_year == $year) {
                return false;
            } else {
                $year = ($year - 1);
                $term = 3;
            }
        } else {
            $term = ($term - 1);
        }
        $where = array(
            'year_of_enrollment' => $year,
            'term_id' => $term,
            'beneficiary_id' => $girl_id
        );
        $term_days = getTermTotalLearningDays($year, $term);
        $min_attendance_rate = Config('constants.threshhold_attendance_rate');
        $days_attended = DB::table('beneficiary_attendanceperform_details')
            ->where($where)
            ->value('benficiary_attendance');
        if (($term_days == 0 || $term_days == '') || ($min_attendance_rate == 0 || $min_attendance_rate == '')) {// || ($days_attended == 0 || $days_attended == '')) {
            return false;
        } else {
            $attendance_rate = (($days_attended / $term_days) * 100);
            if ($attendance_rate < $min_attendance_rate) {
                return true;
            } else {
                return false;
            }
        }
    }

    static function getPreviousTerm($year, $term)
    {
        if ($term == 1) {
            $term = 3;
            $year = ($year - 1);
        } else if ($term == 2) {
            $term = 1;
        } else {
            $term = 2;
        }
        return array(
            'term' => $term,
            'year' => $year
        );
    }

    static function getSetCurrentTerm()
    {
        $setTerm = DB::table('school_terms')->where('is_active', 1)->value('id');
        return $setTerm;
    }

    static function getCurrentTerm($year, $term)
    {
        if ($term == 3) {
            $term = 1;
            $year = ($year + 1);
        } else if ($term == 2) {
            $term = 3;
        } else {
            $term = 2;
        }
        return array(
            'term' => $term,
            'year' => $year
        );
    }

    static function getReconciliationSuspenseAmount($request_id, $school_id)
    {
        $suspense_amount = 0;
        try {
            $qry = DB::table('reconciliation_suspense_account as t1')
                ->select(DB::raw('SUM(CASE WHEN credit_debit = 1 THEN amount ELSE 0 END) as credit,
                                  SUM(CASE WHEN credit_debit = 2 THEN amount ELSE 0 END) as debit'))
                ->where('payment_request_id', $request_id)
                ->where('school_id', $school_id);
            $results = $qry->first();
            if (!is_null($results)) {
                $suspense_amount = ($results->credit - $results->debit);
            }
        } catch (\Exception $e) {

        }
        return $suspense_amount;
    }

    static function checkEnrollmentDuplicates($request_id)
    {
        $qry = DB::table('beneficiary_payment_records as t1')
            ->join('beneficiary_enrollments as t2', 't1.enrollment_id', '=', 't2.id')
            ->where('t1.payment_request_id', $request_id)
            ->groupBy('t2.beneficiary_id')
            ->havingRaw('count(*)  > 1');
        $counter = $qry->count();
        if ($counter > 0) {
            return true;
        } else {
            return false;
        }
    }

 static function getGrantAidedTopUpAmount()
    {
        $default = Config('constants.grant_aided_plus');
        $grant_aided_plus = $default;
        $grant_aided_plus_details = DB::table('grant_aided_fees')->first();
        if ( $grant_aided_plus_details) {
            $grant_aided_plus =  $grant_aided_plus_details->topup_amount;
        }
        return  $grant_aided_plus;
    }
    static function getWeeklyBordersTopUpAmount()
    {
        $default = Config('constants.weekly_border_plus');
        $weekly_border_plus = $default;
        $weekly_border_plus_details = DB::table('weekly_borders_fees')->first();
        if ($weekly_border_plus_details) {
            $weekly_border_plus = $weekly_border_plus_details->topup_amount;
        }
        return $weekly_border_plus;
    }

    static function unserializeDeletedData($record_id)
    {
        $prev_data = DB::table('audit_trail')
            ->where('record_id', $record_id)
            ->where('table_name', 'beneficiary_enrollments')
            ->where('table_action', 'delete')
            ->value('prev_tabledata');
        print_r(unserialize($prev_data));
    }

    static function returnMessage($results)
    {
        return count(convertStdClassObjToArray($results)) . ' records fetched!!';
    }

    static function getBatchVerificationChecklist($batch_id, $category_id)
    {
        $where = array(
            'batch_id' => $batch_id,
            'category_id' => $category_id
        );
        $checklist_type = DB::table('batch_checklist_types')
            ->where($where)
            ->value('checklist_type_id');
        return $checklist_type;
    }

    static function getBatchPaymentEligibility($batch_id)
    {
        $payment_eligible = DB::table('ebatchespaymentsetup')
            ->where('batch_id', $batch_id)
            ->where('is_active', 1)
            ->value('change_request_id');
        if ($payment_eligible == '') {
            $payment_eligible = 1;
        }
        return $payment_eligible;
    }

    static function generateRecordViewID()
    {
        $view_id = 'kgs' . Str::random(10) . date('s');
        return $view_id;
    }

    static function generateRefNumber($codes_array, $ref_id)
    {
        $serial_format = DB::table('refnumbers_formats')
            ->where('id', $ref_id)
            ->value('ref_format');
        $arr = explode("|", $serial_format);
        $serial_variables = $serial_format = DB::table('refnumbers_variables')
            ->select('identifier')
            ->get();
        $serial_variables = convertStdClassObjToArray($serial_variables);
        $serial_variables = convertAssArrayToSimpleArray($serial_variables, 'identifier');
        $ref = '';
        foreach ($arr as $code) {
            if (in_array($code, $serial_variables)) {
                isset($codes_array[$code]) ? $code = $codes_array[$code] : $code;
            }
            $ref = $ref . $code;
        }
        return $ref;
    }

    static function generateRecordRefNumber($ref_id, $process_id, $district_id, $codes_array, $table_name, $user_id)
    {
        try {
            $year = date('Y');
            $where = array(
                'year' => $year,
                'process_id' => $process_id
            );
            if (validateisNumeric($district_id)) {
                // $where['district_id'] = $district_id;
            }
            $serial_num_tracker = new ProcessSerialTracker();
            $serial_track = $serial_num_tracker->where($where)->first();
            if ($serial_track == '' || is_null($serial_track)) {
                $current_serial_id = 1;
                $serial_num_tracker->year = $year;
                $serial_num_tracker->process_id = $process_id;
                $serial_num_tracker->district_id = $district_id;
                $serial_num_tracker->created_by = $user_id;
                $serial_num_tracker->table_name = $table_name;
                $serial_num_tracker->last_serial_no = $current_serial_id;
                $serial_num_tracker->save();
            } else {
                $last_serial_id = $serial_track->last_serial_no;
                $current_serial_id = $last_serial_id + 1;
                $update_data = array(
                    'last_serial_no' => $current_serial_id,
                    'updated_by' => $user_id
                );
                $serial_num_tracker->where($where)->update($update_data);
            }
            $serial_no = str_pad($current_serial_id, 4, 0, STR_PAD_LEFT);
            //$reg_year = substr($year, -2);
            $codes_array['serial_no'] = $serial_no;
            $codes_array['record_year'] = $year;
            $ref_number = self::generateRefNumber($codes_array, $ref_id);
            $res = array(
                'success' => true,
                'ref_no' => $ref_number
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

    static function checkForLocalIPs()
    {
        $local_domains = array('10.3.248.15', '10.3.248.14', 'localhost', '127.0.0.1');
        if (in_array($_SERVER['SERVER_NAME'], $local_domains, TRUE)) {
            return true;
        }
        return false;
    }

    static function excelNumberToAlpha($numberOfCols, $code)
    {
        $alphabets = array('', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');

        $division = floor($numberOfCols / 26);
        $remainder = $numberOfCols % 26;

        if ($remainder == 0) {
            $division = $division - 1;
            $code .= 'Z';
        } else
            $code .= $alphabets[$remainder];
        if ($division > 26)
            return self::excelNumberToAlpha($division, $code);
        else
            $code .= $alphabets[$division];
        return strrev($code);
    }

    static function exportSystemRecords(Request $request, $data)
    {
        //todo: Request Params
        $cols = urldecode($request->input('cols'));
        $export_title = urldecode($request->input('export_title'));
        $export_filename = urldecode($request->input('export_filename'));
        $export_type = $request->input('export_type');
        //todo: formatting of params
        $cols = json_decode($cols, true);
        if (!isset($export_title)) {
            $export_title = 'System Report';
        }

        $headings = array();
        $records = array();
        $recordsAll = array();

        foreach ($cols as $col) {
            $headings[] = $col['text'];
        }
        foreach ($data as $datum) {
            foreach ($cols as $col) {
                $records[$col['dataIndex']] = isset($datum[$col['dataIndex']]) ? $datum[$col['dataIndex']] : '';
            }
            $recordsAll[] = $records;
        }
        if ($export_type == 'csv') {
            $report = Excel::download(new GenericExporter($recordsAll, $headings, $export_title), $export_filename . '_' . time() . '.csv', \Maatwebsite\Excel\Excel::CSV);
        } else {
            $report = Excel::download(new GenericExporter($recordsAll, $headings, $export_title), $export_filename . '_' . time() . '.xls', \Maatwebsite\Excel\Excel::XLS);
        }
        return $report;// Excel::download(new GenericExporter($recordsAll, $headings, $export_title), $export_filename . '_' . time() . '.xls');
    }

 //Job 2/12/2021
    //operational functional
    static function calculatePercentageDepreciation(Object $depreciation_details,string $test_date="",$percentage_rate=2,$only_end_date=false,$only_salvage_value=false,$get_cumulative_depreciation=false,$get_total_depreciation=false)
    {   

        // dd($percentage_rate);
            //test data
            $date_acquired=new  \DateTime('2021/11/1');
            $date_acquired=('2021-01-1');//should be string;
            $asset_life = 60; 
            $depreciable_cost=1200000;
            $salvage_value =100000;


            //real_data

            $asset_life = $depreciation_details->asset_life;
            $depreciable_cost = $depreciation_details->depreciable_cost;
        // $salvage_value = $depreciation_details->salvage_value;
            $date_acquired =$depreciation_details->date_acquired;//must be date string;
            $salvage_value="";
            //$consideredCost = ($depreciable_cost - $salvage_value);

            //deprecaiable cost excludes salvage value here;
            //$depreciable_cost= $depreciable_cost-$salvage_value;
            
            //$total_asset_days
            $asset_death_day=Carbon::createFromFormat('Y-m-d', $date_acquired)->addMonths($asset_life)->toDateString();
        

            $start_date=new DateTime($date_acquired);
            $end_date= new DateTime($asset_death_day);
            $asset_depreciation_days=[];
            $depreciation_periods=[];
            $index=0;
            //calculates depreciation days and periods
            while($start_date<=$end_date)
            {
                $end_year_date_of_period="";
                $year_of_start_date =(DateTime::createFromFormat("Y-m-d", $start_date->format('Y-m-d')))->format("Y");
                
                $end_year_date_of_period = $year_of_start_date. '-12-31';
                if(new DateTime($end_year_date_of_period)>$end_date)
                {
                    //mark end date as last period
                    $end_year_date_of_period= $end_date->format('Y-m-d');
                }
            
                $earlier = $start_date;
                $later = new DateTime($end_year_date_of_period);
                $days_between_start_and_end_date=($later->diff($earlier)->format("%a"))+1;//plus one includes end date 
                $asset_depreciation_days[]=$days_between_start_and_end_date;
                $depreciation_periods[]=[$start_date->format('Y-m-d'), $end_year_date_of_period];
                //increment start date to next year
                $start_date=new DateTime(Carbon::createFromFormat('Y-m-d', $end_year_date_of_period)->adddays(1)->toDateString());
                //dump($index);
                $index++;
                // if( (new DateTime($end_year_date_of_period))>=$end_date{
                //     $end_date=
                // }


            }//end of while loop
        
        // $percentage_rate=2;
            $straight_line_rate=1/ ($asset_life/12);
            $straight_line_rate=(100/ ($asset_life/12));
        
            // $straight_line_rate=$depreciable_cost/($asset_life/12);
            // $straight_line_rate= $straight_line_rate/$depreciable_cost;

            $declining_balance_rate=($percentage_rate*$straight_line_rate)/100;
            $book_value=$depreciable_cost;
        // $book_values[]=$book_value;//add first book value
            $book_values=array();//new addition on 13/1/2022
            $depreciation_values=[];
            $accumulated_depr_expense=[];
            $depreciation_keys_to_not_remove_if_salvage_value_is_below_zero=[];
        
            foreach($depreciation_periods as $period_key=>$period)
            {   
        
                $number_of_days=365;
                $year_of_period =(DateTime::createFromFormat("Y-m-d", $period[0]))->format("Y");
                $isleapyear=!($year_of_period % 4) && ($year_of_period % 100 || !($year_of_period % 400));
                if($isleapyear)
                {
                $number_of_days=366;
                }
            
                $year_depreciation =   $declining_balance_rate* $book_value;
                if(($asset_life/12)<1)//23/08/2022
                {
                    $year_depreciation=($year_depreciation/12)*$asset_life;
                }
            
                $daily_depreciation =  $year_depreciation/$number_of_days;
            
                $period_depreciation = $asset_depreciation_days[$period_key]*$daily_depreciation;
                $new_book_value= round( $book_value-$period_depreciation);
                
                if($new_book_value<0)
                {
                

                    if($only_salvage_value==true)
                    {
                        $last_book_value=$book_values[count($book_values)-1];
                        $salvage_value=$last_book_value[1];//lower value of the book value
                        return $salvage_value;
                    }
                
                
                }else{


                //new  formula on 12/22/2021 to accommodate salvage value calc
                $book_values[]=[$book_value,$new_book_value];//begginning book value,ending book value
                $book_value=$new_book_value;
                $depreciation_values[]=$period_depreciation;
                $depreciation_keys_to_not_remove_if_salvage_value_is_below_zero[]=$period_key;
                if($period_key==(count($depreciation_periods)-1))
                {
                    
                
                    $salvage_value=$new_book_value;
                
                    if($only_salvage_value==true)
                    {
                        return $salvage_value;
                    }
                }

                }
            
            

                //beginning of core formula!!
                //dump($new_book_value);
                // if($new_book_value>$salvage_value){
                //     //dump("here");
                // $book_values[]=[$book_value,$new_book_value];//begginning book value,ending book value
                // $book_value=$new_book_value;
                // $depreciation_values[]=$period_depreciation;
                // }else{
                
                //     $period_depreciation=$book_value-$salvage_value;
                //     $depreciation_values[]=$period_depreciation;
                
                //     $book_values[]=[$book_value,$salvage_value];//begginning book value,ending book value
                //     $book_value=$new_book_value;
                //     //to remove extra period for book value end to prevent date match error new update on 4/12/2021 Job
                //     //exception happens when  test date is beyond period and book values length is less
                //     if(count($book_values)!=count($depreciation_periods))
                //     {
                //         $count=count($book_values);
                //         $count2=count($depreciation_periods);
                //         $start_index_slice=$count;
                //         $end_index_slice=$count2;
                //         for($i=$start_index_slice;$i<$end_index_slice;$i++)
                //         {
                //            unset($depreciation_periods[$i]);
                //         }
                //     }
                //     // dump($book_values);
                //     // dump($depreciation_periods);
                //     // dump($depreciation_values);
                //     break;
                // }
                //end of core formula!! 
                

            
                // if($period_key==5)
                // {
                //     break;
                // }
                

            }

        
            //remove period keys that would have salvage value fall below zero on 13/1/2022
            
            //get only correct depreciation periods with salvage value above 0;
            $new_deprecaition_periods=[];
            foreach($depreciation_keys_to_not_remove_if_salvage_value_is_below_zero as $clean_period_key)
            {
                $new_deprecaition_periods[]=$depreciation_periods[$clean_period_key];
            }
            $depreciation_periods=$new_deprecaition_periods;

            $depre_expense=0;

            foreach($depreciation_values as $value)
            {
                $depre_expense+=$value;
                $accumulated_depr_expense[]=$depre_expense;

            }
        
            /*
            * book values = $book_values
            * periods = $depreciation_periods
            * depreciation_values = $depreciation_values
            * asset_depreciation_days =$asset_depreciation_days
            * accumulated_depr_expense = $accumulated_depr_expense
            */
            // dump( $accumulated_depr_expense);
            // dump($book_values);
            // dump($depreciation_periods);
            // dump($depreciation_values);
            // dump($asset_depreciation_days);
            if($get_total_depreciation==true)
            {
                $test_date=$asset_death_day;
            }
        
            if($test_date!=""){
            $test_date=new DateTime($test_date);//convetted to object must
            $matching_period="";
            $matching_book_val="";
            $matching_depreciation_val="";
            $matching_depreciation_days="";
            $daily_depreciation="";
            $period_start_date_to_test_date="";
            $depreciation_value_for_test_data="";
            $test_date_current_asset_value="";
            
            $current_depreciation_value="";
            $cumulative_depreciation=0;
            $depreciation_for_current_year=0;
            $max_current_value_key="";
        
        foreach($depreciation_periods as $key=>$period)
        {    
        
            //$test_date= new DateTime('2022-08-31');
            $start= new DateTime($period[0]);
            $end= new DateTime($period[1]);
            if($test_date >= $start  && $test_date<=$end)
            {
                $matching_period=$depreciation_periods[$key];
                $matching_book_val=$book_values[$key];
                $matching_depreciation_val=$depreciation_values[$key];
                $matching_depreciation_days=$asset_depreciation_days[$key];

                $earlier = $start;
                $later = $test_date;
                $period_start_date_to_test_date=($later->diff($earlier)->format("%a"))+1;//plus one includes end date 
                $daily_depreciation= $matching_depreciation_val/ $matching_depreciation_days;
                $depreciation_value_for_test_data= $period_start_date_to_test_date* $daily_depreciation;
                $test_date_current_asset_value=$book_values[$key][0]- $depreciation_value_for_test_data;
                $max_current_value_key=$key;
                $depreciation_for_current_year= $depreciation_value_for_test_data;
                //$current_depreciation_value=
                //dump($test_date->format('Y-m-d'). "is between ". $start->format('Y-m-d')." and ".$end->format('Y-m-d'));
            }
            
        }
        if($get_cumulative_depreciation==true){
        foreach($depreciation_values as $key=>$value)
        {   
            if($key<$max_current_value_key)
            {
                $cumulative_depreciation+=$value;
            }
        }
        $cumulative_depreciation+=$depreciation_for_current_year;
        if( $cumulative_depreciation=="")
        {
            $cumulative_depreciation=$salvage_value;
        }
        return $cumulative_depreciation;
        }
        if($test_date_current_asset_value=="")
        {
        
            //control
            if($salvage_value=="")//23/08/2022
            {
                $salvage_value=0;
            }
            $test_date_current_asset_value=$salvage_value;

            // return $test_date_current_asset_value=$salvage_value;
        }
        
        if($get_total_depreciation==true)
        {
            $total_depre= $depreciable_cost-  $test_date_current_asset_value;
            return $total_depre;
        }

        
        
        return $test_date_current_asset_value;
            //   dd($matching_period,$matching_book_val,$matching_depreciation_val,$matching_depreciation_days, $period_start_date_to_test_date,
            //    $depreciation_value_for_test_data, $test_date_current_asset_value);
        }else{
            if($only_end_date==true)
            {
                $count=count($book_values)-1;
            return $depreciation_periods[$count][1];
                
            }
            $depreciation_data=[];
            foreach($book_values as $index=>$asset_book_data)
            {
                
                $depreciation_data[] = array(
                'year_count' => $index+1,
                'period_count'=>$index+1,
                'period' =>   implode(" to ",$depreciation_periods[$index]),
                'depr_expense' =>  $depreciation_values[$index],
                'accumulated_depr' => $accumulated_depr_expense[$index],
                'book_value' => $asset_book_data[1]//ending book value
            );


            }//endfor
            return $depreciation_data;
        }

    }
    //job
     //job
     static function  calculateStraightLineDepreciation(Object $depreciation_details,string $test_date="",$only_end_date=false,$get_cumulative_depreciation=false,$get_total_depreciation)
     {   
 
                 // dd($percentage_rate);
                     //test data
                     $date_acquired=new  \DateTime('2021/11/1');
                     $date_acquired=('2021-01-1');//should be string;
                     $asset_life = 60; 
                     $depreciable_cost=1200000;
                     $salvage_value =100000;
 
 
                     //real_data
 
                     $asset_life = $depreciation_details->asset_life;
                     $depreciable_cost = $depreciation_details->depreciable_cost;
                     $salvage_value = $depreciation_details->salvage_value;
                     $date_acquired =$depreciation_details->date_acquired;//must be date string;
                 
                     //$consideredCost = ($depreciable_cost - $salvage_value);
 
                     //deprecaiable cost excludes salvage value here;
                     //$depreciable_cost= $depreciable_cost-$salvage_value;
                     
                     //$total_asset_days
                     $asset_death_day=Carbon::createFromFormat('Y-m-d', $date_acquired)->addMonths($asset_life)->toDateString();
                 
 
                     $start_date=new DateTime($date_acquired);
                     $end_date= new DateTime($asset_death_day);
                     $asset_depreciation_days=[];
                     $depreciation_periods=[];
                     $index=0;
                     //calculates depreciation days and periods
                     while($start_date<=$end_date)
                     {
                         $end_year_date_of_period="";
                         $year_of_start_date =(DateTime::createFromFormat("Y-m-d", $start_date->format('Y-m-d')))->format("Y");
                         
                         $end_year_date_of_period = $year_of_start_date. '-12-31';
                         if(new DateTime($end_year_date_of_period)>$end_date)
                         {
                             //mark end date as last period
                             $end_year_date_of_period= $end_date->format('Y-m-d');
                         }
                     
                         $earlier = $start_date;
                         $later = new DateTime($end_year_date_of_period);
                         $days_between_start_and_end_date=($later->diff($earlier)->format("%a"))+1;//plus one includes end date 
                         $asset_depreciation_days[]=$days_between_start_and_end_date;
                         $depreciation_periods[]=[$start_date->format('Y-m-d'), $end_year_date_of_period];
                         //increment start date to next year
                         $start_date=new DateTime(Carbon::createFromFormat('Y-m-d', $end_year_date_of_period)->adddays(1)->toDateString());
                         //dump($index);
                         $index++;
                         // if( (new DateTime($end_year_date_of_period))>=$end_date{
                         //     $end_date=
                         // }
 
 
                     }//end of while loop
                 // $percentage_rate=2;
                 
                 
                 
                     $straight_line_value= ($depreciable_cost-$salvage_value)/($asset_life/12);
                 
                     $book_value=$depreciable_cost;
                 // $book_values[]=$book_value;//add first book value
                     $depreciation_values=[];
                     $accumulated_depr_expense=[];
                     
                 
                     foreach($depreciation_periods as $period_key=>$period)
                     {   
                     
                         $number_of_days=365;
                         $year_of_period =(DateTime::createFromFormat("Y-m-d", $period[0]))->format("Y");
                         $isleapyear=!($year_of_period % 4) && ($year_of_period % 100 || !($year_of_period % 400));
                         if($isleapyear)
                         {
                         $number_of_days=366;
                         }
                     
                         $year_depreciation =     $straight_line_value;
                         //dump($year_depreciation);
                         $daily_depreciation =  $year_depreciation/$number_of_days;
                         $period_depreciation = $asset_depreciation_days[$period_key]*$daily_depreciation;
                     
                         //dump($period_depreciation);
                         $new_book_value= $book_value-$period_depreciation;
                     
                         //dump($new_book_value);
                         if($new_book_value>$salvage_value){
                             //dump("here");
                         $book_values[]=[$book_value,$new_book_value];//begginning book value,ending book value
                         $book_value=$new_book_value;
                         $depreciation_values[]=$period_depreciation;
                         }else{
                         
                             $period_depreciation=$book_value-$salvage_value;
                             $depreciation_values[]=$period_depreciation;
                             $book_values[]=[$book_value,$salvage_value];//begginning book value,ending book value
                             $book_value=$new_book_value;
                             if(count($book_values)!=count($depreciation_periods))
                             {
                                 $count=count($book_values);
                                 $count2=count($depreciation_periods);
                                 $start_index_slice=$count;
                                 $end_index_slice=$count2;
                                 for($i=$start_index_slice;$i<$end_index_slice;$i++)
                                 {
                                 unset($depreciation_periods[$i]);
                                 }
                             }
                             // dump($book_values);
                             // dump($depreciation_periods);
                             // dump($depreciation_values);
                             break;
                         }
 
                         
 
                     
                         // if($period_key==5)
                         // {
                         //     break;
                         // }
                         
 
                     }
                     $depre_expense=0;
 
                     foreach($depreciation_values as $value)
                     {
                         $depre_expense+=$value;
                         $accumulated_depr_expense[]=$depre_expense;
 
                     }
 
                     /*
                     * book values = $book_values
                     * periods = $depreciation_periods
                     * depreciation_values = $depreciation_values
                     * asset_depreciation_days =$asset_depreciation_days
                     * accumulated_depr_expense = $accumulated_depr_expense
                     */
                     // dump( $accumulated_depr_expense);
                     // dump($book_values);
                     // dump($depreciation_periods);
                     // dump($depreciation_values);
                     // dump($asset_depreciation_days);
                     if($get_total_depreciation==true)
                     {
                         $test_date=$asset_death_day;
                     }
                     if($test_date!=""){
                     $test_date=new DateTime($test_date);//convetted to object must
                     $matching_period="";
                     $matching_book_val="";
                     $matching_depreciation_val="";
                     $matching_depreciation_days="";
                     $daily_depreciation="";
                     $period_start_date_to_test_date="";
                     $depreciation_value_for_test_data="";
                     $test_date_current_asset_value="";
 
                     $current_depreciation_value="";
                     $cumulative_depreciation=0;
                     $depreciation_for_current_year=0;
                     $max_current_value_key="";
                 foreach($depreciation_periods as $key=>$period)
                 {    
                     // $test_date= new DateTime('2022-08-31');
                     $start= new DateTime($period[0]);
                     $end= new DateTime($period[1]);
                     if($test_date >= $start  && $test_date<=$end)
                     {
                         $matching_period=$depreciation_periods[$key];
                         $matching_book_val=$book_values[$key];
                         $matching_depreciation_val=$depreciation_values[$key];
                         $matching_depreciation_days=$asset_depreciation_days[$key];
 
                         $earlier = $start;
                         $later = $test_date;
                         $period_start_date_to_test_date=($later->diff($earlier)->format("%a"))+1;//plus one includes end date 
                         $daily_depreciation= $matching_depreciation_val/ $matching_depreciation_days;
                         $depreciation_value_for_test_data= $period_start_date_to_test_date* $daily_depreciation;
                         $test_date_current_asset_value=$book_values[$key][0]- $depreciation_value_for_test_data;
                         $max_current_value_key=$key;
                         $depreciation_for_current_year= $depreciation_value_for_test_data;
                         //dump($test_date->format('Y-m-d'). "is between ". $start->format('Y-m-d')." and ".$end->format('Y-m-d'));
                     }
                     
                 }
 
                 if($get_cumulative_depreciation==true){
                 foreach($depreciation_values as $key=>$value)
                 {   
                     if($key<$max_current_value_key)
                     {
                         $cumulative_depreciation+=$value;
                     }
                 }
                 $cumulative_depreciation+=$depreciation_for_current_year;
                 if( $cumulative_depreciation=="")
                 {
                     $cumulative_depreciation=$salvage_value;
                 }
                 return $cumulative_depreciation;
                 }
                 
                 if($test_date_current_asset_value=="")
                 {
                     $test_date_current_asset_value=$salvage_value;
 
                     // return $test_date_current_asset_value=$salvage_value;
                 }
                 if($get_total_depreciation==true)
                 {
                     $total_depre= $depreciable_cost- $test_date_current_asset_value;
                     return $total_depre;
                 }
 
 
                 return $test_date_current_asset_value;
                     //   dd($matching_period,$matching_book_val,$matching_depreciation_val,$matching_depreciation_days, $period_start_date_to_test_date,
                     //    $depreciation_value_for_test_data, $test_date_current_asset_value);
                 }else{
                     if($only_end_date==true)
                     {
                         $count=count($book_values)-1;
                     return $depreciation_periods[$count][1];
                         
                     }
                     $depreciation_data=[];
                     foreach($book_values as $index=>$asset_book_data)
                     {
                         
                         $depreciation_data[] = array(
                         'year_count' => $index+1,
                         'period_count'=>$index+1,
                         'period' =>   implode(" to ",$depreciation_periods[$index]),
                         'depr_expense' =>  $depreciation_values[$index],
                         'accumulated_depr' => $accumulated_depr_expense[$index],
                         'book_value' => $asset_book_data[1]//ending book value
                     );
 
 
                     }//endfor
                     return $depreciation_data;
                 }
 
     }
   

}
