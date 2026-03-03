<?php

namespace App\Modules\FinancialModule\Http\Controllers;

use PDF;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Jobs\GenericSendEmailJob;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class FinancialReportsController extends BaseController
{
    //start frank
    public function formatMoney($money)
    {
        if ($money == '' || $money == 0) {
            $money = '00';
        }
        return is_numeric($money) ? number_format((round($money)), 2, '.', ',') : round($money);
    }

    public function saveFinancialModuleCommonData(Request $req)
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
            unset($post_data['model']);
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

    public function getFinanceDashboardData(){
        try {
            $budget_no = 0;
            $workplan_no = 0;
            $total_imp_plans = 0;
            $archived_imp_plans = 0;
            $logged_in_user = $this->user_id;
            // $user_access_point = Auth::user()->access_point_id;
            $bud_qry = DB::table('budget_allocation as t1')
                ->select(DB::raw("COUNT(DISTINCT t1.thematic_id) as budget_no"));
            $plan_qry = DB::table('financial_workplan as t1')
                ->select(DB::raw("COUNT(DISTINCT t1.id) as workplan_no"));
            $arch_impl_qry = DB::table('financial_implementation as t1')
                ->select(DB::raw("COUNT(DISTINCT t1.workplan_id) as archived_imp_plans"))
                ->where("t1.workflow_stage_id", 6);
            $impl_qry = DB::table('financial_implementation as t1')
                ->select(DB::raw("COUNT(t1.id) as total_imp_plans"));
            $bud_qry = $bud_qry->first();
            $plan_qry = $plan_qry->first();
            $arch_impl_qry = $arch_impl_qry->first();
            $impl_qry = $impl_qry->first();
            if ($bud_qry) {
                $budget_no = $bud_qry->budget_no;
            }
            if ($plan_qry) {
                $workplan_no = $plan_qry->workplan_no;
            }
            if ($arch_impl_qry) {
                $archived_imp_plans = $arch_impl_qry->archived_imp_plans;
            }
            if ($impl_qry) {
                $total_imp_plans = $impl_qry->total_imp_plans;
            }
            $res = array(
                'success' => true,
                'budget_no' => number_format($budget_no),
                'workplan_no' => number_format($workplan_no),
                'archived_imp_plans' => number_format($archived_imp_plans),
                'total_imp_plans' => number_format($total_imp_plans)
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

    public function getBalancePerBackingSheetReceipt($backing_sheet_id,$receipt){
        try{
            $qry = DB::table('financial_dollar_cashbook_details as t1')
                ->join('financial_dollar_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')                
                ->select(DB::raw('(t2.receipts - SUM(IF(t1.payments IS NULL,0,t1.payments))) AS balance'))
                ->where('t1.backing_sheet_id', $backing_sheet_id)
                ->first();
            if(validateisNumeric($qry->balance)){
                $res = $qry->balance;
                DB::table('financial_dollar_backing_sheets')
                    ->where('id', $backing_sheet_id)
                    ->update(array('balance' => $res));
            } else {
                $res = $receipt;
            }
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getBalancePerPaymentReceipt($backing_sheet_id,$receipt,$is_edit,$id){
        try{
            if($is_edit == 0){
                $qry = DB::table('financial_dollar_cashbook_details as t1')
                    ->join('financial_dollar_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')                
                    ->select(DB::raw('(t2.balance - '.$receipt.') AS balance'))
                    ->where('t1.backing_sheet_id', $backing_sheet_id)
                    ->first();
                    if($qry){
                        if(validateisNumeric($qry->balance)){
                            $res = $qry->balance;
                            DB::table('financial_dollar_backing_sheets')
                                ->where('id', $backing_sheet_id)
                                ->update(array('balance' => $res));
                            DB::table('financial_dollar_cashbook_details')
                                ->where('id', $id)
                                ->update(array('balance' => $res));
                        } else {
                            $res = $receipt;
                            DB::table('financial_dollar_backing_sheets')
                                ->where('id', $backing_sheet_id)
                                ->update(array('balance' => $res));
                            DB::table('financial_dollar_cashbook_details')
                                ->where('id', $id)
                                ->update(array('balance' => $res));
                        }
                    } else {
                        $select_qry = DB::table('financial_dollar_backing_sheets')              
                            ->select('balance')
                            ->where('id', $backing_sheet_id)
                            ->first();
                        $res = $select_qry->balance - $receipt;
                        DB::table('financial_dollar_backing_sheets')
                            ->where('id', $backing_sheet_id)
                            ->update(array('balance' => $res));
                        DB::table('financial_dollar_cashbook_details')
                            ->where('id', $id)
                            ->update(array('balance' => $res));
                    }
            } else {
                $qry = DB::table('financial_dollar_cashbook_details as t1')
                    ->join('financial_dollar_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')                
                    ->select(DB::raw('(t2.receipts - SUM(IF(t1.payments IS NULL,0,t1.payments))) AS balance'))
                    ->where('t1.backing_sheet_id', $backing_sheet_id)
                    ->first();
                if(validateisNumeric($qry->balance)){
                    $res = $qry->balance;
                    DB::table('financial_dollar_backing_sheets')
                        ->where('id', $backing_sheet_id)
                        ->update(array('balance' => $res));
                    DB::table('financial_dollar_cashbook_details')
                        ->where('id', $id)
                        ->update(array('balance' => $res));
                }
            }
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getBozBalancePerPaymentReceipt($backing_sheet_id,$receipt,$is_edit,$id){
        try{
            if($is_edit == 0){
                $qry = DB::table('financial_boz_cashbook_details as t1')
                    ->join('financial_boz_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')                
                    ->select(DB::raw('(t2.balance - '.$receipt.') AS balance'))
                    ->where('t1.backing_sheet_id', $backing_sheet_id)
                    ->first();
                    if($qry){
                        if(validateisNumeric($qry->balance)) {
                            $res = $qry->balance;
                            DB::table('financial_boz_backing_sheets')
                                ->where('id', $backing_sheet_id)
                                ->update(array('balance' => $res));
                            DB::table('financial_boz_cashbook_details')
                                ->where('id', $id)
                                ->update(array('balance' => $res));
                        } else {
                            DB::table('financial_boz_backing_sheets')
                                ->where('id', $backing_sheet_id)
                                ->update(array('balance' => $receipt));
                            DB::table('financial_boz_cashbook_details')
                                ->where('id', $id)
                                ->update(array('balance' => $receipt));
                        }
                    } else {
                        $select_qry = DB::table('financial_boz_backing_sheets')              
                            ->select('balance')
                            ->where('id', $backing_sheet_id)
                            ->first();
                        $res = $select_qry->balance - $receipt;
                        DB::table('financial_boz_backing_sheets')
                            ->where('id', $backing_sheet_id)
                            ->update(array('balance' => $res));
                        DB::table('financial_boz_cashbook_details')
                            ->where('id', $id)
                            ->update(array('balance' => $res));
                    }
            } else {
                $qry = DB::table('financial_boz_cashbook_details as t1')
                    ->join('financial_boz_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')                
                    ->select(DB::raw('(t2.receipts - SUM(IF(t1.payments IS NULL,0,t1.payments))) AS balance'))
                    ->where('t1.backing_sheet_id', $backing_sheet_id)
                    ->first();
                if(validateisNumeric($qry->balance)){
                    $res = $qry->balance;
                    DB::table('financial_boz_backing_sheets')
                        ->where('id', $backing_sheet_id)
                        ->update(array('balance' => $res));
                    DB::table('financial_boz_cashbook_details')
                        ->where('id', $id)
                        ->update(array('balance' => $res));
                }
            }
            $res = array(
                'success' => true,
                'message' => 'Update Successful'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getZanacoBalancePerPaymentReceipt($backing_sheet_id,$receipt,$id){
        try{
            $qry = DB::table('financial_cashbook_details as t1')
                ->join('financial_backing_sheets as t2', 't1.backing_sheet_id', '=', 't2.id')
                ->select(DB::raw('(t2.balance - SUM(IF(t1.payments IS NULL,0,t1.payments))) AS balance,
                        SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_payments,t1.work_plan_ref'))
                ->where('t1.backing_sheet_id', $backing_sheet_id)->first();
            // dd($qry);
            $balance = $qry->balance;
            $work_plan_id = $qry->work_plan_ref;
            $wk_plan_qry = DB::table('budget_allocation as t1')
                ->join('financial_workplan as t2', 't1.id', '=', 't2.budget_id')
                ->select('t1.id','t1.current_balance','t1.current_balance_dollar','t1.budget_amount_dollar','t1.budget_amount_zmw','t1.rate')
                ->where('t2.id', $work_plan_id)->first();
            // $budget_current_balance = is_numeric($wk_plan_qry->current_balance) ? $wk_plan_qry->current_balance : 0;
            $budget_current_balance = is_numeric($wk_plan_qry->current_balance) ? $wk_plan_qry->current_balance : (is_numeric($wk_plan_qry->budget_amount_zmw) ? $wk_plan_qry->budget_amount_zmw : 0);
            $budget_dollar_balance = is_numeric($wk_plan_qry->current_balance_dollar) ? $wk_plan_qry->current_balance_dollar : (is_numeric($wk_plan_qry->budget_amount_dollar) ? $wk_plan_qry->budget_amount_dollar : 0);
            $budget_id = $wk_plan_qry->id;
            $dollar_balance = $receipt / $wk_plan_qry->rate;
            // current_balance
            if(validateisNumeric($balance)) {
                $budget_balance = $budget_current_balance - $receipt;
                $budget_balance_in_dollars = $budget_dollar_balance - $dollar_balance;
                $current_expense_zmw = $wk_plan_qry->budget_amount_zmw - $budget_balance;
                $current_expense_dollar = $current_expense_zmw / $wk_plan_qry->rate;
                $update_array = array(
                    'balance' => $balance,
                    'payments' => $qry->total_payments
                );
                $budget_array = array(
                    'current_balance' => $budget_balance,
                    'current_balance_dollar' => $budget_balance_in_dollars,
                    'current_expense_zmw' => $current_expense_zmw,
                    'current_expense_dollar' => $current_expense_dollar
                );
                $backing_res = DB::table('financial_backing_sheets')->where('id', $backing_sheet_id)->update($update_array);
                $budget_res = DB::table('budget_allocation')->where('id', $budget_id)->update($budget_array);
                $balance_result = array(
                    'backing_res' => $backing_res,
                    'budget_res' => $budget_res
                );
                $res = array(
                    'success' => true,
                    'message' => 'Balance Update Successful',
                    'results' => $balance_result
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function onBackingSheetSelect(Request $request){
        try{
            $id = $request->input('id');
            $qry = DB::table('financial_dollar_backing_sheets as t1')               
                ->select('t1.balance')
                ->where('t1.id', $id)
                ->first();
            $balance = $qry->balance;
            if(validateisNumeric($balance)){
                $res = array(
                    'success' => true,
                    'results' => $balance,
                    'message' => 'All is well'
                );
            } else {
                $res = array(
                    'success' => true,
                    'results' => 0,
                    'message' => 'All is not well'
                );
            }
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function saveDollarBackingSheetDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $cashbook_type = $request->input('cashbook_type');
            $acc_type = $request->input('acc_type');
            $table_name = $request->input('table_name');
            $receipt = $request->input('receipts');
            $table_data = array(
                'cheque_status' => $request->input('cheque_status'),
                'cashbook_type' => $cashbook_type,
                'acc_type' => $acc_type,
                'payee' => $request->input('payee'),
                'cheque_no' => $request->input('cheque_no'),
                'receipts' => $request->input('receipts'),
                'receipt_date' => $request->input('receipt_date'),
                'details' => $request->input('details')
            );
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {
                //Update backing sheet details
                $table_data['balance'] = $this->getBalancePerBackingSheetReceipt($id,$receipt);
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);                
            } else {
                //Add new backing sheet details
                $table_data['balance'] = $receipt;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);    
            }
            $res = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
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

    public function saveDollarAccountDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $cashbook_type = $request->input('cashbook_type');
            $acc_type = $request->input('acc_type');
            $table_name = $request->input('table_name');
            $backing_sheet_id = $request->input('backing_sheet_id');
            $payments = $request->input('payments');
            $payments_zmw = $request->input('payments_zmw');
            $payments_description = $request->input('payments_description');
            $table_data = array(
                'cheque_status' => $request->input('cheque_status'),
                'cashbook_type' => $cashbook_type,
                'acc_type' => $acc_type,
                'backing_sheet_id' => $backing_sheet_id,
                'schedule_type' => $request->input('schedule_type'),
                'payee' => $request->input('payee'),
                'cheque_no' => $request->input('cheque_no'),
                'receipts' => $request->input('receipts'),
                'payments' => $payments,
                'rate' => $request->input('rate'),
                'payments_zmw' => $payments_zmw,
                'payments_description' => $payments_description,
                'receipt_date' => $request->input('receipt_date'),
                'details' => $request->input('details')
            );  
            
            $boz_tbl_data = $table_data;
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {
                //Update backing sheet details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                //update balance details
                // $res = array('success' => true);
                if($res['success'] == true){
                    $this->getBalancePerPaymentReceipt($backing_sheet_id,$payments,1,$id);
                    //update boz backing sheet details
                    unset($boz_tbl_data['backing_sheet_id']);
                    unset($boz_tbl_data['schedule_type']);
                    unset($boz_tbl_data['payments_description']);
                    unset($boz_tbl_data['payments']);
                    $boz_tbl_data['receipts'] = $payments_zmw;
                    $boz_tbl_data['dollar_payment_id'] = $id;
                    $boz_tbl_data['balance'] = $payments_zmw;
                    $boz_tbl_data['initial_balance'] = $payments_zmw;
                    $boz_tbl_data['details'] = $payments_description;
                    $boz_tbl_data['created_at'] = Carbon::now();
                    $boz_tbl_data['created_by'] = $user_id;                    
                    $boz_id = DB::table('financial_boz_backing_sheets as t1')
                        ->select('t1.id')->where('t1.dollar_payment_id', $id)->first();
                    $where_boz = array(
                        'id' => $boz_id->id
                    );
                    $boz_previous_data = getPreviousRecords('financial_boz_backing_sheets', $where_boz);
                    $res = updateRecord('financial_boz_backing_sheets', $boz_previous_data, $where_boz, $boz_tbl_data, $user_id);
                }
            } else {
                //Add new backing sheet details
                $balance = $this->getBalancePerPaymentReceipt($backing_sheet_id,$payments,0,$id);
                $table_data['balance'] = $balance;
                $table_data['initial_balance'] = $balance;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $dollar_payment_id = DB::table($table_name)->insertGetId($table_data);
                if($dollar_payment_id) {
                    //insert boz backing sheet details
                    unset($boz_tbl_data['backing_sheet_id']);
                    unset($boz_tbl_data['schedule_type']);
                    unset($boz_tbl_data['payments_description']);
                    unset($boz_tbl_data['payments']);
                    $boz_tbl_data['receipts'] = $payments_zmw;
                    $boz_tbl_data['dollar_payment_id'] = $dollar_payment_id;
                    $boz_tbl_data['balance'] = $payments_zmw;
                    $boz_tbl_data['initial_balance'] = $payments_zmw;
                    $boz_tbl_data['details'] = $payments_description;
                    $boz_tbl_data['created_at'] = Carbon::now();
                    $boz_tbl_data['created_by'] = $user_id;
                    $res = insertRecord('financial_boz_backing_sheets', $boz_tbl_data, $user_id);
                } else {
                    return false;
                }
            }
            $response = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
            $response = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $response = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($response);
    }

    public function saveBozAccountDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $cashbook_type = $request->input('cashbook_type');
            $acc_type = $request->input('acc_type');
            $table_name = $request->input('table_name');
            $backing_sheet_id = $request->input('backing_sheet_id');
            $payments = $request->input('payments');
            $payments_description = $request->input('payments_description');
            $table_data = array(
                'cheque_status' => $request->input('cheque_status'),
                'cashbook_type' => $cashbook_type,
                'acc_type' => $acc_type,
                'backing_sheet_id' => $backing_sheet_id,
                'schedule_type' => $request->input('schedule_type'),
                'payee' => $request->input('payee'),
                'cheque_no' => $request->input('cheque_no'),
                'receipts' => $request->input('receipts'),
                'payments' => $payments,
                'payments_description' => $payments_description,
                'receipt_date' => $request->input('receipt_date'),
                'details' => $request->input('details')
            );  
            
            $boz_tbl_data = array();
            $boz_tbl_data = $table_data;
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {
                //Update backing sheet details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);
                //update balance details
                if($res['success'] == true){
                    $this->getBozBalancePerPaymentReceipt($backing_sheet_id,$payments,1,$id);
                    //update boz backing sheet details
                    unset($boz_tbl_data['backing_sheet_id']);
                    unset($boz_tbl_data['schedule_type']);
                    unset($boz_tbl_data['payments_description']);
                    unset($boz_tbl_data['payments']);
                    $boz_tbl_data['receipts'] = $payments;
                    $boz_tbl_data['boz_payment_id'] = $id;
                    $boz_tbl_data['balance'] = $payments;
                    $boz_tbl_data['initial_balance'] = $payments;
                    $boz_tbl_data['details'] = $payments_description;
                    $boz_tbl_data['created_at'] = Carbon::now();
                    $boz_tbl_data['created_by'] = $user_id;                    
                    $boz_id = DB::table('financial_backing_sheets as t1')
                        ->select('t1.id')->where('t1.boz_payment_id', $id)
                        ->first();
                    $where_boz = array(
                        'id' => $boz_id->id
                    );
                    $boz_previous_data = getPreviousRecords('financial_backing_sheets', $where_boz);
                    $res = updateRecord('financial_backing_sheets', $boz_previous_data, $where_boz, $boz_tbl_data, $user_id);
                }
            } else {
                //Add new backing sheet details
                $this->getBozBalancePerPaymentReceipt($backing_sheet_id,$payments,0,$id);
                // dd($balance);
                // $table_data['balance'] = $balance;
                // $table_data['initial_balance'] = $balance;
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $boz_payment_id = DB::table($table_name)->insertGetId($table_data);
                if($boz_payment_id) {
                    //insert boz backing sheet details
                    unset($boz_tbl_data['backing_sheet_id']);
                    unset($boz_tbl_data['schedule_type']);
                    unset($boz_tbl_data['payments_description']);
                    unset($boz_tbl_data['payments']);
                    $boz_tbl_data['receipts'] = $payments;
                    $boz_tbl_data['boz_payment_id'] = $boz_payment_id;
                    $boz_tbl_data['balance'] = $payments;
                    $boz_tbl_data['initial_balance'] = $payments;
                    $boz_tbl_data['details'] = $payments_description;
                    $boz_tbl_data['created_at'] = Carbon::now();
                    $boz_tbl_data['created_by'] = $user_id;
                    $res = insertRecord('financial_backing_sheets', $boz_tbl_data, $user_id);
                } else {
                    return false;
                }
            }
            $response = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
            $response = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $response = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return response()->json($response);
    }
    
    public function saveBozBackingSheetDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $table_name = $request->has('table_name') ? $request->input('table_name') : 'financial_boz_backing_sheets';
            if ($table_name == 'financial_boz_backing_sheets') {         
                $table_data = array(
                    'cheque_status' => $request->input('cheque_status'),
                    'cashbook_type' => $request->input('cashbook_type'),
                    'acc_type' => $request->input('acc_type'),
                    'payee' => $request->input('payee'),
                    'cheque_no' => $request->input('cheque_no'),
                    'receipts' => $request->input('receipts'),
                    'payments' => $request->input('payments'),
                    'balance' => $request->input('balance'),
                    'receipt_date' => $request->input('receipt_date'),
                    'tasks' => $request->input('tasks')
                );
            } else {         
                $table_data = array(
                    'cheque_status' => $request->input('cheque_status'),
                    'cashbook_type' => $request->input('cashbook_type'),
                    'acc_type' => $request->input('acc_type'),
                    'backing_sheet_id' => $request->input('backing_sheet_id'),
                    'schedule_type' => $request->input('schedule_type'),
                    'payee' => $request->input('payee'),
                    'cheque_no' => $request->input('cheque_no'),
                    'receipts' => $request->input('receipts'),
                    'payments' => $request->input('payments'),
                    'payments_description' => $request->input('payments_description'),
                    'receipt_date' => $request->input('receipt_date')
                );
            }
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {
                //Update backing sheet details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);                
            } else {
                //Add new backing sheet details
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);    
            }
            $res = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
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

    public function saveBackingSheetDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $table_name = $request->has('table_name') ? $request->input('table_name') : 'financial_backing_sheets';
            if ($table_name == 'financial_backing_sheets') {         
                $table_data = array(
                    'cheque_status' => $request->input('cheque_status'),
                    'cashbook_type' => $request->input('cashbook_type'),
                    'acc_type' => $request->input('acc_type'),
                    'payee' => $request->input('payee'),
                    'cheque_no' => $request->input('cheque_no'),
                    'receipts' => $request->input('receipts'),
                    'payments' => $request->input('payments'),
                    'balance' => $request->input('balance'),
                    'receipt_date' => $request->input('receipt_date'),
                    'tasks' => $request->input('tasks')
                );
            } else {         
                $table_data = array(
                    'cheque_status' => $request->input('cheque_status'),
                    'cashbook_type' => $request->input('cashbook_type'),
                    'acc_type' => $request->input('acc_type'),
                    'backing_sheet_id' => $request->input('backing_sheet_id'),
                    'schedule_type' => $request->input('schedule_type'),
                    'work_plan_ref' => $request->input('work_plan_ref'),
                    'programme_id' => $request->input('programme_id'),
                    'activity_id' => $request->input('activity_id'),
                    'payee' => $request->input('payee'),
                    'cheque_no' => $request->input('cheque_no'),
                    'receipts' => $request->input('receipts'),
                    'payments' => $request->input('payments'),
                    'payments_description' => $request->input('payments_description'),
                    'balance' => $request->input('balance'),
                    'receipt_date' => $request->input('receipt_date'),
                    'tasks' => $request->input('tasks')
                );
            }
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {
                //Update backing sheet details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);                
            } else {
                //Add new backing sheet details
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);    
            }
            $res = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
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

    public function saveZanacoCashbookDetails(Request $request){
        try{
            $id = $request->input('id');
            $user_id = $this->user_id;
            $cashbook_type = $request->input('cashbook_type');
            $acc_type = $request->input('acc_type');
            $table_name = $request->input('table_name');
            $backing_sheet_id = $request->input('backing_sheet_id');
            $payments = $request->input('payments');
            $payments_description = $request->input('payments_description');
            $table_data = array(
                'cheque_status' => $request->input('cheque_status'),
                'cashbook_type' => $cashbook_type,
                'acc_type' => $acc_type,
                'backing_sheet_id' => $backing_sheet_id,
                'schedule_type' => $request->input('schedule_type'),
                'work_plan_ref' => $request->input('work_plan_ref'),
                'programme_id' => $request->input('programme_id'),
                'activity_id' => $request->input('activity_id'),
                'payee' => $request->input('payee'),
                'cheque_no' => $request->input('cheque_no'),
                'receipts' => $request->input('receipts'),
                'payments' => $payments,
                'payments_description' => $payments_description,
                'balance' => $request->input('balance'),
                'receipt_date' => $request->input('receipt_date'),
                'tasks' => $request->input('tasks'),
                'budget_id' => $request->input('budget_id')
            );
            $backingsheet_data = $table_data;
            $where = array(
                'id' => $id
            );
            if(validateisNumeric($id)) {  
                //Update cashbook details
                $table_data['updated_at'] = Carbon::now();
                $table_data['updated_by'] = $user_id;
                $previous_data = getPreviousRecords($table_name, $where);
                $res = updateRecord($table_name, $previous_data, $where, $table_data, $user_id);                
                //update balance details  
                if($res['success'] == true){
                    $method_res = $this->getZanacoBalancePerPaymentReceipt($backing_sheet_id,$payments,$id);
                } else {
                    return false;
                }
            } else {
                //Add new cashbook details
                $table_data['created_at'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $res = insertRecord($table_name, $table_data, $user_id);          
                //update balance details
                if($res['success'] == true){
                    $method_res = $this->getZanacoBalancePerPaymentReceipt($backing_sheet_id,$payments,$id);
                } else {
                    return false;
                }   
            }
            $res = array(
                'success' => true,
                'results' => $res,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $exception) {
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

    public function getAprvdImplPlanDetails(Request $request){
        try{     
            // $qry = DB::table('financial_implementation as t1')
            //     ->leftJoin('financial_workplan as t2', 't1.workplan_id','=','t2.id')
            //     ->select('t1.id','t2.mis_no')
            //     ->where('t1.submitted', 1);            
            $budget_id = $request->input('budget_id');  
            $qry = DB::table('financial_workplan as t1')
                ->select('t1.id','t1.mis_no')
                ->where('t1.submitted', 1);                
            if (validateisNumeric($budget_id)) {
                $qry->where('t1.budget_id', $budget_id);
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getWorkplanDetailsForCashbk(Request $request){
        try{  
            $implementation_id = $request->input('id');       
            $qry = DB::table('financial_implementation as t1')
                ->leftJoin('financial_workplan as t2','t1.workplan_id','=','t2.id')
                ->select('t1.id','t2.mis_no','t2.programme_id','t2.activity','t2.task')
                ->where('t1.id', $implementation_id);
                $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'Success'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }        

    public function getBackingSheetDetails(Request $request){
        try{
            $table_name = $request->has('table_name') ? $request->input('table_name') : 'financial_backing_sheets';
            if ($table_name == 'financial_backing_sheets') {
                $qry = DB::table('financial_backing_sheets')->select('*');
            } else {
                $qry = DB::table('financial_cashbook_details as t1')
                    ->leftJoin('financial_activities', 't1.activity_id','=','financial_activities.id')
                    ->leftJoin('financial_programmes', 't1.programme_id','=','financial_programmes.id')
                    ->leftJoin('financial_backing_sheets', 't1.backing_sheet_id','=','financial_backing_sheets.id')
                    ->select(DB::raw("financial_activities.activity_name,financial_programmes.programme_name,financial_backing_sheets.payee as backing_payee,t1.*"
                    )
                );
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getBozBackingSheetDetails(Request $request){
        try{
            $table_name = $request->has('table_name') ? $request->input('table_name') : 'financial_boz_backing_sheets';
            if ($table_name == 'financial_boz_backing_sheets') {
                $qry = DB::table('financial_boz_backing_sheets')->select('*');
            } else {
                $qry = DB::table('financial_boz_cashbook_details as t1')
                    ->leftJoin('financial_activities', 't1.activity_id','=','financial_activities.id')
                    ->leftJoin('financial_programmes', 't1.programme_id','=','financial_programmes.id')
                    ->leftJoin('financial_boz_backing_sheets', 't1.backing_sheet_id','=','financial_boz_backing_sheets.id')
                    ->select(DB::raw("financial_activities.activity_name,financial_programmes.programme_name,financial_boz_backing_sheets.payee as backing_payee,t1.*"
                    )
                );
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getDollarBackingSheetDetails(Request $request){
        try{
            $table_name = $request->has('table_name') ? $request->input('table_name') : 'financial_dollar_backing_sheets';
            if ($table_name == 'financial_dollar_backing_sheets') {
                $qry = DB::table('financial_dollar_backing_sheets')->select('*');
            } else {
                $qry = DB::table('financial_dollar_cashbook_details as t1')
                    ->leftJoin('financial_activities', 't1.activity_id','=','financial_activities.id')
                    ->leftJoin('financial_programmes', 't1.programme_id','=','financial_programmes.id')
                    ->leftJoin('financial_dollar_backing_sheets', 't1.backing_sheet_id','=','financial_dollar_backing_sheets.id')
                    ->select(DB::raw("financial_activities.activity_name,financial_programmes.programme_name,
                        financial_dollar_backing_sheets.payee as backing_payee,t1.*")
                );
            }
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function uploadFinancialReceipts(Request $request){
        try {
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

    public function setSelectedDate(Request $request){
        try{
            $params = array(
                'current_date' => $request->input('asAtDate'),
                'created_by' => Auth::user()->id
            );
            $qryId = DB::table('financial_report_dates')->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Details Saved Successfully'
            );
        }catch (\Exception $e){
             $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }
       
    public function getDollarFiscalYearData(){
        try{
            $logged_in_user = Auth::user()->id;
            $qryDate = DB::table('financial_report_dates as t1')
                ->select('t1.current_date')
                ->where('t1.created_by',$logged_in_user)
                ->orderBy('id', 'DESC')->first();        
            $asAtDate = $qryDate->current_date;          
            $res = json_decode(
                json_encode(
                    DB::table('financial_fiscal_year as t1')
                        ->select('t1.*')
                        ->whereDate('t1.year_from','<=', $asAtDate)
                        ->whereDate('t1.year_to','>=', $asAtDate)
                        ->where('t1.account_name',1)
                        ->get()->toArray()
                ),true
            );
            $currentDate = Carbon::parse($asAtDate);
            $curDateFormated = $currentDate->format('d/m/Y');
            $res[0]['as_at_date'] = $asAtDate;     
            $res[0]['current_date'] = $curDateFormated;
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }
       
    public function getBOZFiscalYearData(){
        try{
            $logged_in_user = Auth::user()->id;
            $qryDate = DB::table('financial_report_dates as t1')
                ->select('t1.current_date')
                ->where('t1.created_by',$logged_in_user)
                ->orderBy('id', 'DESC')->first();        
            $asAtDate = $qryDate->current_date;          
            $res = json_decode(
                json_encode(
                    DB::table('financial_fiscal_year as t1')
                        ->select('t1.*')
                        ->whereDate('t1.year_from','<=', $asAtDate)
                        ->whereDate('t1.year_to','>=', $asAtDate)
                        ->where('t1.account_name',2)
                        ->get()->toArray()
                ),true
            );
            $currentDate = Carbon::parse($asAtDate);
            $curDateFormated = $currentDate->format('d/m/Y');
            $res[0]['as_at_date'] = $asAtDate;     
            $res[0]['current_date'] = $curDateFormated;
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }
       
    public function getFiscalYearData(){
        try{
            $logged_in_user = Auth::user()->id;
            $qryDate = DB::table('financial_report_dates as t1')
                ->select('t1.current_date')
                ->where('t1.created_by',$logged_in_user)
                ->orderBy('id', 'DESC')->first();        
            $asAtDate = $qryDate->current_date;          
            $res = json_decode(
                json_encode(
                    DB::table('financial_fiscal_year as t1')
                        ->select('t1.*')
                        ->whereDate('t1.year_from','<=', $asAtDate)
                        ->whereDate('t1.year_to','>=', $asAtDate)
                        ->where('t1.account_name',3)
                        ->get()->toArray()
                ),true
            );
            $currentDate = Carbon::parse($asAtDate);
            $curDateFormated = $currentDate->format('d/m/Y');
            $res[0]['as_at_date'] = $asAtDate;     
            $res[0]['current_date'] = $curDateFormated;
        }catch (\Exception $e){
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getFiscalYears(Request $request)
    {
        $table_name = $request->input('table_name');
        $sub_category = $request->input('sub_category');
        $filters = $request->input('filters');
        $filters = (array)json_decode($filters);
        try {
            $qry = DB::table($table_name);
            if (count((array)$filters) > 0) {
                $qry->where($filters);
            }
            if (isset($sub_category) && $sub_category > 0) {
                $qry->where('account_name', $sub_category);
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

    public function getAccountOpeningBalances(Request $request)
    {
        $table_name = $request->input('table_name');//here
        $sub_category = $request->input('sub_category');
        $filters = $request->input('filters');
        $filters = (array)json_decode($filters);
        try {
            $qry = DB::table($table_name.' as t1')
                    ->leftJoin('financial_kgs_bank_accounts as t2', 't1.account_name','=','t2.id')
                    ->select(DB::raw('t1.*,t2.name as account_description'));
            if (count((array)$filters) > 0) {
                $qry->where($filters);
            }
            $qry->get();
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

    public function getReconData($as_at_date,$year_from,$year_to,$table_name){
        try{
            $logged_in_user = Auth::user()->id;
            $table = $table_name . ' as t1';        
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_debits'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getStalePayments($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;
            $table = $table_name . ' as t1';
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.is_stale', 1)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getExpenditure($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;   
            $table = $table_name . ' as t1';      
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_expenditure'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.is_stale','<>', 1)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getBankCharges($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id; 
            $table = $table_name . ' as t1';        
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as bank_charges'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 4)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleOne($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id; 
            $table = $table_name . ' as t1';        
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleOne'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 1)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleTwo($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;   
            $table = $table_name . ' as t1';      
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleTwo'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 2)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleThree($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id; 
            $table = $table_name . ' as t1';        
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleThree'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 3)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleFour($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;
            $table = $table_name . ' as t1';         
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleFour'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 4)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleFive($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;
            $table = $table_name . ' as t1';         
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleFive'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 5)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }

    public function getScheduleSix($as_at_date,$year_from,$year_to,$table_name){ 
        try{
            $logged_in_user = Auth::user()->id;
            $table = $table_name . ' as t1';
            $res = json_decode(
                json_encode(
                    DB::table($table)
                        ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as scheduleSix'))
                        ->whereDate('t1.receipt_date','>=', $year_from)
                        ->whereDate('t1.receipt_date','<=', $as_at_date)
                        ->where('t1.schedule_type', 6)
                        ->get()->toArray()
                ),true
            );
        }catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return $res;
    }
    
    public function generateFinancialReport(Request $request){
        try{
            $quarter = $request->input('quarter');  
            $category = $request->input('category'); 
            $sub_category = $request->input('sub_category');
            
            // if($category == 8 || $category == 9){
            //     $approval_array = array();
            // } else {
            $approval_array = array(
                'asAtDate' => $request->input('asAtDate'), 
                'fiscal_year' => $request->input('fiscal_year'), 
                'prepared_by' => $request->input('prepared_by'), 
                'prep_desig' => $request->input('prep_desig'), 
                'checked_by' => $request->input('checked_by'),
                'checked_by_desig' => $request->input('checked_by_desig'), 
                'authorsed_by' => $request->input('authorsed_by'), 
                'auth_desig' => $request->input('auth_desig'),
                'audited_by' => $request->input('audited_by'), 
                'audit_desig'  => $request->input('audit_desig'), 
                'selected_year_rate'  => $request->input('selected_year_rate'), 
                'prev_year_rate'  => $request->input('prev_year_rate')
            );
            // }

            if($category == 1){//cashbook
                if($sub_category == 1) {//here
                    $this->generateDollarCashbook($quarter,$category,$sub_category,$approval_array);
                } else if($sub_category == 2) {
                    $this->generateBOZCashbook($quarter,$category,$sub_category,$approval_array);
                } else if($sub_category == 3) {
                    $this->generateZanacoCashbook($quarter,$category,$sub_category,$approval_array);
                }
            } else if($category == 2) {//Bank Reconciliation schedule
                if($sub_category == 1) {
                    $this->generateDollarReconciliationSchedule('financial_dollar_cashbook_details',$approval_array);
                } else if($sub_category == 2) {
                    $this->generateBozReconciliationSchedule('financial_boz_cashbook_details',$approval_array);
                } else if($sub_category == 3) {
                    $this->generateZanacoReconciliationSchedule('financial_cashbook_details',$approval_array);
                }
            } else if($category == 3) {//Bank Reconciliation      
                if($sub_category == 1) {
                    $this->generateDollarBankReconciliation($approval_array);
                } else if($sub_category == 2) {
                    $this->generateBozBankReconciliation($approval_array);
                }if($sub_category == 3) {
                    $this->generateZanacoBankReconciliation($approval_array);
                } else {
                    $this->generateBankReconciliation($approval_array);
                } 
            } else if($category == 4) {
                $this->generateBudgetVsExpenditure($quarter,$category,$sub_category,$approval_array);
            } else if($category == 5) {   
                $this->generateBudgetVsActualExpenditure($quarter,$category,$sub_category,$approval_array);               
            } else if($category == 6) {
                /* if($quarter == 1) {
                    $report = generateJasperReport('finance/FinBankReconciliation', 'FinBankReconciliation' . time(), 'pdf');
                } else if($quarter == 2) {
                    $report = generateJasperReport('finance/FinBankReconciliation', 'FinBankReconciliation' . time(), 'pdf');
                } else if($quarter == 3) {
                    $report = generateJasperReport('finance/FinBankReconciliation', 'FinBankReconciliation' . time(), 'pdf');
                } else if($quarter == 4) {
                    $report = generateJasperReport('finance/FinBankReconciliation', 'FinBankReconciliation' . time(), 'pdf');
                } else {
               } */
            }  else if($category == 8) {   
                $this->generateDetailExpenditureReport($quarter,$category,$sub_category,$approval_array);               
            }  else if($category == 9) {   
                $this->generateReportByCategory($quarter,$category,$sub_category,$approval_array);               
            } else {
                echo "<p style='text-align: center;color: red'>Report Not Configured!!</p>";
                exit();
            }
        }catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            print_r($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            print_r($res);
        }
    }

    public function generateDollarCashbook($quarter,$category,$sub_category,$approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Dollar Cash Book');
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 10, 10, true);
        // PDF::SetFooterMargin(PDF_MARGIN_FOOTER);
        PDF::AddPage("Landscape !");
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 10);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 20, 'PNG', '', 'T', true, 250, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        //PDF::SetY(40);                
        PDF::SetFont('times', '', 8);              
        $fiscal_yr_data = $this->getDollarFiscalYearData();
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_dollar_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $date_now = Carbon::now()->format('d/m/Y');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');

        $back_qry = DB::table('financial_dollar_backing_sheets as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $decrypted_bk_dtls = decryptArray(convertStdClassObjToArray($back_qry));
        $qry = DB::table('financial_dollar_cashbook_details as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $decrypted_cashbk_dtls = decryptArray(convertStdClassObjToArray($qry));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS                		                             </td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> DOLLAR BANK ACCOUNT                                            		         </td></tr>
                <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"></td></tr> 
                <tr><td style="text-align:center"colspan="8">  <b> DOLLAR CASH BOOK FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b>	         </td></tr>  
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td style="text-align:center"><b>DATE</b></td><td> <b>PAYEE</b></td><td colspan="2"> <b>DETAILS</b></td><td style="text-align:center"> <b>CHQ/B/TRF NO</b></td>
                <td style="text-align:center"> <b>RECEIPTS(Dr)</b></td><td style="text-align:center"> <b>PAYMENTS(Cr)</b></td><td style="text-align:center"> <b>BALANCE</b></td></tr>  
                <tr><td colspan="5"></td><td style="text-align:center"> <b>$</b></td><td style="text-align:center"> <b>$</b></td><td style="text-align:center"> <b>$</b></td></tr>  
                <tr><td style="text-align:center">'.$parsed_year_from.'</td><td> OPENING BALANCE</td><td colspan="5"> </td><td style="text-align:right"> '.$this->formatMoney($openning_bal).' </td></tr>                
            ';
        if($decrypted_bk_dtls){
            foreach ($decrypted_bk_dtls as $key => $bk_dtls) {
                // $balAt[$key] = ($bk_dtls["receipts"] + $openning_bal);
                $backing_sheet_id = $bk_dtls["id"];
                $receipt_date = Carbon::parse($bk_dtls["receipt_date"]);
                $curDateFormated = $receipt_date->format('d/m/Y');    
                $htmlTable .= '<tr>
                    <td style="text-align:center"> '.$curDateFormated.'</td>
                    <td> '.$bk_dtls["payee"].' </td>
                    <td colspan="2"> '.$bk_dtls["details"].'</td>
                    <td style="text-align:center"> '.$bk_dtls["cheque_no"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["receipts"]).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["payments"]).'</td>
                    <td style="text-align:right"> </td>
                </tr>';
                foreach ($decrypted_cashbk_dtls as $key => $cashbk_dtls) {
                    if($cashbk_dtls["backing_sheet_id"] == $backing_sheet_id){            
                        $receipt_date = Carbon::parse($cashbk_dtls["receipt_date"]);
                        $curDateFormated = $receipt_date->format('d/m/Y');  
                        $htmlTable .= '
                        <tr>
                            <td style="text-align:center"> '.$curDateFormated.'</td>
                            <td> '.$cashbk_dtls["payee"].' </td>
                            <td colspan="2"> '.$cashbk_dtls["payments_description"].'</td>
                            <td style="text-align:center"> '.$cashbk_dtls["cheque_no"].'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["receipts"]).'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["payments"]).'</td>
                            <td style="text-align:center">  </td>
                        </tr>';
                    }
                }
            }
        }
        $total_receivables_qry = DB::table('financial_dollar_backing_sheets as t1')                   
            ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_debits'))
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date);
        $decrypted_receivables_qry = decryptArray(convertStdClassObjToArray($total_receivables_qry->get()));
        $receivables = $decrypted_receivables_qry[0]['total_debits'] ? $decrypted_receivables_qry[0]['total_debits'] : 0;

        $total_payables_qry = DB::table('financial_dollar_cashbook_details as t1')
            ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits'))
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date);        
        $decrypted_payables_qry = decryptArray(convertStdClassObjToArray($total_payables_qry->get()));
        $payables = $decrypted_payables_qry[0]['total_credits'] ? $decrypted_payables_qry[0]['total_credits'] : 0;
        $openning_bal = is_numeric($openning_bal) ? $openning_bal : 0;
        $receivables = is_numeric($receivables) ? $receivables : 0;
        $payables = is_numeric($payables) ? $payables : 0;
        $balance = ($openning_bal + $receivables) - $payables;
        $htmlTable .= '
                <tr>
                    <td>     </td><td>     </td><td>     </td><td>     </td><td>     </td>
                    <td style="text-align:right"> '.$this->formatMoney($receivables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($payables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
                <tr>
                    <td>     </td>
                    <td colspan="6">   <b>CLOSING CASHBOOK BALANCE</b> </td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
            </tbody>
        </table>'; 

        $htmlTable3 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "70%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTable3);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateBOZCashbook($quarter,$category,$sub_category,$approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('BOZ Cash Book');
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 10, 10, true);
        // PDF::SetFooterMargin(PDF_MARGIN_FOOTER);
        PDF::AddPage("Landscape !");

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 10);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 20, 'PNG', '', 'T', true, 250, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();         
        PDF::SetFont('times', '', 8);
        $fiscal_yr_data = $this->getBOZFiscalYearData();

        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
   
        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_boz_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');

        $back_qry = DB::table('financial_boz_backing_sheets as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $back_sheet_details = $back_qry->get();
        $converted_bk_dtls = convertStdClassObjToArray($back_sheet_details);
        $decrypted_bk_dtls = decryptArray($converted_bk_dtls);

        $qry = DB::table('financial_boz_cashbook_details as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date)
            ->where('t1.schedule_type', 0);

        $cashbook_details = $qry->get();
        $converted_cashbk_dtls = convertStdClassObjToArray($cashbook_details);
        $decrypted_cashbk_dtls = decryptArray($converted_cashbk_dtls);
        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
                <thead></thead>
                <tbody>            
                    <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS                		                             </td></tr>
                    <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> BANK OF ZAMBIA                                            		         </td></tr>
                    <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"> 0393785304817                          		         </td></tr> 
                    <tr><td style="text-align:center"colspan="8">  <b> BANK OF ZAMBIA CASH BOOK FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b>	         </td></tr>  
                    <tr><td style="text-align:center"colspan="8"></td></tr>            
                    <tr><td style="text-align:center"><b>DATE</b></td><td> <b>PAYEE</b></td><td colspan="2"> <b>DETAILS</b></td><td style="text-align:center"> <b>CHQ/B/TRF NO</b></td>
                    <td style="text-align:center"> <b>RECEIPTS(Dr)</b></td><td style="text-align:center"> <b>PAYMENTS(Cr)</b></td><td style="text-align:center"> <b>BALANCE</b></td></tr>  
                    <tr><td colspan="5"></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td></tr>  
                    <tr><td style="text-align:center">'.$parsed_year_from.'</td><td> OPENING BALANCE</td><td colspan="5"> </td><td style="text-align:right"> '.$this->formatMoney($openning_bal).' </td></tr>                
            ';

            foreach ($decrypted_bk_dtls as $key => $bk_dtls) {
                // $balAt[$key] = ($bk_dtls["receipts"] + $openning_bal);
                $backing_sheet_id = $bk_dtls["id"];
                $receipt_date = Carbon::parse($bk_dtls["receipt_date"]);
                $curDateFormated = $receipt_date->format('d/m/Y');    
                $htmlTable .= '
                <tr>
                    <td style="text-align:center"> '.$curDateFormated.'</td>
                    <td> '.$bk_dtls["payee"].' </td>
                    <td colspan="2"> '.$bk_dtls["details"].'</td>
                    <td style="text-align:center"> '.$bk_dtls["cheque_no"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["receipts"]).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["payments"]).'</td>
                    <td style="text-align:right"> </td>
                </tr>';
                foreach ($decrypted_cashbk_dtls as $key => $cashbk_dtls) {  
                    if($cashbk_dtls["backing_sheet_id"] == $backing_sheet_id){            
                        $receipt_date = Carbon::parse($cashbk_dtls["receipt_date"]);
                        $curDateFormated = $receipt_date->format('d/m/Y');        
                        $htmlTable .= '
                        <tr>
                            <td style="text-align:center"> '.$curDateFormated.'</td>
                            <td> '.$cashbk_dtls["payee"].' </td>
                            <td colspan="2"> '.$cashbk_dtls["payments_description"].'</td>
                            <td style="text-align:center"> '.$cashbk_dtls["cheque_no"].'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["receipts"]).'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["payments"]).'</td>
                            <td style="text-align:center">  </td>
                        </tr>';
                    }      
                }
            }
     
        $total_receivables_qry = DB::table('financial_boz_backing_sheets as t1')                   
            ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_debits'))
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $receivables_qry = $total_receivables_qry->get();
        $converted_receivables_qry = convertStdClassObjToArray($receivables_qry);
        $decrypted_receivables_qry = decryptArray($converted_receivables_qry);

        $total_payables_qry = DB::table('financial_boz_cashbook_details as t1')
            ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits'))
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $payables_qry = $total_payables_qry->get();
        $converted_payables_qry = convertStdClassObjToArray($payables_qry);
        $decrypted_payables_qry = decryptArray($converted_payables_qry);
        
        $receivables = $decrypted_receivables_qry[0]['total_debits'];
        $payables = $decrypted_payables_qry[0]['total_credits'];
        $openning_bal = is_numeric($openning_bal) ? $openning_bal : 0;
        $receivables = is_numeric($receivables) ? $receivables : 0;
        $payables = is_numeric($payables) ? $payables : 0;
        $balance = ($openning_bal + $receivables) - $payables;
        $htmlTable .= '
                <tr>
                    <td>     </td><td>     </td><td>     </td><td>     </td><td>     </td>
                    <td style="text-align:right"> '.$this->formatMoney($receivables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($payables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
                <tr>
                    <td>     </td>
                    <td colspan="6">   <b>CLOSING CASHBOOK BALANCE</b> </td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
            </tbody>
        </table>'; 

        $htmlTable3 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "70%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTable3);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateZanacoCashbook($quarter,$category,$sub_category,$approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('ZANACO Cash Book');
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetMargins(10, 10, 10, true);
        // PDF::SetFooterMargin(PDF_MARGIN_FOOTER);
        PDF::AddPage("Landscape !");

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 10);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 20, 'PNG', '', 'T', true, 250, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();         
        PDF::SetFont('times', '', 8);     
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');

        $back_qry = DB::table('financial_backing_sheets as t1')
            ->select('t1.*')
            // ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','>=', $at_date->firstOfMonth())
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $back_sheet_details = $back_qry->get();
        $converted_bk_dtls = convertStdClassObjToArray($back_sheet_details);
        $decrypted_bk_dtls = decryptArray($converted_bk_dtls);

        $qry = DB::table('financial_cashbook_details as t1')
            ->select('t1.*')
            // ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','>=', $at_date->firstOfMonth())
            ->whereDate('t1.receipt_date','<=', $as_at_date)
            ->where('t1.schedule_type', 0);

        $cashbook_details = $qry->get();
        $converted_cashbk_dtls = convertStdClassObjToArray($cashbook_details);
        $decrypted_cashbk_dtls = decryptArray($converted_cashbk_dtls);

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
            <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS                		                             </td></tr>
            <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
            <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> ZANACO                                            		         </td></tr>
            <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
            <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"> 0393785304817                          		         </td></tr> 
            <tr><td style="text-align:center"colspan="8">  <b> ZANACO CASH BOOK FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b>	         </td></tr>  
            <tr><td style="text-align:center"colspan="8"></td></tr>            
            <tr><td style="text-align:center"><b>DATE</b></td><td> <b>PAYEE</b></td><td colspan="2"> <b>DETAILS</b></td><td style="text-align:center"> <b>CHQ/B/TRF NO</b></td>
            <td style="text-align:center"> <b>RECEIPTS(Dr)</b></td><td style="text-align:center"> <b>PAYMENTS(Cr)</b></td><td style="text-align:center"> <b>BALANCE</b></td></tr>  
            <tr><td colspan="5"></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td></tr>  
            <tr><td style="text-align:center">'.$parsed_year_from.'</td><td> OPENING BALANCE</td><td colspan="5"> </td><td style="text-align:right"> '.$openning_bal.' </td></tr>                
            ';

            foreach ($decrypted_bk_dtls as $key => $bk_dtls) {
                // $balAt[$key] = ($bk_dtls["receipts"] + $openning_bal);
                $backing_sheet_id = $bk_dtls["id"];
                $receipt_date = Carbon::parse($bk_dtls["receipt_date"]);
                $curDateFormated = $receipt_date->format('d/m/Y');   
                $htmlTable .= '<tr>
                    <td style="text-align:center"> '.$curDateFormated.'</td>
                    <td> '.$bk_dtls["payee"].' </td>
                    <td colspan="2"> '.$bk_dtls["details"].'</td>
                    <td style="text-align:center"> '.$bk_dtls["cheque_no"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["receipts"]).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["payments"]).'</td>
                    <td style="text-align:right"> </td>
                </tr>';
                foreach ($decrypted_cashbk_dtls as $key => $cashbk_dtls) {  
                    if($cashbk_dtls["backing_sheet_id"] == $backing_sheet_id){            
                        $receipt_date = Carbon::parse($cashbk_dtls["receipt_date"]);
                        $curDateFormated = $receipt_date->format('d/m/Y');        
                        $htmlTable .= '<tr>
                            <td style="text-align:center"> '.$curDateFormated.'</td>
                            <td> '.$cashbk_dtls["payee"].' </td>
                            <td colspan="2"> '.$cashbk_dtls["payments_description"].'</td>
                            <td style="text-align:center"> '.$cashbk_dtls["cheque_no"].'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["receipts"]).'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["payments"]).'</td>
                            <td style="text-align:center">  </td>
                        </tr>';
                    }      
                }
            }
     
        $total_receivables_qry = DB::table('financial_backing_sheets as t1')                   
            ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_debits'))
            // ->whereDate('t1.receipt_date','>=', $year]_from)
            ->whereDate('t1.receipt_date','>=', $at_date->firstOfMonth())
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $receivables_qry = $total_receivables_qry->get();
        $converted_receivables_qry = convertStdClassObjToArray($receivables_qry);
        $decrypted_receivables_qry = decryptArray($converted_receivables_qry);

        $total_payables_qry = DB::table('financial_cashbook_details as t1')
            ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits'))
            // ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','>=', $at_date->firstOfMonth())
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $payables_qry = $total_payables_qry->get();
        $converted_payables_qry = convertStdClassObjToArray($payables_qry);
        $decrypted_payables_qry = decryptArray($converted_payables_qry);
        
        $receivables = $decrypted_receivables_qry[0]['total_debits'];
        $payables = $decrypted_payables_qry[0]['total_credits'];
        $openning_bal = is_numeric($openning_bal) ? $openning_bal : 0;
        $receivables = is_numeric($receivables) ? $receivables : 0;
        $payables = is_numeric($payables) ? $payables : 0;
        $balance = ($openning_bal + $receivables) - $payables;
        $htmlTable .= '
                <tr>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td style="text-align:right"> '.$this->formatMoney($receivables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($payables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
                <tr>
                    <td>     </td>
                    <td colspan="6">   <b>CLOSING CASHBOOK BALANCE</b> </td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
            </tbody>
        </table>'; 

        $total_activities_qry = DB::table('financial_cashbook_details as t1')
            ->leftJoin('financial_activities', 't1.activity_id','=','financial_activities.id')
            ->leftJoin('financial_programmes', 't1.programme_id','=','financial_programmes.id')
            ->leftJoin('financial_backing_sheets', 't1.backing_sheet_id','=','financial_backing_sheets.id')
            ->select(DB::raw("financial_activities.activity_name,financial_programmes.programme_name,
                financial_backing_sheets.payee as backing_payee,t1.work_plan_ref,t1.programme_id,t1.activity_id,
                SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits"
            ))
            // ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','>=', $at_date->firstOfMonth())
            ->whereDate('t1.receipt_date','<=', $as_at_date)
            ->groupBy('t1.activity_id');

        $activities_qry = $total_activities_qry->get();
        $converted_activities_qry = convertStdClassObjToArray($activities_qry);
        $decrypted_activities_qry = decryptArray($converted_activities_qry);
        
        $htmlTable2 = '
        <br><br><br><br>
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
                <tbody>            
                    <tr><td width="20%"> <b>PAYEE</b></td><td width="35%"> <b>ACTIVITY</b></td><td width="35%"> <b>PROGRAMME</b></td><td width="10%"> <b>AMOUNTS</b></td></tr>
               ';
            foreach ($decrypted_activities_qry as $key => $decrypted_activities) {      
                $htmlTable2 .= '<tr>
                    <td> '.$decrypted_activities["backing_payee"].' </td>
                    <td> '.$decrypted_activities["activity_name"].'</td>
                    <td> '.$decrypted_activities["programme_name"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($decrypted_activities["total_credits"]).'</td>
                </tr>';
            }
        $htmlTable2 .= '
            </tbody>
        </table>'; 

        $htmlTable3 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "70%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTable2);
        PDF::writeHTML($htmlTable3);
        PDF::Output('ZanacoCashBook' . $asAtDateFormated . '.pdf', 'I');
    }

    public function generateBankReconciliation($approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::AddPage();
        // PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 10, 10, true);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetY(40);                
        PDF::SetFont('times', '', 11);   
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }

        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $date_now = Carbon::now()->format('d/m/Y');
           
        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;
                
        $fiscal_yr_openning_bal = is_numeric($fiscal_yr_data[0]['openning_bal']) ? $fiscal_yr_data[0]['openning_bal'] : 0;
        $cashbook_data_total_debits = is_numeric($cashbook_data[0]['total_debits']) ? $cashbook_data[0]['total_debits'] : 0;
        $stale_payments_total_credits = is_numeric($stale_payments[0]['total_credits']) ? $stale_payments[0]['total_credits'] : 0;
        $total_expenditure = is_numeric($expenditure[0]['total_expenditure']) ? $expenditure[0]['total_expenditure'] : 0;
        $bankCharges = is_numeric($bank_charges[0]['bank_charges']) ? $bank_charges[0]['bank_charges'] : 0;

        $initial_sub_total = $fiscal_yr_openning_bal + $cashbook_data_total_debits + $stale_payments_total_credits;
        $exp_plus_bnk_charg = ($total_expenditure + $bankCharges);
        $sub_totals = $initial_sub_total - $exp_plus_bnk_charg;        
        $balance_as_per_statement = ($sub_totals + $schedule_one + $schedule_five) - $schedule_two;

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr><td style="text-align:center" colspan="3"><b>BANK RECONCILIATION FOR THE MONTH ENDING '.$fiscal_yr_data[0]['current_date'].'</b>               		                 </td></tr>
                    <tr><td>STATION</td><td colspan="2"> HEAD QUARTERS                		                             </td></tr>
                    <tr><td>ACCOUNT NAME</td><td colspan="2"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td>BANK</td><td colspan="2"> ZANACO                                            		         </td></tr>
                    <tr><td>BRANCH</td><td colspan="2"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td>ACCOUNT NUMBER</td><td colspan="2"> 0393785304817                          		         </td></tr>   
                    <tr><td style="text-align:center"colspan="3">    <b>BANK RECONCILIATION STATEMENT AS AT : '.$fiscal_yr_data[0]['current_date'].'</b></td></tr> 
                    <tr><td colspan="2">    1. Opening cash book balance 				                 </td><td style="text-align:right">  '.$this->formatMoney($fiscal_yr_data[0]['openning_bal']).'   </td></tr>                        
                    <tr><td colspan="2">    2. Add:Receipts		                 		                 </td><td style="text-align:right">  '.$this->formatMoney($cashbook_data[0]['total_debits']).'   </td></tr>
                    <tr><td colspan="2">    Add back stale cheques 		                                 </td><td style="text-align:right">  '.$this->formatMoney($stale_payments[0]['total_credits']).'</td></tr>
                    <tr><td colspan="2">    3. Sub total		                                         </td><td style="text-align:right">  '.$this->formatMoney($initial_sub_total).' </td></tr>                        
                    <tr><td colspan="2">    4. Less: expenditure during the month plus bank charges		 </td><td style="text-align:right">  '.$this->formatMoney($exp_plus_bnk_charg).'   </td></tr>                        
                    <tr><td colspan="2">    5. Closing cash book balance				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'   </td></tr>                        
                    <tr><td colspan="2">    CASH BOOK ADJUSTMENTS		                                 </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">    6.Add: (a) Bank interest		 -                           </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">           (b) Other Income not in cash book  (see schedule 5)	 </td><td style="text-align:right">               </td></tr>	                        
                    <tr><td colspan="2">    7. Subtotal                                                  </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    8. (c). Less :charges,ledger fees,transfer charges etc	 -   </td><td style="text-align:right">	             </td></tr>
                    <tr><td colspan="2">       (d). Less :Previously un applied		                     </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    9. ADJUSTED CASH BOOK BALANCE 				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    10.Add: Unpresented EFTAs (see schedule I)			         </td><td style="text-align:right">  '.$this->formatMoney($schedule_one).'    </td></tr>
                    <tr><td colspan="2">    11.Add Unapplied Funds - Schedule V				             </td><td style="text-align:right">  '.$this->formatMoney($schedule_five).' </td></tr>
                    <tr><td colspan="2">    11.Less: Uncredited lodgements(see attached schedule II) 	 </td><td style="text-align:right">  '.$this->formatMoney($schedule_two).'   </td></tr>                        
                    <tr><td colspan="2">    12.(a) Add adjustments ie errors on statement		 -       </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">       (b) Less adjustments ie errors on statement - Schedule VI-</td><td style="text-align:right">  '.$this->formatMoney($schedule_six).' </td></tr>
                    <tr><td colspan="2">       (c) Less adjustments ie errors on statement	 -   	 	 </td><td style="text-align:right">                     </td></tr>                        
                    <tr><td colspan="2">    13.BALANCE AS PER BANK STATEMENT				             </td><td style="text-align:right">  '.$this->formatMoney($balance_as_per_statement).'   </td></tr>             
                </tbody>
            </table>';      
                   
        $htmlTable2 = '
                <style>
                    table {
                        border-collapse: collapse;
                        white-space:nowrap;
                    }
                    table, th, td {
                        border: 0px white;
                    }
                </style>
                <table border="1" cellpadding="2" align="left" width = "100%">
                    <thead></thead>
                    <tbody>
                        <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                        <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                        <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                        <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                        <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                        <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                        <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                        <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                    </tbody>
                </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::SetY(230);
        PDF::writeHTML($htmlTable2);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateDollarBankReconciliation($approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();      
        PDF::SetFont('times', '', 11);

        $fiscal_yr_data = $this->getDollarFiscalYearData();
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $date_now = Carbon::now()->format('d/m/Y');

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_dollar_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_dollar_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $fiscal_yr_openning_bal = is_numeric($fiscal_yr_data[0]['openning_bal']) ? $fiscal_yr_data[0]['openning_bal'] : 0;
        $cashbook_data_total_debits = is_numeric($cashbook_data[0]['total_debits']) ? $cashbook_data[0]['total_debits'] : 0;
        $stale_payments_total_credits = is_numeric($stale_payments[0]['total_credits']) ? $stale_payments[0]['total_credits'] : 0;
        $total_expenditure = is_numeric($expenditure[0]['total_expenditure']) ? $expenditure[0]['total_expenditure'] : 0;
        $bankCharges = is_numeric($bank_charges[0]['bank_charges']) ? $bank_charges[0]['bank_charges'] : 0;

        $initial_sub_total = $fiscal_yr_openning_bal + $cashbook_data_total_debits + $stale_payments_total_credits;
        $exp_plus_bnk_charg = ($total_expenditure + $bankCharges);
        $sub_totals = $initial_sub_total - $exp_plus_bnk_charg;
        $balance_as_per_statement = ($sub_totals + $schedule_one + $schedule_five) - $schedule_two;

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr><td style="text-align:center" colspan="3"><b>BANK RECONCILIATION FOR THE MONTH ENDING '.$fiscal_yr_data[0]['current_date'].'</b>               		                 </td></tr>
                    <tr><td>STATION</td><td colspan="2"> HEAD QUARTERS                		                             </td></tr>
                    <tr><td>ACCOUNT NAME</td><td colspan="2"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td>BANK</td><td colspan="2"> DOLLAR BANK                                            		         </td></tr>
                    <tr><td>BRANCH</td><td colspan="2"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td>ACCOUNT NUMBER</td><td colspan="2">                          		         </td></tr>   
                    <tr><td style="text-align:center"colspan="3">    <b>BANK RECONCILIATION STATEMENT AS AT : '.$fiscal_yr_data[0]['current_date'].'</b></td></tr> 
                    <tr><td colspan="2">    1. Opening cash book balance 				                 </td><td style="text-align:right">  '.$this->formatMoney($fiscal_yr_data[0]['openning_bal']).'   </td></tr>                        
                    <tr><td colspan="2">    2. Add:Receipts		                 		                 </td><td style="text-align:right">  '.$this->formatMoney($cashbook_data[0]['total_debits']).'   </td></tr>
                    <tr><td colspan="2">    Add back stale cheques 		                                 </td><td style="text-align:right">  '.$this->formatMoney($stale_payments[0]['total_credits']).'</td></tr>
                    <tr><td colspan="2">    3. Sub total		                                         </td><td style="text-align:right">  '.$this->formatMoney($initial_sub_total).' </td></tr>                        
                    <tr><td colspan="2">    4. Less: expenditure during the month plus bank charges		 </td><td style="text-align:right">  '.$this->formatMoney($exp_plus_bnk_charg).'   </td></tr>                        
                    <tr><td colspan="2">    5. Closing cash book balance				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'   </td></tr>                        
                    <tr><td colspan="2">    CASH BOOK ADJUSTMENTS		                                 </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">    6.Add: (a) Bank interest		 -                           </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">           (b) Other Income not in cash book  (see schedule 5)	 </td><td style="text-align:right">               </td></tr>	                        
                    <tr><td colspan="2">    7. Subtotal                                                  </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    8. (c). Less :charges,ledger fees,transfer charges etc	 -   </td><td style="text-align:right">	             </td></tr>
                    <tr><td colspan="2">       (d). Less :Previously un applied		                     </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    9. ADJUSTED CASH BOOK BALANCE 				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    10.Add: Unpresented EFTAs (see schedule I)			         </td><td style="text-align:right">  '.$this->formatMoney($schedule_one).'    </td></tr>
                    <tr><td colspan="2">    11.Add Unapplied Funds - Schedule V				             </td><td style="text-align:right">  '.$this->formatMoney($schedule_five).' </td></tr>
                    <tr><td colspan="2">    11.Less: Uncredited lodgements(see attached schedule II) 	 </td><td style="text-align:right">  '.$this->formatMoney($schedule_two).'   </td></tr>                        
                    <tr><td colspan="2">    12.(a) Add adjustments ie errors on statement		 -       </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">       (b) Less adjustments ie errors on statement - Schedule VI-</td><td style="text-align:right">  '.$this->formatMoney($schedule_six).' </td></tr>
                    <tr><td colspan="2">       (c) Less adjustments ie errors on statement	 -   	 	 </td><td style="text-align:right">                     </td></tr>                        
                    <tr><td colspan="2">    13.BALANCE AS PER BANK STATEMENT				             </td><td style="text-align:right">  '.$this->formatMoney($balance_as_per_statement).'   </td></tr>             
                </tbody>
            </table>';      
        
        $htmlTable2 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
                
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::SetY(230);
        PDF::writeHTML($htmlTable2);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateBozBankReconciliation($approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();      
        PDF::SetFont('times', '', 11);

        $fiscal_yr_data = $this->getBOZFiscalYearData();
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $date_now = Carbon::now()->format('d/m/Y');

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_boz_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_boz_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $fiscal_yr_openning_bal = is_numeric($fiscal_yr_data[0]['openning_bal']) ? $fiscal_yr_data[0]['openning_bal'] : 0;
        $cashbook_data_total_debits = is_numeric($cashbook_data[0]['total_debits']) ? $cashbook_data[0]['total_debits'] : 0;
        $stale_payments_total_credits = is_numeric($stale_payments[0]['total_credits']) ? $stale_payments[0]['total_credits'] : 0;
        $total_expenditure = is_numeric($expenditure[0]['total_expenditure']) ? $expenditure[0]['total_expenditure'] : 0;
        $bankCharges = is_numeric($bank_charges[0]['bank_charges']) ? $bank_charges[0]['bank_charges'] : 0;

        $initial_sub_total = $fiscal_yr_openning_bal + $cashbook_data_total_debits + $stale_payments_total_credits;
        $exp_plus_bnk_charg = ($total_expenditure + $bankCharges);
        $sub_totals = $initial_sub_total - $exp_plus_bnk_charg;
        $balance_as_per_statement = ($sub_totals + $schedule_one + $schedule_five) - $schedule_two;

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr><td style="text-align:center" colspan="3"><b>BANK RECONCILIATION FOR THE MONTH ENDING '.$fiscal_yr_data[0]['current_date'].'</b>               		                 </td></tr>
                    <tr><td>STATION</td><td colspan="2"> HEAD QUARTERS                		                             </td></tr>
                    <tr><td>ACCOUNT NAME</td><td colspan="2"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td>BANK</td><td colspan="2"> BANK OF ZAMBIA                                            		         </td></tr>
                    <tr><td>BRANCH</td><td colspan="2"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td>ACCOUNT NUMBER</td><td colspan="2">                          		         </td></tr>   
                    <tr><td style="text-align:center"colspan="3">    <b>BANK RECONCILIATION STATEMENT AS AT : '.$fiscal_yr_data[0]['current_date'].'</b></td></tr> 
                    <tr><td colspan="2">    1. Opening cash book balance 				                 </td><td style="text-align:right">  '.$this->formatMoney($fiscal_yr_data[0]['openning_bal']).'   </td></tr>                        
                    <tr><td colspan="2">    2. Add:Receipts		                 		                 </td><td style="text-align:right">  '.$this->formatMoney($cashbook_data[0]['total_debits']).'   </td></tr>
                    <tr><td colspan="2">    Add back stale cheques 		                                 </td><td style="text-align:right">  '.$this->formatMoney($stale_payments[0]['total_credits']).'</td></tr>
                    <tr><td colspan="2">    3. Sub total		                                         </td><td style="text-align:right">  '.$this->formatMoney($initial_sub_total).' </td></tr>                        
                    <tr><td colspan="2">    4. Less: expenditure during the month plus bank charges		 </td><td style="text-align:right">  '.$this->formatMoney($exp_plus_bnk_charg).'   </td></tr>                        
                    <tr><td colspan="2">    5. Closing cash book balance				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'   </td></tr>                        
                    <tr><td colspan="2">    CASH BOOK ADJUSTMENTS		                                 </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">    6.Add: (a) Bank interest		 -                           </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">           (b) Other Income not in cash book  (see schedule 5)	 </td><td style="text-align:right">               </td></tr>	                        
                    <tr><td colspan="2">    7. Subtotal                                                  </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    8. (c). Less :charges,ledger fees,transfer charges etc	 -   </td><td style="text-align:right">	             </td></tr>
                    <tr><td colspan="2">       (d). Less :Previously un applied		                     </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    9. ADJUSTED CASH BOOK BALANCE 				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    10.Add: Unpresented EFTAs (see schedule I)			         </td><td style="text-align:right">  '.$this->formatMoney($schedule_one).'    </td></tr>
                    <tr><td colspan="2">    11.Add Unapplied Funds - Schedule V				             </td><td style="text-align:right">  '.$this->formatMoney($schedule_five).' </td></tr>
                    <tr><td colspan="2">    11.Less: Uncredited lodgements(see attached schedule II) 	 </td><td style="text-align:right">  '.$this->formatMoney($schedule_two).'   </td></tr>                        
                    <tr><td colspan="2">    12.(a) Add adjustments ie errors on statement		 -       </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">       (b) Less adjustments ie errors on statement - Schedule VI-</td><td style="text-align:right">  '.$this->formatMoney($schedule_six).' </td></tr>
                    <tr><td colspan="2">       (c) Less adjustments ie errors on statement	 -   	 	 </td><td style="text-align:right">                     </td></tr>                        
                    <tr><td colspan="2">    13.BALANCE AS PER BANK STATEMENT				             </td><td style="text-align:right">  '.$this->formatMoney($balance_as_per_statement).'   </td></tr>             
                </tbody>
            </table>';      
        
        $htmlTable2 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
                
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::SetY(230);
        PDF::writeHTML($htmlTable2);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateZanacoBankReconciliation($approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();      
        PDF::SetFont('times', '', 11);

        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $date_now = Carbon::now()->format('d/m/Y');

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $fiscal_yr_openning_bal = is_numeric($fiscal_yr_data[0]['openning_bal']) ? $fiscal_yr_data[0]['openning_bal'] : 0;
        $cashbook_data_total_debits = is_numeric($cashbook_data[0]['total_debits']) ? $cashbook_data[0]['total_debits'] : 0;
        $stale_payments_total_credits = is_numeric($stale_payments[0]['total_credits']) ? $stale_payments[0]['total_credits'] : 0;
        $total_expenditure = is_numeric($expenditure[0]['total_expenditure']) ? $expenditure[0]['total_expenditure'] : 0;
        $bankCharges = is_numeric($bank_charges[0]['bank_charges']) ? $bank_charges[0]['bank_charges'] : 0;

        $initial_sub_total = $fiscal_yr_openning_bal + $cashbook_data_total_debits + $stale_payments_total_credits;
        $exp_plus_bnk_charg = ($total_expenditure + $bankCharges);
        $sub_totals = $initial_sub_total - $exp_plus_bnk_charg;
        $balance_as_per_statement = ($sub_totals + $schedule_one + $schedule_five) - $schedule_two;

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr><td style="text-align:center" colspan="3"><b>BANK RECONCILIATION FOR THE MONTH ENDING '.$fiscal_yr_data[0]['current_date'].'</b>               		                 </td></tr>
                    <tr><td>STATION</td><td colspan="2"> HEAD QUARTERS                		                             </td></tr>
                    <tr><td>ACCOUNT NAME</td><td colspan="2"> KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td>BANK</td><td colspan="2"> ZANACO BANK                                            		         </td></tr>
                    <tr><td>BRANCH</td><td colspan="2"> LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td>ACCOUNT NUMBER</td><td colspan="2"> 0393785304817 </td></tr>   
                    <tr><td style="text-align:center"colspan="3">    <b>BANK RECONCILIATION STATEMENT AS AT : '.$fiscal_yr_data[0]['current_date'].'</b></td></tr> 
                    <tr><td colspan="2">    1. Opening cash book balance 				                 </td><td style="text-align:right">  '.$this->formatMoney($fiscal_yr_data[0]['openning_bal']).'   </td></tr>                        
                    <tr><td colspan="2">    2. Add:Receipts		                 		                 </td><td style="text-align:right">  '.$this->formatMoney($cashbook_data[0]['total_debits']).'   </td></tr>
                    <tr><td colspan="2">    Add back stale cheques 		                                 </td><td style="text-align:right">  '.$this->formatMoney($stale_payments[0]['total_credits']).'</td></tr>
                    <tr><td colspan="2">    3. Sub total		                                         </td><td style="text-align:right">  '.$this->formatMoney($initial_sub_total).' </td></tr>                        
                    <tr><td colspan="2">    4. Less: expenditure during the month plus bank charges		 </td><td style="text-align:right">  '.$this->formatMoney($exp_plus_bnk_charg).'   </td></tr>                        
                    <tr><td colspan="2">    5. Closing cash book balance				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'   </td></tr>                        
                    <tr><td colspan="2">    CASH BOOK ADJUSTMENTS		                                 </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">    6.Add: (a) Bank interest		 -                           </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">           (b) Other Income not in cash book  (see schedule 5)	 </td><td style="text-align:right">               </td></tr>	                        
                    <tr><td colspan="2">    7. Subtotal                                                  </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    8. (c). Less :charges,ledger fees,transfer charges etc	 -   </td><td style="text-align:right">	             </td></tr>
                    <tr><td colspan="2">       (d). Less :Previously un applied		                     </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    9. ADJUSTED CASH BOOK BALANCE 				                 </td><td style="text-align:right">  '.$this->formatMoney($sub_totals).'  </td></tr>                        
                    <tr><td colspan="2">    10.Add: Unpresented EFTAs (see schedule I)			         </td><td style="text-align:right">  '.$this->formatMoney($schedule_one).'    </td></tr>
                    <tr><td colspan="2">    11.Add Unapplied Funds - Schedule V				             </td><td style="text-align:right">  '.$this->formatMoney($schedule_five).' </td></tr>
                    <tr><td colspan="2">    11.Less: Uncredited lodgements(see attached schedule II) 	 </td><td style="text-align:right">  '.$this->formatMoney($schedule_two).'   </td></tr>                        
                    <tr><td colspan="2">    12.(a) Add adjustments ie errors on statement		 -       </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">       (b) Less adjustments ie errors on statement - Schedule VI-</td><td style="text-align:right">  '.$this->formatMoney($schedule_six).' </td></tr>
                    <tr><td colspan="2">       (c) Less adjustments ie errors on statement	 -   	 	 </td><td style="text-align:right">                     </td></tr>                        
                    <tr><td colspan="2">    13.BALANCE AS PER BANK STATEMENT				             </td><td style="text-align:right">  '.$this->formatMoney($balance_as_per_statement).'   </td></tr>             
                </tbody>
            </table>';      
        
        $htmlTable2 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
                
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::SetY(230);
        PDF::writeHTML($htmlTable2);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateFinancialReconReport(){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        // PDF::SetTitle('Offer Letters');
        PDF::SetAutoPageBreak(TRUE, 10);//true sets it to on and 0 means margin is zero from sides
        //PDF::SetMargins($left,$top,$right = -1,$keepmargins = false)
        PDF::SetMargins(10, 5, 10, true);
        PDF::AddPage();

        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 2, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        //PDF::SetY(40);                
        PDF::SetFont('times', '', 11); 
        $date_now = Carbon::now()->format('d/m/Y');    
        
        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr><td style="text-align:center" colspan="3"><b>BANK RECONCILIATION FOR THE MONTH ENDING 28 FEBRUARY 2021</b>               		                 </td></tr>
                    <tr><td>STATION</td><td colspan="2">: HEAD QUARTERS                		                             </td></tr>
                    <tr><td>ACCOUNT NAME</td><td colspan="2">: KEEPING GIRLS IN SCHOOL PROJECT-KGS                       </td></tr>
                    <tr><td>BANK</td><td colspan="2">: ZANACO                                            		         </td></tr>
                    <tr><td>BRANCH</td><td colspan="2">: LUSAKA BUSINESS CENTRE                         		         </td></tr>
                    <tr><td>ACCOUNT NUMBER</td><td colspan="2">: 0393785304817                          		         </td></tr>   
                    <tr><td style="text-align:center"colspan="3">    <b>BANK RECONCILIATION STATEMENT AS AT : 28 FEBRUARY 2021</b>	                     </td></tr> 
                    <tr><td colspan="2">    1. Opening cash book balance 				                 </td><td style="text-align:right">  697,685.55   </td></tr>                        
                    <tr><td colspan="2">    2. Add:Receipts		                 		                 </td><td style="text-align:right">1,864,665.67   </td></tr>
                    <tr><td colspan="2">    Add back stale cheques 		                                 </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    3. Sub total		                                         </td><td style="text-align:right">2,562,351.22   </td></tr>                        
                    <tr><td colspan="2">    4. Less: expenditure during the month plus bank charges		 </td><td style="text-align:right">1,854,210.17   </td></tr>                        
                    <tr><td colspan="2">    5. Closing cash book balance				                 </td><td style="text-align:right">  708,141.05   </td></tr>                        
                    <tr><td colspan="2">    CASH BOOK ADJUSTMENTS		                                 </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">    6.Add: (a) Bank interest		 -                           </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">           (b) Other Income not in cash book  (see schedule 5)	 </td><td style="text-align:right">               </td></tr>	                        
                    <tr><td colspan="2">    7. Subtotal                                                  </td><td style="text-align:right">708,141.05     </td></tr>                        
                    <tr><td colspan="2">    8. (c). Less :charges,ledger fees,transfer charges etc	 -   </td><td style="text-align:right">	             </td></tr>
                    <tr><td colspan="2">       (d). Less :Previously un applied		                     </td><td style="text-align:right">               </td></tr>
                    <tr><td colspan="2">    9. ADJUSTED CASH BOOK BALANCE 				                 </td><td style="text-align:right"> 708,141.05    </td></tr>                        
                    <tr><td colspan="2">    10.Add: Unpresented EFTAs (see schedule I)			         </td><td style="text-align:right"> 213,424.88    </td></tr>
                    <tr><td colspan="2">    11.Add Unapplied Funds - Schedule V				             </td><td style="text-align:right"> 142,270.00    </td></tr>
                    <tr><td colspan="2">    11.Less: Uncredited lodgements(see attached schedule II) 	 </td><td style="text-align:right">(278,914.00)   </td></tr>                        
                    <tr><td colspan="2">    12.(a) Add adjustments ie errors on statement		 -       </td><td style="text-align:right">               </td></tr>                        
                    <tr><td colspan="2">       (b) Less adjustments ie errors on statement - Schedule VI-</td><td style="text-align:right">(166,297.00)   </td></tr>
                    <tr><td colspan="2">       (c) Less adjustments ie errors on statement	 -   	 	 </td><td style="text-align:right">  41,489.00    </td></tr>                        
                    <tr><td colspan="2">    13.BALANCE AS PER BANK STATEMENT				             </td><td style="text-align:right"> 660,113.93    </td></tr>             
                </tbody>
            </table>';    

        $htmlTable2 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "90%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::SetY(230);
        PDF::writeHTML($htmlTable2);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }

    public function generateDollarReconciliationSchedule($first_table, $approval_array){
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);
        
        $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getDollarFiscalYearData();
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,$first_table);
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,$first_table);
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,$first_table);
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,$first_table);

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $scheduleOne = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',1)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();            
        $scheduleOne = decryptArray(convertStdClassObjToArray($scheduleOne));
        $scheduleTwo = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',2)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleTwo = decryptArray(convertStdClassObjToArray($scheduleTwo));  
        $scheduleThree = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',3)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleThree = decryptArray(convertStdClassObjToArray($scheduleThree));
        $scheduleFour = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',4)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFour = decryptArray(convertStdClassObjToArray($scheduleFour));
        $scheduleFive = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',5)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFive = decryptArray(convertStdClassObjToArray($scheduleFive));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS</td></tr>
                <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> DOLLAR BANK ACCOUNT</td></tr>
                <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"></td></tr>
                <tr><td style="text-align:center"colspan="8">  <b> SCHEDULES FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b></td></tr>  
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td style="text-align:center"colspan="8">SCHEDULE I - UNPRESENTED PAYMENTS</td></tr>
                <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
                <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>
            ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTable .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTable .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
                    
        $htmlTableTwo = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE II - UNCREDITED LODGEMENTS</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
            <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableTwo .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableTwo .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

        $htmlTableThree = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE III - REFUNDS FROM SCHOOL</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableThree .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableThree .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableFour = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE IV - BANK CHARGES</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFour .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFour .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';   
        	         
        $htmlTableFive = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule V - Unapplied Funds (Dishonoured payments)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DETAILS</b></td><td colspan="3"> 
            <b>DOC NUMBER</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFive .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFive .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableSix = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule VI - Adjustments (Error)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>DEBIT</b></td><td style="text-align:center"> <b>CREDIT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableSix .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableSix .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        // PDF::SetFont('helvetica', '', 8);
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTableTwo);
        PDF::writeHTML($htmlTableThree);
        PDF::writeHTML($htmlTableFour);
        PDF::writeHTML($htmlTableFive);
        PDF::writeHTML($htmlTableSix);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    } 

    public function generateBozReconciliationSchedule($first_table, $approval_array){
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(40);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);
        
        $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getBOZFiscalYearData();
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,$first_table);
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,$first_table);
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,$first_table);
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,$first_table);

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $scheduleOne = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',1)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();            
        $scheduleOne = decryptArray(convertStdClassObjToArray($scheduleOne));
        $scheduleTwo = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',2)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleTwo = decryptArray(convertStdClassObjToArray($scheduleTwo));  
        $scheduleThree = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',3)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleThree = decryptArray(convertStdClassObjToArray($scheduleThree));
        $scheduleFour = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',4)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFour = decryptArray(convertStdClassObjToArray($scheduleFour));
        $scheduleFive = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',5)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFive = decryptArray(convertStdClassObjToArray($scheduleFive));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS</td></tr>
                <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> BANK OF ZAMBIA ACCOUNT</td></tr>
                <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"></td></tr>
                <tr><td style="text-align:center"colspan="8">  <b> SCHEDULES FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b></td></tr>  
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td style="text-align:center"colspan="8">SCHEDULE I - UNPRESENTED PAYMENTS</td></tr>
                <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
                <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>
            ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTable .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTable .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
                    
        $htmlTableTwo = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE II - UNCREDITED LODGEMENTS</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
            <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableTwo .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableTwo .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

        $htmlTableThree = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE III - REFUNDS FROM SCHOOL</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableThree .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableThree .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableFour = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE IV - BANK CHARGES</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFour .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFour .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';   
        	         
        $htmlTableFive = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule V - Unapplied Funds (Dishonoured payments)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DETAILS</b></td><td colspan="3"> 
            <b>DOC NUMBER</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFive .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFive .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableSix = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule VI - Adjustments (Error)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>DEBIT</b></td><td style="text-align:center"> <b>CREDIT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableSix .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableSix .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
        
        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        // PDF::SetFont('helvetica', '', 8);
        PDF::SetY(60);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTableTwo);
        PDF::writeHTML($htmlTableThree);
        PDF::writeHTML($htmlTableFour);
        PDF::writeHTML($htmlTableFive);
        PDF::writeHTML($htmlTableSix);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    } 

    public function generateZanacoReconciliationSchedule($first_table, $approval_array){
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);
        
        $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,$first_table);
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,$first_table);
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,$first_table);
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,$first_table);

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $scheduleOne = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',1)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();            
        $scheduleOne = decryptArray(convertStdClassObjToArray($scheduleOne));
        $scheduleTwo = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',2)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleTwo = decryptArray(convertStdClassObjToArray($scheduleTwo));  
        $scheduleThree = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',3)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleThree = decryptArray(convertStdClassObjToArray($scheduleThree));
        $scheduleFour = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',4)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFour = decryptArray(convertStdClassObjToArray($scheduleFour));
        $scheduleFive = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',5)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFive = decryptArray(convertStdClassObjToArray($scheduleFive));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS</td></tr>
                <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> ZANACO BANK ACCOUNT</td></tr>
                <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"> 0393785304817 </td></tr>
                <tr><td style="text-align:center"colspan="8">  <b> SCHEDULES FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b></td></tr>  
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td style="text-align:center"colspan="8">SCHEDULE I - UNPRESENTED PAYMENTS</td></tr>
                <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
                <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>
            ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTable .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTable .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
                    
        $htmlTableTwo = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE II - UNCREDITED LODGEMENTS</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
            <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableTwo .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableTwo .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

        $htmlTableThree = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE III - REFUNDS FROM SCHOOL</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableThree .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableThree .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableFour = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE IV - BANK CHARGES</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFour .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFour .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';   
        	         
        $htmlTableFive = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule V - Unapplied Funds (Dishonoured payments)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DETAILS</b></td><td colspan="3"> 
            <b>DOC NUMBER</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFive .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFive .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableSix = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule VI - Adjustments (Error)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>DEBIT</b></td><td style="text-align:center"> <b>CREDIT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableSix .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableSix .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        // PDF::SetFont('helvetica', '', 8);
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTableTwo);
        PDF::writeHTML($htmlTableThree);
        PDF::writeHTML($htmlTableFour);
        PDF::writeHTML($htmlTableFive);
        PDF::writeHTML($htmlTableSix);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    } 

    public function manualPromotionProcess(){
        // $year = date('Y');
        $year = 2021;
        $description = 'Beneficiary Grade Promotions for the Year ' . $year;
        $meta_params = array(
            'year' => $year,
            'description' => $description,
            'created_at' => Carbon::now()
        );
        $log_data = array(
            'process_type' => 'Beneficiary Grade Annual Promotions',
            'process_description' => 'Annual Beneficiaries Grade Promotion',//$this->description,
            'created_at' => Carbon::now()
        );
        $checker = DB::table('ben_annual_promotions')
            ->where('year', $year)
            ->count();
        if ($checker > 0) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Found another promotion entry for ' . $year;
            DB::table('auto_processes_logs')
                ->insert($log_data);
            print_r('Status: Failed');
            print_r('');
            print_r('Message: Found another promotion entry for ' . $year);
            exit();
        }
         //check for a missed year
        $max_year = DB::table('ben_annual_promotions')->max('year');
        $next_year = ($max_year + 1);
        if ($next_year != $year) {
            print_r('failed');
            exit();
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Promotion should be for the year ' . 
            $next_year . ', but trying to do promotion for ' . $year;
            DB::table('auto_processes_logs')
                ->insert($log_data);
            print_r('Status: Failed');
            print_r('');
            print_r('Message: Promotion should be for the year ' . $next_year . ', 
            but trying to do promotion for ' . $year);
            exit();
        }
        DB::transaction(function () use ($meta_params, $log_data, $year) {
            try {
                $prev_year = $year - 1;
                $promotion_id = DB::table('ben_annual_promotions')->insertGetId($meta_params);
                //gradeNines for Promotion
                $where = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 0
                );
                $grade_nines_main_qry = DB::table('beneficiary_information')
                    ->where($where);

                $grade_nines_qry = clone $grade_nines_main_qry;
                $grade_nines_qry->select(DB::raw("id as girl_id,$prev_year as prev_year,
                $year as promotion_year,'MIS Auto' as created_by"));
                $grade_nines = $grade_nines_qry->get();
                $grade_nines = convertStdClassObjToArray($grade_nines);
                $size = 100;
                $grade_nines_chunks = array_chunk($grade_nines, $size);
                foreach ($grade_nines_chunks as $grade_nines_chunk) {
                    DB::table('grade_nines_for_promotion')->insert($grade_nines_chunk);
                }

                $update_params = array(
                    'under_promotion' => 1,
                    'promotion_year' => $year
                );
                $grade_nines_update_qry = clone $grade_nines_main_qry;
                $grade_nines_update_qry->update($update_params);

                $promotion_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->get();
                $promotion_data = convertStdClassObjToArray($promotion_data);

                $grade_log_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->get();
                $grade_log_data = convertStdClassObjToArray($grade_log_data);

                //log grade 12 transitioning
                $to_stage = 4;
                $reason = "'Completed grade 12. Transition of " . $year."'";
                $grade12_log = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,enrollment_status as from_stage,$to_stage as to_stage,$reason as reason"))
                    ->where('enrollment_status', 1)
                    ->where('current_school_grade', 12)
                    ->get();
                $grade12_log = convertStdClassObjToArray($grade12_log);
                DB::table('beneficiaries_transitional_report')->insert($grade12_log);

                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->where('current_school_grade', 12)
                    ->update(array('enrollment_status' => 4));

                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->update(array('current_school_grade' => 
                    DB::raw('current_school_grade+1'), 
                    'last_annual_promo_date' => DB::raw('NOW()')));

                $promotion_chunks = array_chunk($promotion_data, $size);
                foreach ($promotion_chunks as $promotion_chunk) {
                    DB::table('ben_annual_promotion_details')->insert($promotion_chunk);
                }
                $grade_log_chunks = array_chunk($grade_log_data, $size);
                foreach ($grade_log_chunks as $grade_log_chunk) {
                    DB::table('beneficiary_grade_logs')->insert($grade_log_chunk);
                }

                $log_data['status'] = 'Successful';
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                print_r('Status: Successful');
                print_r('');
                print_r('Message: Promotion for ' . $year . ' executed successfully');
            } catch (\Exception $e) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $e->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $e->getMessage());
            } catch (\Throwable $throwable) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $throwable->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $throwable->getMessage());
            }
        }, 5);
        return;
    }

    public function manualPromotionProcessRollBack(){
        $initiail_year = date('Y');
        $year = $initiail_year - 1;
        $description = 'Beneficiary Grade Promotions Roll Back for the Year ' . $year;
        $meta_params = array(
            'year' => $year,
            'description' => $description,
            'created_at' => Carbon::now()
        );
        $log_data = array(
            'process_type' => 'Beneficiary Grade Annual Promotions RollBack',
            'process_description' => 'Annual Beneficiaries Grade Promotion RollBack',//$this->description,
            'created_at' => Carbon::now()
        );
        
        $max_year = DB::table('ben_annual_promotions')->max('year');
        $next_year = ($max_year + 1);
        DB::transaction(function () use ($meta_params, $log_data, $year) {
            try {
                $latest_date = DB::table('ben_annual_promotions')->orderBy('id','DESC')->first();
                $promotion_created_date = $latest_date->created_at;
                $prev_year = $year - 1;
                $promotion_id = 4;
                //gradeNines for Promotion
                $whereOne = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 1
                );
                $previous_grade_nines_main_qry = DB::table('beneficiary_information')->where($whereOne);                
                $where = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 0
                );
                // $new_grade_nines_main_qry = DB::table('beneficiary_information')->where($where);

                $grade_nines_qry = clone $previous_grade_nines_main_qry;

                //rolls back all promotions
                DB::table('ben_annual_promotion_details as t1')
                    ->join('beneficiary_information as t2', 't2.id', '=', 't1.girl_id')
                    ->where('t1.promotion_id', 4)
                    ->update(['t2.current_school_grade' => DB::raw('t1.from_grade')]);
                
                //rolls back grade 12s promotions
                $res = DB::table('temp_beneficiary_information as t1')
                    ->join('beneficiary_information as t2', 't2.id', '=', 't1.girl_id')
                    ->where('t2.enrollment_status', 4)
                    ->where('t2.current_school_grade', 12)
                    ->update(array('t2.enrollment_status' => 1));
                $delete_where = array(
                    'promotion_year' => $year,
                    'prev_year' => $prev_year
                );
                $res = DB::table('grade_nines_for_promotion')->where($delete_where)->delete();
                $update_params = array(
                    'under_promotion' => 0,
                    'promotion_year' => $prev_year
                );
                $grade_nines_update_qry = clone $previous_grade_nines_main_qry;
                $grade_nines_update_qry->update($update_params);

                $res = DB::table('ben_annual_promotion_details')->where('created_at','>=',$promotion_created_date)->delete();
                print_r('Status: Successful');
                print_r('');
                print_r('Message: Promotion Rollback for ' . $year . ' executed successfully');
            } catch (\Exception $e) {
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $e->getMessage());
            } catch (\Throwable $throwable) {
                print_r('Status: Failed');
                print_r('');
                print_r('Message: ' . $throwable->getMessage());
            }
        }, 5);
        return;
    }

    public function generateBudgetVsExpenditure($quarter,$category,$sub_category,$approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('BUDGET PERFORMANCE REPORT');
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetMargins(10, 10, 10, true);
        // PDF::SetFooterMargin(PDF_MARGIN_FOOTER);
        PDF::AddPage("Landscape !");
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 10);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 20, 'PNG', '', 'T', true, 250, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();         
        PDF::SetFont('times', '', 8);     
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }

        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];

        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $at_date = Carbon::parse($as_at_date);

        $date_as_at = $at_date->format('Y-m-d');
        $new_parsed_year_from = Carbon::parse($year_from)->format('Y-m-d');

        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $total_activities_qry = DB::table('budget_allocation')
            ->join('financial_programmes','budget_allocation.programme_id','=','financial_programmes.id')
            ->join('financial_activities','budget_allocation.activitiy_id','=','financial_activities.id')
            ->select('budget_allocation.*',
                'financial_programmes.programme_name','financial_programmes.programme_code',
                'financial_activities.activity_explanation','financial_activities.code as activity_code'
            )
            // ->whereDate('budget_allocation.date_from','>=', $year_from)
            ->whereDate('budget_allocation.date_from','<=', $date_as_at);

        $activities_qry = $total_activities_qry->get();
        $decrypted_activities_qry = decryptArray(convertStdClassObjToArray($activities_qry));
        
        $htmlTable2 = '
            <br><br>
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="4" align="left">
            <thead></thead>
            <tbody>
                <tr><td style="text-align:center"colspan="10"><b> BUDGET PERFORMANCE REPORT AS AT '.$asAtDateFormated.'</b></td></tr>
                <tr>
                    <td style="text-align:center" colspan="3" width="44%"></td>
                    <td style="text-align:center" colspan="2" width="16%"> <b>Budget</b></td>
                    <td style="text-align:left" colspan="2" width="16%"> <b>Actual Expenditure</b></td>
                    <td style="text-align:center" colspan="2" width="16%"> <b>Balance</b></td>
                    <td style="text-align:center" width="8%"> <b>Consumption (%)</b></td>
                </tr>
                <tr>
                    <td style="text-align:center" width="5%"> <b>Activity Code</b></td>
                    <td style="text-align:center" width="10%"> <b>Programme</b></td>
                    <td style="text-align:left" width="29%"> <b>Activity Description</b></td>
                    <td style="text-align:center" width="8%"> <b>Annual Budget (ZMW)</b></td>
                    <td style="text-align:center" width="8%"> <b>Annual Budget (USD)</b></td>                        
                    <td style="text-align:center" width="8%"> <b>Cummulative Expenditure as at '.$asAtDateFormated.' (ZMW)</b></td>
                    <td style="text-align:center" width="8%"> <b>Cummulative Expenditure as at '.$asAtDateFormated.' (USD)</b></td>
                    <td style="text-align:center" width="8%"> <b>Balance as at '.$asAtDateFormated.' (ZMW)</b></td>
                    <td style="text-align:center" width="8%"> <b>Balance as at '.$asAtDateFormated.' (USD)</b></td>
                    <td style="text-align:center" width="8%"> <b>Expenditure as Percentage of Budget (%)</b></td>
                </tr>
        ';

        foreach ($decrypted_activities_qry as $key => $decrypted_activities) {
            $budget_amount_zmw = $decrypted_activities["budget_amount_zmw"] ? $decrypted_activities["budget_amount_zmw"] : 0;
            $current_expense_zmw = $decrypted_activities["current_expense_zmw"] ? $decrypted_activities["current_expense_zmw"] : 0;

            if($budget_amount_zmw == 0 || $current_expense_zmw == 0) {
                $percentage_expenditure = '%';
            } else {
                $percentage = ($decrypted_activities["current_expense_zmw"] / $decrypted_activities["budget_amount_zmw"]) * 100;
                $percentage_expenditure = $this->formatMoney($percentage).'%';
            }

            $current_dollar_balance = $decrypted_activities["current_balance_dollar"] ? $decrypted_activities["current_balance_dollar"] : 0;
            $budget_dollar_amount = $decrypted_activities["budget_amount_dollar"] ? $decrypted_activities["budget_amount_dollar"] : 0;
            $current_balance = $decrypted_activities["current_balance"] ? $decrypted_activities["current_balance"] : 0;
            if($current_dollar_balance == 0 && $budget_dollar_amount > 0) {
                if($current_balance > 0) {
                    $current_dollar_balance = ($decrypted_activities["current_balance"] / $decrypted_activities["rate"]);
                }
            }
            
            $htmlTable2 .= '
            <tr>
                <td style="text-align:center" width="5%">'.$decrypted_activities["programme_code"].'</td>
                <td style="text-align:left" width="10%">'.$decrypted_activities["programme_name"].'</td>
                <td style="text-align:left" width="29%">'.$decrypted_activities["activity_explanation"].'</td>
                <td style="text-align:center" width="8%">'.$this->formatMoney($decrypted_activities["budget_amount_zmw"]).'</td>
                <td style="text-align:center" width="8%">'.$this->formatMoney($decrypted_activities["budget_amount_dollar"]).'</td>                        
                <td style="text-align:center" width="8%">'.$this->formatMoney($decrypted_activities["current_expense_zmw"]).'</td>
                <td style="text-align:center" width="8%">'.$this->formatMoney($decrypted_activities["current_expense_dollar"]).'</td>
                <td style="text-align:center" width="8%">'.$this->formatMoney($decrypted_activities["current_balance"]).'</td>
                <td style="text-align:center" width="8%">'.$this->formatMoney($current_dollar_balance).'</td>
                <td style="text-align:center" width="8%">'.$percentage_expenditure.'</td>
            </tr>';
        }
        $htmlTable2 .= '
            </tbody>
        </table>'; 

        $htmlTable3 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <br><br>
            <table border="0" cellpadding="2" align="left">
                <thead></thead>
                <tbody>
                    <tr>
                        <td width = "10%"><b>PREPARED BY</b></td>
                        <td width = "20%">'.$approval_array['prepared_by'].'</td>
                        <td width = "10%" style="text-align:right"><b>DESIGNATION</b></td>
                        <td>'.$approval_array['prep_desig'].'</td>
                        <td width = "10%" style="text-align:right"><b>SIGNATURE</b></td>
                        <td>............................................</td>
                        <td width = "10%" style="text-align:right"><b>DATE</b></td><td> '.$date_now.' </td>
                    </tr>                    
                    <tr>
                        <td><b>CHECKED BY</b></td><td>'.$approval_array['checked_by'].'</td>
                        <td style="text-align:right"><b>DESIGNATION</b></td>
                        <td>'.$approval_array['checked_by_desig'].'</td>
                        <td style="text-align:right"><b>SIGNATURE</b></td>
                        <td>............................................</td>
                        <td style="text-align:right"><b>DATE</b></td><td> '.$date_now.' </td>
                    </tr>                    
                    <tr>
                        <td><b>AUTHORISED BY</b></td><td>'.$approval_array['authorsed_by'].'</td>
                        <td style="text-align:right"><b>DESIGNATION</b></td>
                        <td>'.$approval_array['auth_desig'].'</td>
                        <td style="text-align:right"><b>SIGNATURE</b></td>
                        <td>............................................</td>
                        <td style="text-align:right"><b>DATE</b></td><td> '.$date_now.' </td>
                    </tr>                    
                    <tr>
                        <td><b>AUDITED BY</b></td><td>'.$approval_array['audited_by'].'</td>
                        <td style="text-align:right"><b>DESIGNATION</b></td>
                        <td>'.$approval_array['audit_desig'].'</td>
                        <td style="text-align:right"><b>SIGNATURE</b></td>
                        <td>............................................</td>
                        <td style="text-align:right"><b>DATE</b></td><td> '.$date_now.' </td>
                    </tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        // PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTable2);
        PDF::writeHTML($htmlTable3);
        PDF::Output('BudgetPerformanceReport' . $asAtDateFormated. '.pdf', 'I');
    }

    public function generateBudgetVsActualExpenditure($quarter,$category,$sub_category,$approval_array){
        //get the last ID//letter generation logging here
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetTitle('Budget Against Actual Expenditure');
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetMargins(10, 10, 10, true);
        // PDF::SetFooterMargin(PDF_MARGIN_FOOTER);
        PDF::AddPage("Landscape !");
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 10);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 20, 'PNG', '', 'T', true, 250, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();         
        PDF::SetFont('times', '', 8);     
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }

        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];

        $cashbook_data = $this->getReconData($as_at_date,$year_from,$year_to,'financial_backing_sheets');
        $stale_payments = $this->getStalePayments($as_at_date,$year_from,$year_to,'financial_cashbook_details');        
        $expenditure = $this->getExpenditure($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $bank_charges = $this->getBankCharges($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,'financial_cashbook_details');
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,'financial_cashbook_details');

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');

        $back_qry = DB::table('financial_backing_sheets as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $back_sheet_details = $back_qry->get();
        $converted_bk_dtls = convertStdClassObjToArray($back_sheet_details);
        $decrypted_bk_dtls = decryptArray($converted_bk_dtls);

        $qry = DB::table('financial_cashbook_details as t1')
            ->select('t1.*')
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date)
            ->where('t1.schedule_type', 0);

        $cashbook_details = $qry->get();
        $converted_cashbk_dtls = convertStdClassObjToArray($cashbook_details);
        $decrypted_cashbk_dtls = decryptArray($converted_cashbk_dtls);

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
            <tr><td style="text-align:center"colspan="8">  <b> ZANACO CASH BOOK FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b>	         </td></tr>  
            <tr><td style="text-align:center"colspan="8"></td></tr>            
            <tr><td style="text-align:center"><b>DATE</b></td><td> <b>PAYEE</b></td><td colspan="2"> <b>DETAILS</b></td><td style="text-align:center"> <b>CHQ/B/TRF NO</b></td>
            <td style="text-align:center"> <b>RECEIPTS(Dr)</b></td><td style="text-align:center"> <b>PAYMENTS(Cr)</b></td><td style="text-align:center"> <b>BALANCE</b></td></tr>  
            <tr><td colspan="5"></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td><td style="text-align:center"> <b>ZMW</b></td></tr>  
            <tr><td style="text-align:center">'.$parsed_year_from.'</td><td> OPENING BALANCE</td><td colspan="5"> </td><td style="text-align:right"> '.$openning_bal.' </td></tr>                
            ';

            foreach ($decrypted_bk_dtls as $key => $bk_dtls) {
                // $balAt[$key] = ($bk_dtls["receipts"] + $openning_bal);
                $backing_sheet_id = $bk_dtls["id"];
                $receipt_date = Carbon::parse($bk_dtls["receipt_date"]);
                $curDateFormated = $receipt_date->format('d/m/Y');   
                $htmlTable .= '<tr>
                    <td style="text-align:center"> '.$curDateFormated.'</td>
                    <td> '.$bk_dtls["payee"].' </td>
                    <td colspan="2"> '.$bk_dtls["details"].'</td>
                    <td style="text-align:center"> '.$bk_dtls["cheque_no"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["receipts"]).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($bk_dtls["payments"]).'</td>
                    <td style="text-align:right"> </td>
                </tr>';
                foreach ($decrypted_cashbk_dtls as $key => $cashbk_dtls) {  
                    if($cashbk_dtls["backing_sheet_id"] == $backing_sheet_id){            
                        $receipt_date = Carbon::parse($cashbk_dtls["receipt_date"]);
                        $curDateFormated = $receipt_date->format('d/m/Y');        
                        $htmlTable .= '<tr>
                            <td style="text-align:center"> '.$curDateFormated.'</td>
                            <td> '.$cashbk_dtls["payee"].' </td>
                            <td colspan="2"> '.$cashbk_dtls["payments_description"].'</td>
                            <td style="text-align:center"> '.$cashbk_dtls["cheque_no"].'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["receipts"]).'</td>
                            <td style="text-align:right"> '.$this->formatMoney($cashbk_dtls["payments"]).'</td>
                            <td style="text-align:center">  </td>
                        </tr>';
                    }      
                }
            }
     
        $total_receivables_qry = DB::table('financial_backing_sheets as t1')                   
            ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_debits'))
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $receivables_qry = $total_receivables_qry->get();
        $converted_receivables_qry = convertStdClassObjToArray($receivables_qry);
        $decrypted_receivables_qry = decryptArray($converted_receivables_qry);

        $total_payables_qry = DB::table('financial_cashbook_details as t1')
            ->select(DB::raw('SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits'))
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date);

        $payables_qry = $total_payables_qry->get();
        $converted_payables_qry = convertStdClassObjToArray($payables_qry);
        $decrypted_payables_qry = decryptArray($converted_payables_qry);
        
        $receivables = $decrypted_receivables_qry[0]['total_debits'];
        $payables = $decrypted_payables_qry[0]['total_credits'];
        $openning_bal = is_numeric($openning_bal) ? $openning_bal : 0;
        $receivables = is_numeric($receivables) ? $receivables : 0;
        $payables = is_numeric($payables) ? $payables : 0;
        $balance = ($openning_bal + $receivables) - $payables;
        $htmlTable .= '
                <tr>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td>     </td>
                    <td style="text-align:right"> '.$this->formatMoney($receivables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($payables).'</td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
                <tr>
                    <td>     </td>
                    <td colspan="6">   <b>CLOSING CASHBOOK BALANCE</b> </td>
                    <td style="text-align:right"> '.$this->formatMoney($balance).'</td>
                </tr>
            </tbody>
        </table>'; 

        $total_activities_qry = DB::table('financial_cashbook_details as t1')
            ->leftJoin('financial_activities', 't1.activity_id','=','financial_activities.id')
            ->leftJoin('financial_programmes', 't1.programme_id','=','financial_programmes.id')
            ->leftJoin('financial_backing_sheets', 't1.backing_sheet_id','=','financial_backing_sheets.id')
            ->select(DB::raw("financial_activities.activity_name,financial_programmes.programme_name,
                financial_backing_sheets.payee as backing_payee,t1.work_plan_ref,t1.programme_id,t1.activity_id,
                SUM(IF(t1.payments IS NULL,0,t1.payments)) as total_credits"
            ))
            ->whereDate('t1.receipt_date','>=', $year_from)
            ->whereDate('t1.receipt_date','<=', $as_at_date)
            ->groupBy('t1.activity_id');

        $activities_qry = $total_activities_qry->get();
        $converted_activities_qry = convertStdClassObjToArray($activities_qry);
        $decrypted_activities_qry = decryptArray($converted_activities_qry);
        
        $htmlTable2 = '
        <br><br><br><br>
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
                <tbody>            
                    <tr><td width="20%"> <b>PAYEE</b></td><td width="35%"> <b>ACTIVITY</b></td><td width="35%"> <b>PROGRAMME</b></td><td width="10%"> <b>AMOUNTS</b></td></tr>
               ';
            foreach ($decrypted_activities_qry as $key => $decrypted_activities) {      
                $htmlTable2 .= '<tr>
                    <td> '.$decrypted_activities["backing_payee"].' </td>
                    <td> '.$decrypted_activities["activity_name"].'</td>
                    <td> '.$decrypted_activities["programme_name"].'</td>
                    <td style="text-align:right"> '.$this->formatMoney($decrypted_activities["total_credits"]).'</td>
                </tr>';
            }
        $htmlTable2 .= '
            </tbody>
        </table>'; 

        $htmlTable3 = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "70%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTable2);
        PDF::writeHTML($htmlTable3);
        PDF::Output('filename_' . time() . '.pdf', 'I');
    }
    
    public function generateDetailExpenditureReport($quarter,$category,$sub_category,$approval_array) {
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);        
        // $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $selected_year_rate = $approval_array['selected_year_rate']; 
        $prev_year_rate = $approval_array['prev_year_rate'];

        $init_ida_receipts = json_decode(
            json_encode(
                DB::table('financial_dollar_backing_sheets as t1')
                    ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_dollar_receipts'))
                    // ->whereDate('t1.receipt_date','>=', $year_from)
                    // ->whereDate('t1.receipt_date','<=', $as_at_date)
                    ->get()->toArray()
            ),true
        );

        if($init_ida_receipts[0]['total_dollar_receipts']) {
            $ida_dollars = $this->formatMoney($init_ida_receipts[0]['total_dollar_receipts']);
            $ida_receipts_dollars = $init_ida_receipts[0]['total_dollar_receipts'];
            $ida_receipts_kwacha = $ida_receipts_dollars * $selected_year_rate;
            $ida_receipts = $this->formatMoney($ida_receipts_kwacha);
        } else {
            $ida_receipts = '0.00';
        }

        // Personal Emoluments (Code 210000 to 219999)		
        // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)		
        // Financial Charges (Code 226083)		
        // Productivty Grants (Code 225040)	
        // Fixed Assets (Codes 310000 to 329999)

        // Personal Emoluments (Code 210000 to 219999)
        $init_emoluments = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select('t2.*','t1.gl_acc_code')           
            ->whereBetween('t1.gl_acc_code', [210000, 219999])->get();
       //     ->whereDate('t1.receipt_date','>=', $year_from_new)
       //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $emoluments = decryptArray(convertStdClassObjToArray($init_emoluments));

        // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)
        $init_goods_services = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select('t2.*','t1.gl_acc_code')           
            ->whereBetween('t1.gl_acc_code', [220000, 269999])->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $goods_services = decryptArray(convertStdClassObjToArray($init_goods_services));
		
        // Financial Charges (Code 226083)		
        $init_fin_charges = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select('t2.*','t1.gl_acc_code')           
            ->where('t1.gl_acc_code', 226083)->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $fin_charges = decryptArray(convertStdClassObjToArray($init_fin_charges));
	
        // Productivity Grants (Code 225040)	
        $init_productivity = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select('t2.*','t1.gl_acc_code')             
            ->where('t1.gl_acc_code', 226083)->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $productivity = decryptArray(convertStdClassObjToArray($init_productivity));

        // Fixed Assets (Codes 310000 to 329999)
        $init_fixed_assets = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select('t2.*','t1.gl_acc_code')           
            ->whereBetween('t1.gl_acc_code', [310000, 329999])->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $fixed_assets = decryptArray(convertStdClassObjToArray($init_fixed_assets));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="8"> <b>KEEPING GIRLS IN SCHOOL PROJECT</b></td></tr>
                <tr><td colspan="8"> <b>DETAILED REPORT BY EXPENSE</b></td></tr>
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td colspan="4" style="text-align:left"> <b>RECEIPTS</b></td>
                <td style="text-align:center"> <b>2022<br>KWACHA<br>ZMW</b></td><td style="text-align:center"><b>2022<br>DOLLAR<br>USD</b></td>
                <td style="text-align:center"> <b>2021<br>KWACHA<br>ZMW</b></td><td style="text-align:center"> <b>2021<br>DOLLAR<br>USD</b></td></tr>
            ';
        $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"><b>IDA Receipts</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">' . $ida_receipts . '</td>
                    <td style="text-align:right">'.$ida_dollars.'</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"><b>Other Receipts</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="8" style="text-align:left"><b>Less Expenditure</b></td>
                </tr>
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Personal Emoluments</b></td>
                </tr>';
                if($emoluments) {
                    foreach ($emoluments as $key => $emoluments_dtls) {
                        $emol_payments = $emoluments_dtls["payments"] ? $this->formatMoney($emoluments_dtls["payments"]) : '0.00';
                        $emol_tasks = $emoluments_dtls["tasks"] ? $emoluments_dtls["tasks"] : ' ';
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">'.$emol_tasks.'</td>
                            <td style="text-align:right">'.$emol_payments.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                    }
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }                
                $htmlTable .= '
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Goods and Services</b></td>
                </tr>';
                if($goods_services) {
                    foreach ($goods_services as $key => $goods_services_dtls) {
                        $goods_services_payments = $goods_services_dtls["payments"] ? $this->formatMoney($goods_services_dtls["payments"]) : '0.00';
                        $goods_services_tasks = $goods_services_dtls["tasks"] ? $goods_services_dtls["tasks"] : ' ';
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">'.$goods_services_tasks.'</td>
                            <td style="text-align:right">'.$goods_services_payments.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                    }
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }                
                $htmlTable .= '
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Financial Charges</b></td>
                </tr>';                
                if($fin_charges) {
                    foreach ($fin_charges as $key => $fin_charges_dtls) {
                        $fin_charges_payments = $this->formatMoney($fin_charges_dtls["payments"]) ? $this->formatMoney($fin_charges_dtls["payments"]) : '0.00';
                        $fin_charges_tasks = $fin_charges_dtls["tasks"] ? $this->formatMoney($fin_charges_dtls["tasks"]) : ' ';
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">'.$fin_charges_tasks.'</td>
                            <td style="text-align:right">'.$fin_charges_payments.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                    }
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }  
                
                $htmlTable .= '
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Productivity Grants</b></td>
                </tr>';                               
                if($productivity) {
                    foreach ($productivity as $key => $productivity_dtls) {
                        $productivity_payments = $this->formatMoney($productivity_dtls["payments"]) ? $this->formatMoney($productivity_dtls["payments"]) : '0.00';
                        $productivity_tasks = $productivity_dtls["tasks"] ? $productivity_dtls["tasks"] : ' ';
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">'.$productivity_tasks.'</td>
                            <td style="text-align:right">'.$productivity_payments.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                    }
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }
                
                $htmlTable .= '
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Fixed Assets</b></td>
                </tr>';                                             
                if($fixed_assets) {
                    foreach ($fixed_assets as $key => $fixed_assets_dtls) {
                        $fixed_assets_payments = $fixed_assets_dtls["payments"] ? $this->formatMoney($fixed_assets_dtls["payments"]) : '0.00';
                        $fixed_assets_tasks = $fixed_assets_dtls["tasks"] ? $fixed_assets_dtls["tasks"] : ' ';
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">'.$fixed_assets_tasks.'</td>
                            <td style="text-align:right">'.$fixed_assets_payments.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                    }
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }
                
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> <b>Total Expenditure</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
                $htmlTable .= '
                <tr>
                    <td colspan="8" style="text-align:left"> <b>Excess of receipts over expenditure (Total receipts minus expenditure)</b></td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
            </tbody>
        </table>';
        
        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        // PDF::SetFont('helvetica', '', 8);
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        // PDF::writeHTML($htmlTableTwo);
        // PDF::writeHTML($htmlTableThree);
        // PDF::writeHTML($htmlTableFour);
        // PDF::writeHTML($htmlTableFive);
        // PDF::writeHTML($htmlTableSix);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('DetailedExpenditureReport' . time() . '.pdf', 'I');
    } 
    
    public function generateReportByCategory($quarter,$category,$sub_category,$approval_array) {
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);
        
        // $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $selected_year_rate = $approval_array['selected_year_rate']; 
        $prev_year_rate = $approval_array['prev_year_rate'];

        $init_ida_receipts = json_decode(
            json_encode(
                DB::table('financial_dollar_backing_sheets as t1')
                    ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_dollar_receipts'))
                    // ->whereDate('t1.receipt_date','>=', $year_from)
                    // ->whereDate('t1.receipt_date','<=', $as_at_date)
                    ->get()->toArray()
            ),true
        );

        if($init_ida_receipts) {
            $ida_dollars = $this->formatMoney($init_ida_receipts[0]['total_dollar_receipts']);
            $ida_receipts_dollars = $init_ida_receipts[0]['total_dollar_receipts'];
            $ida_receipts_kwacha = $ida_receipts_dollars * $selected_year_rate;
            $ida_receipts = $this->formatMoney($ida_receipts_kwacha);
        } else {
            $ida_receipts = '0.00';
        }
        // Personal Emoluments (Code 210000 to 219999)		
        // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)		
        // Financial Charges (Code 226083)		
        // Productivty Grants (Code 225040)	
        // Fixed Assets (Codes 310000 to 329999)

        // Personal Emoluments (Code 210000 to 219999)
        $init_emoluments = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))           
            ->whereBetween('t1.gl_acc_code', [210000, 219999])->get();
       //     ->whereDate('t1.receipt_date','>=', $year_from_new)
       //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $emoluments = decryptArray(convertStdClassObjToArray($init_emoluments));        
        if($emoluments) {
            $init_emoluments = $emoluments[0]['total_emolments'];
            $emoluments = $this->formatMoney($init_emoluments);
            $calc_emolument_dollars = $init_emoluments/$selected_year_rate;
            $emolument_dollars = $this->formatMoney($calc_emolument_dollars);
        } else {
            $emoluments = '0.00';
            $emolument_dollars = '0.00';
        }
        // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)
        $init_goods_services = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
            ->whereBetween('t1.gl_acc_code', [220000, 269999])->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $goods_services = decryptArray(convertStdClassObjToArray($init_goods_services));
               
        if($goods_services[0]['total_emolments']) {
            $init_services = $goods_services[0]['total_emolments'];
            $goods_services = $this->formatMoney($init_services);
            $calc_services_dollars = $init_services/$selected_year_rate;
            $goods_services_dollars = $this->formatMoney($calc_services_dollars);
        } else {
            $goods_services = '0.00';
            $goods_services_dollars = '0.00';
        }
        // Financial Charges (Code 226083)		
        $init_fin_charges = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
            ->where('t1.gl_acc_code', 226083)->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $fin_charges = decryptArray(convertStdClassObjToArray($init_fin_charges));
            
        if($fin_charges[0]['total_emolments']) {
            $init_charges = $fin_charges[0]['total_emolments'];
            $fin_charges = $this->formatMoney($init_charges);
            $calc_charges_dollars = $init_charges/$selected_year_rate;
            $fin_charges_dollars = $this->formatMoney($calc_charges_dollars);
        } else {
            $fin_charges = '0.00';
            $fin_charges_dollars = '0.00';
        }

        // Productivity Grants (Code 225040)	
        $init_productivity = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
            ->where('t1.gl_acc_code', 226083)->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $productivity = decryptArray(convertStdClassObjToArray($init_productivity));
       
        if($productivity[0]['total_emolments']) {
            $init_productivity = $productivity[0]['total_emolments'];
            $productivity = $this->formatMoney($init_productivity);
            $calc_productivity_dollars = $init_productivity/$selected_year_rate;
            $productivity_dollars = $this->formatMoney($calc_productivity_dollars);
        } else {
            $productivity = '0.00';
            $productivity_dollars = '0.00';
        }

        // Fixed Assets (Codes 310000 to 329999)
        $init_fixed_assets = DB::table('financial_chart_of_accounts as t1')
            ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
            ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
            ->whereBetween('t1.gl_acc_code', [310000, 329999])->get();
        //     ->whereDate('t1.receipt_date','>=', $year_from_new)
        //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $fixed_assets = decryptArray(convertStdClassObjToArray($init_fixed_assets));
       
        if($fixed_assets[0]['total_emolments']) {
            $init_fixed_assets = $fixed_assets[0]['total_emolments'];
            $fixed_assets = $this->formatMoney($init_fixed_assets);
            $calc_fixed_assets_dollars = $init_fixed_assets/$selected_year_rate;
            $fixed_assets_dollars = $this->formatMoney($calc_fixed_assets_dollars);
        } else {
            $fixed_assets = '0.00';
            $fixed_assets_dollars = '0.00';
        }

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="8"> <b>KEEPING GIRLS IN SCHOOL PROJECT</b></td></tr>
                <tr><td colspan="8"> <b>STATEMENT OF CASH RECEIPTS AND EXPENDITURE FOR THE QUARTER/YEAR ENDED '.$asAtDateFormated.'</b></td></tr>
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td colspan="4" style="text-align:left"> <b>RECEIPTS</b></td>
                <td style="text-align:center"> <b>2022<br>KWACHA<br>ZMW</b></td><td style="text-align:center"><b>2022<br>DOLLAR<br>USD</b></td>
                <td style="text-align:center"> <b>2021<br>KWACHA<br>ZMW</b></td><td style="text-align:center"> <b>2021<br>DOLLAR<br>USD</b></td></tr>
            ';
            
        $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"><b>IDA Credit and Other Receipts</b></td>
                    <td style="text-align:right">'.$ida_receipts.'</td><td style="text-align:right">'.$ida_dollars.'</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"><b>TOTAL RECEIPTS</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="8" style="text-align:left"> <b>PAYMENTS</b></td>
                </tr>';
                if($emoluments) {
                    $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">Personnel Emoluments</td>                            
                            <td style="text-align:right">'.$emoluments.'</td>
                            <td style="text-align:right">'.$emolument_dollars.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Personnel Emoluments</td> 
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }
                if($goods_services) {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Goods and Services</td>
                        <td style="text-align:right">'.$goods_services.'</td>
                        <td style="text-align:right">'.$goods_services_dollars.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Goods and Services</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }               
                              
                if($fin_charges) {
                        $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">Financial Charges</td>
                            <td style="text-align:right">'.$fin_charges.'</td>
                            <td style="text-align:right">'.$fin_charges_dollars.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Financial Charges</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }  
                                          
                if($fixed_assets) {
                    $htmlTable .= '
                        <tr>
                            <td colspan="4" style="text-align:left">Fixed Assets Acquisition</td>
                            <td style="text-align:right">'.$fixed_assets.'</td>
                            <td style="text-align:right">'.$fixed_assets_dollars.'</td>
                            <td style="text-align:right">0.00</td>
                            <td style="text-align:right">0.00</td>
                        </tr>';
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Fixed Assets Acquisition</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }                               
                if($productivity) {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Production Grants (School fees and other requisites for school girls)</td>
                        <td style="text-align:right">'.$productivity.'</td>
                        <td style="text-align:right">'.$productivity_dollars.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                } else {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left"> </td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    </tr>';
                }             
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> <b>TOTAL PAYMENTS</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"> <b>Increase/(Decrease) in Cash</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"> <b>Foreign Exchange Losses</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"> <b>Cash at beginning of the year</b></td>
                    <td style="text-align:right">'.$ida_receipts.'</td><td style="text-align:right">'.$ida_dollars.'</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align:left"> <b>Cash at the end of the year</b></td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>
            </tbody>
        </table>';
        
        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('DetailedExpenditureReport' . time() . '.pdf', 'I');
    }
    
    /* public function generateReportByCategory($quarter,$category,$sub_category,$approval_array) {
        $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
        PDF::SetMargins(10, 10, 10, true); 
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, 10);
        PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
        PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
        PDF::SetFont('times', 'B', 11);
        //headers
        $image_path = '\resources\images\kgs-logo.png';
        PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        PDF::SetY(30);
        PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
        PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
        PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
        PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
        PDF::SetY(2);
        PDF::ln(5);
        // Start clipping.
        PDF::SetFont('times', 'I', 9);
        PDF::StartTransform();
        PDF::SetFont('times', '', 11);
        
        $table_one = $first_table.' as t1';
        $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
        $openning_bal = $fiscal_yr_data[0]['openning_bal'];
        $current_date = $fiscal_yr_data[0]['current_date'];
        $as_at_date = $fiscal_yr_data[0]['as_at_date'];
        $year_from = $fiscal_yr_data[0]['year_from'];
        $year_to = $fiscal_yr_data[0]['year_to'];
        $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
        $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
        $at_date = Carbon::parse($as_at_date);
        $asAtDateFormated = $at_date->format('d/m/Y');
        $date_now = Carbon::now()->format('d/m/Y');
        
        $scheduleOne = $this->getScheduleOne($as_at_date,$year_from,$year_to,$first_table);
        $scheduleTwo = $this->getScheduleTwo($as_at_date,$year_from,$year_to,$first_table);
        $scheduleThree = $this->getScheduleThree($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFour = $this->getScheduleFour($as_at_date,$year_from,$year_to,$first_table);
        $scheduleFive = $this->getScheduleFive($as_at_date,$year_from,$year_to,$first_table);
        $scheduleSix = $this->getScheduleSix($as_at_date,$year_from,$year_to,$first_table);

        $schedule_one = $scheduleOne[0]['scheduleOne'] ? $scheduleOne[0]['scheduleOne'] : 0;
        $schedule_two = $scheduleTwo[0]['scheduleTwo'] ? $scheduleTwo[0]['scheduleTwo'] : 0;
        $schedule_three = $scheduleThree[0]['scheduleThree'] ? $scheduleOne[0]['scheduleThree'] : 0;
        $schedule_four = $scheduleFour[0]['scheduleFour'] ? $scheduleFour[0]['scheduleFour'] : 0;
        $schedule_five = $scheduleFive[0]['scheduleFive'] ? $scheduleOne[0]['scheduleFive'] : 0;
        $schedule_six = $scheduleSix[0]['scheduleSix'] ? $scheduleOne[0]['scheduleSix'] : 0;

        $scheduleOne = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',1)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();            
        $scheduleOne = decryptArray(convertStdClassObjToArray($scheduleOne));
        $scheduleTwo = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',2)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleTwo = decryptArray(convertStdClassObjToArray($scheduleTwo));  
        $scheduleThree = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',3)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleThree = decryptArray(convertStdClassObjToArray($scheduleThree));
        $scheduleFour = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',4)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFour = decryptArray(convertStdClassObjToArray($scheduleFour));
        $scheduleFive = DB::table($table_one)
            ->select('t1.*')->where('t1.schedule_type',5)
            ->whereDate('t1.receipt_date','>=', $year_from_new)
            ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
        $scheduleFive = decryptArray(convertStdClassObjToArray($scheduleFive));

        $htmlTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
            </style>
            <table border="0.25" cellpadding="0.5" align="left">
            <thead></thead>
            <tbody>
                <tr><td colspan="2"> <b>STATION</b></td><td colspan="6"> HEAD QUARTERS</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NAME</b></td><td colspan="6"> KEEPING GIRLS IN SCHOOL PROJECT-KGS</td></tr>
                <tr><td colspan="2"> <b>BANK</b></td><td colspan="6"> ZANACO BANK ACCOUNT</td></tr>
                <tr><td colspan="2"> <b>BRANCH</b></td><td colspan="6"> LUSAKA BUSINESS CENTRE</td></tr>
                <tr><td colspan="2"> <b>ACCOUNT NUMBER</b></td><td colspan="6"> 0393785304817 </td></tr>
                <tr><td style="text-align:center"colspan="8">  <b> SCHEDULES FOR THE MONTH OF '.$at_date->format('F').' '.$at_date->format('Y').'</b></td></tr>  
                <tr><td style="text-align:center"colspan="8"></td></tr>
                <tr><td style="text-align:center"colspan="8">SCHEDULE I - UNPRESENTED PAYMENTS</td></tr>
                <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
                <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>
            ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTable .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTable .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
                    
        $htmlTableTwo = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE II - UNCREDITED LODGEMENTS</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>PAYEE</b></td><td colspan="3"> 
            <b>DOC/EFTA No</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableTwo .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableTwo .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

        $htmlTableThree = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE III - REFUNDS FROM SCHOOL</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableThree .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableThree .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableFour = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">SCHEDULE IV - BANK CHARGES</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>SOURCE DOC</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFour .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFour .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';   
        	         
        $htmlTableFive = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule V - Unapplied Funds (Dishonoured payments)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DETAILS</b></td><td colspan="3"> 
            <b>DOC NUMBER</b></td><td style="text-align:center"> <b>AMOUNT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableFive .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableFive .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';

                    
        $htmlTableSix = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td style="text-align:center"colspan="8">Schedule VI - Adjustments (Error)</td></tr>
            <tr><td style="text-align:center"><b>DATE</b></td><td colspan="3"> <b>DESCRIPTION</b></td><td colspan="3"> 
            <b>DEBIT</b></td><td style="text-align:center"> <b>CREDIT</b></td></tr>  
        ';
        foreach ($scheduleOne as $key => $bk_dtls) {
            $receipt_date = Carbon::parse($bk_dtls["receipt_date"])->format('d/m/Y');
            $htmlTableSix .= '
            <tr>
                <td style="text-align:center"> '.$receipt_date.' </td>
                <td colspan="3"> '.$bk_dtls["payee"].' </td>
                <td colspan="3"> '.$bk_dtls["payments_description"].' </td>
                <td style="text-align:right"> '.$bk_dtls["payments"].' </td>
            </tr>';
        }
        $htmlTableSix .= '
                <tr>
                    <td></td><td colspan="6"><b>TOTAL</b> </td><td style="text-align:right"> '.$schedule_one.' </td>
                </tr>
            </tbody>
        </table>';
        $htmlFinalTable = '
            <style>
                table {
                    border-collapse: collapse;
                    white-space:nowrap;
                }
                table, th, td {
                    border: 0px white;
                }
            </style>
            <table border="1" cellpadding="2" align="left" width = "100%">
                <thead></thead>
                <tbody>
                    <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                    <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                    <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
                </tbody>
            </table>';
        // PDF::SetFont('helvetica', '', 8);
        PDF::SetY(50);
        PDF::writeHTML($htmlTable);
        PDF::writeHTML($htmlTableTwo);
        PDF::writeHTML($htmlTableThree);
        PDF::writeHTML($htmlTableFour);
        PDF::writeHTML($htmlTableFive);
        PDF::writeHTML($htmlTableSix);
        PDF::writeHTML($htmlFinalTable);
        PDF::Output('ReportByCategory' . time() . '.pdf', 'I');
    }  */
    //end frank
}



/* 
public function generateDetailExpenditureReport($quarter,$category,$sub_category,$approval_array) {
    $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
    PDF::SetMargins(10, 10, 10, true); 
    PDF::AddPage();
    PDF::SetAutoPageBreak(TRUE, 10);
    PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
    PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
    PDF::SetFont('times', 'B', 11);
    //headers
    $image_path = '\resources\images\kgs-logo.png';
    PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
    PDF::SetY(30);
    PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
    PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
    PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
    PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
    PDF::SetY(2);
    PDF::ln(5);
    // Start clipping.
    PDF::SetFont('times', 'I', 9);
    PDF::StartTransform();
    PDF::SetFont('times', '', 11);        
    // $table_one = $first_table.' as t1';
    $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
    $openning_bal = $fiscal_yr_data[0]['openning_bal'];
    $current_date = $fiscal_yr_data[0]['current_date'];
    $as_at_date = $fiscal_yr_data[0]['as_at_date'];
    $year_from = $fiscal_yr_data[0]['year_from'];
    $year_to = $fiscal_yr_data[0]['year_to'];
    $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
    $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
    $at_date = Carbon::parse($as_at_date);
    $asAtDateFormated = $at_date->format('d/m/Y');
    $date_now = Carbon::now()->format('d/m/Y');
    
    $selected_year_rate = $approval_array['selected_year_rate']; 
    $prev_year_rate = $approval_array['prev_year_rate'];

    $init_ida_receipts = json_decode(
        json_encode(
            DB::table('financial_dollar_backing_sheets as t1')
                ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_dollar_receipts'))
                // ->whereDate('t1.receipt_date','>=', $year_from)
                // ->whereDate('t1.receipt_date','<=', $as_at_date)
                ->get()->toArray()
        ),true
    );

    if($init_ida_receipts) {
        $ida_dollars = $this->formatMoney($init_ida_receipts[0]['total_dollar_receipts']);
        $ida_receipts_dollars = $init_ida_receipts[0]['total_dollar_receipts'];
        $ida_receipts_kwacha = $ida_receipts_dollars * $selected_year_rate;
        $ida_receipts = $this->formatMoney($ida_receipts_kwacha);
    } else {
        $ida_receipts = '0.00';
    }

    // Personal Emoluments (Code 210000 to 219999)		
    // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)		
    // Financial Charges (Code 226083)		
    // Productivty Grants (Code 225040)	
    // Fixed Assets (Codes 310000 to 329999)

    // Personal Emoluments (Code 210000 to 219999)
    $init_emoluments = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select('t2.*','t1.gl_acc_code')           
        ->whereBetween('t1.gl_acc_code', [210000, 219999])->get();
   //     ->whereDate('t1.receipt_date','>=', $year_from_new)
   //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $emoluments = decryptArray(convertStdClassObjToArray($init_emoluments));

    // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)
    $init_goods_services = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select('t2.*','t1.gl_acc_code')           
        ->whereBetween('t1.gl_acc_code', [220000, 269999])->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $goods_services = decryptArray(convertStdClassObjToArray($init_goods_services));
    
    // Financial Charges (Code 226083)		
    $init_fin_charges = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select('t2.*','t1.gl_acc_code')           
        ->where('t1.gl_acc_code', 226083)->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $fin_charges = decryptArray(convertStdClassObjToArray($init_fin_charges));

    // Productivity Grants (Code 225040)	
    $init_productivity = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select('t2.*','t1.gl_acc_code')             
        ->where('t1.gl_acc_code', 226083)->get();
//     ->whereDate('t1.receipt_date','>=', $year_from_new)
//     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $productivity = decryptArray(convertStdClassObjToArray($init_productivity));

    // Fixed Assets (Codes 310000 to 329999)
    $init_fixed_assets = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select('t2.*','t1.gl_acc_code')           
        ->whereBetween('t1.gl_acc_code', [310000, 329999])->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $fixed_assets = decryptArray(convertStdClassObjToArray($init_fixed_assets));

    $htmlTable = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td colspan="8"> <b>KEEPING GIRLS IN SCHOOL PROJECT</b></td></tr>
            <tr><td colspan="8"> <b>DETAILED REPORT BY EXPENSE</b></td></tr>
            <tr><td style="text-align:center"colspan="8"></td></tr>
            <tr><td colspan="4" style="text-align:left"> <b>RECEIPTS</b></td>
            <td style="text-align:center"> <b>2022<br>KWACHA<br>ZMW</b></td><td style="text-align:center"><b>2022<br>DOLLAR<br>USD</b></td>
            <td style="text-align:center"> <b>2021<br>KWACHA<br>ZMW</b></td><td style="text-align:center"> <b>2021<br>DOLLAR<br>USD</b></td></tr>
        ';
    $htmlTable .= '
            <tr>
                <td colspan="4" style="text-align:left"><b>IDA Receipts</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">' . $ida_receipts . '</td>
                <td style="text-align:right">'.$ida_dollars.'</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"><b>Other Receipts</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="8" style="text-align:left"><b>Less Expenditure</b></td>
            </tr>
            <tr>
                <td colspan="8" style="text-align:left"> <b>Personal Emoluments</b></td>
            </tr>';
            if($emoluments) {
                foreach ($emoluments as $key => $emoluments_dtls) {
                    $emol_payments = $this->formatMoney($emoluments_dtls["payments"]) ? $this->formatMoney($emoluments_dtls["payments"]) : '0.00';
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">'.$emoluments_dtls["tasks"].'</td>
                        <td style="text-align:right">'.$emol_payments.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                }
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }                
            $htmlTable .= '
            <tr>
                <td colspan="8" style="text-align:left"> <b>Goods and Services</b></td>
            </tr>';
            if($goods_services) {
                foreach ($goods_services as $key => $goods_services_dtls) {
                    $goods_services_payments = $this->formatMoney($goods_services_dtls["payments"]) ? $this->formatMoney($goods_services_dtls["payments"]) : '0.00';
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">'.$goods_services_dtls["tasks"].'</td>
                        <td style="text-align:right">'.$goods_services_payments.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                }
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }                
            $htmlTable .= '
            <tr>
                <td colspan="8" style="text-align:left"> <b>Financial Charges</b></td>
            </tr>';                
            if($fin_charges) {
                foreach ($fin_charges as $key => $fin_charges_dtls) {
                    $fin_charges_payments = $this->formatMoney($fin_charges_dtls["payments"]) ? $this->formatMoney($fin_charges_dtls["payments"]) : '0.00';
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">'.$fin_charges_dtls["tasks"].'</td>
                        <td style="text-align:right">'.$fin_charges_payments.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                }
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }  
            
            $htmlTable .= '
            <tr>
                <td colspan="8" style="text-align:left"> <b>Productivity Grants</b></td>
            </tr>';                               
            if($productivity) {
                foreach ($productivity as $key => $productivity_dtls) {
                    $productivity_payments = $this->formatMoney($productivity_dtls["payments"]) ? $this->formatMoney($productivity_dtls["payments"]) : '0.00';
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">'.$productivity_dtls["tasks"].'</td>
                        <td style="text-align:right">'.$productivity_payments.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                }
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }
            
            $htmlTable .= '
            <tr>
                <td colspan="8" style="text-align:left"> <b>Fixed Assets</b></td>
            </tr>';                                             
            if($fixed_assets) {
                foreach ($fixed_assets as $key => $fixed_assets_dtls) {
                    $fixed_assets_payments = $this->formatMoney($fixed_assets_dtls["payments"]) ? $this->formatMoney($fixed_assets_dtls["payments"]) : '0.00';
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">'.$fixed_assets_dtls["tasks"].'</td>
                        <td style="text-align:right">'.$fixed_assets_payments.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
                }
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }
            
            $htmlTable .= '
            <tr>
                <td colspan="4" style="text-align:left"> <b>Total Expenditure</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>';
            $htmlTable .= '
            <tr>
                <td colspan="8" style="text-align:left"> <b>Excess of receipts over expenditure (Total receipts minus expenditure)</b></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"> </td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
        </tbody>
    </table>';
    
    $htmlFinalTable = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
            table, th, td {
                border: 0px white;
            }
        </style>
        <table border="1" cellpadding="2" align="left" width = "100%">
            <thead></thead>
            <tbody>
                <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
            </tbody>
        </table>';
    // PDF::SetFont('helvetica', '', 8);
    PDF::SetY(50);
    PDF::writeHTML($htmlTable);
    // PDF::writeHTML($htmlTableTwo);
    // PDF::writeHTML($htmlTableThree);
    // PDF::writeHTML($htmlTableFour);
    // PDF::writeHTML($htmlTableFive);
    // PDF::writeHTML($htmlTableSix);
    PDF::writeHTML($htmlFinalTable);
    PDF::Output('DetailedExpenditureReport' . time() . '.pdf', 'I');
} 

public function generateReportByCategory($quarter,$category,$sub_category,$approval_array) {
    $date = date('j\<\s\u\p\>S\<\/\s\u\p\> F Y', time());
    PDF::SetMargins(10, 10, 10, true); 
    PDF::AddPage();
    PDF::SetAutoPageBreak(TRUE, 10);
    PDF::SetLineStyle(array('width' => 0.50, 'color' => array(0, 0, 0)));
    PDF::Rect(0, 0, PDF::getPageWidth(), PDF::getPageHeight());
    PDF::SetFont('times', 'B', 11);
    //headers
    $image_path = '\resources\images\kgs-logo.png';
    PDF::Image(getcwd() . $image_path, 0, 10, 25, 28, 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
    PDF::SetY(30);
    PDF::Cell(0, 2, 'REPUBLIC OF ZAMBIA', 0, 1, 'C');
    PDF::Cell(0, 5, 'MINISTRY OF EDUCATION', 0, 1, 'C');
    PDF::Cell(0, 5, 'P.O BOX 50093', 0, 1, 'C');
    PDF::Cell(0, 5, 'LUSAKA', 0, 1, 'C');
    PDF::SetY(2);
    PDF::ln(5);
    // Start clipping.
    PDF::SetFont('times', 'I', 9);
    PDF::StartTransform();
    PDF::SetFont('times', '', 11);
    
    // $table_one = $first_table.' as t1';
    $fiscal_yr_data = $this->getFiscalYearData();
        if(isset($fiscal_yr_data[0]['openning_bal'])){
        } else {
            echo 'Openning Balance not set';exit();
        }
    $openning_bal = $fiscal_yr_data[0]['openning_bal'];
    $current_date = $fiscal_yr_data[0]['current_date'];
    $as_at_date = $fiscal_yr_data[0]['as_at_date'];
    $year_from = $fiscal_yr_data[0]['year_from'];
    $year_to = $fiscal_yr_data[0]['year_to'];
    $parsed_year_from = Carbon::parse($year_from)->format('d/m/Y');
    $year_from_new = Carbon::parse($year_from)->format('Y-m-d');
    $at_date = Carbon::parse($as_at_date);
    $asAtDateFormated = $at_date->format('d/m/Y');
    $date_now = Carbon::now()->format('d/m/Y');
    
    $selected_year_rate = $approval_array['selected_year_rate']; 
    $prev_year_rate = $approval_array['prev_year_rate'];

    $init_ida_receipts = json_decode(
        json_encode(
            DB::table('financial_dollar_backing_sheets as t1')
                ->select(DB::raw('SUM(IF(t1.receipts IS NULL,0,t1.receipts)) as total_dollar_receipts'))
                // ->whereDate('t1.receipt_date','>=', $year_from)
                // ->whereDate('t1.receipt_date','<=', $as_at_date)
                ->get()->toArray()
        ),true
    );

    if($init_ida_receipts) {
        $ida_dollars = $this->formatMoney($init_ida_receipts[0]['total_dollar_receipts']);
        $ida_receipts_dollars = $init_ida_receipts[0]['total_dollar_receipts'];
        $ida_receipts_kwacha = $ida_receipts_dollars * $selected_year_rate;
        $ida_receipts = $this->formatMoney($ida_receipts_kwacha);
    } else {
        $ida_receipts = '0.00';
    }
    // Personal Emoluments (Code 210000 to 219999)		
    // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)		
    // Financial Charges (Code 226083)		
    // Productivty Grants (Code 225040)	
    // Fixed Assets (Codes 310000 to 329999)

    // Personal Emoluments (Code 210000 to 219999)
    $init_emoluments = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))           
        ->whereBetween('t1.gl_acc_code', [210000, 219999])->get();
   //     ->whereDate('t1.receipt_date','>=', $year_from_new)
   //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $emoluments = decryptArray(convertStdClassObjToArray($init_emoluments));        
    if($emoluments) {
        $init_emoluments = $emoluments[0]['total_emolments'];
        $emoluments = $this->formatMoney($init_emoluments);
        $calc_emolument_dollars = $init_emoluments/$selected_year_rate;
        $emolument_dollars = $this->formatMoney($calc_emolument_dollars);
    } else {
        $emoluments = '0.00';
        $emolument_dollars = '0.00';
    }
    // Goods and Services (Code 220000 to 269999 except codes 225040 and 226083)
    $init_goods_services = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
        ->whereBetween('t1.gl_acc_code', [220000, 269999])->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $goods_services = decryptArray(convertStdClassObjToArray($init_goods_services));
           
    if($goods_services[0]['total_emolments']) {
        $init_services = $goods_services[0]['total_emolments'];
        $goods_services = $this->formatMoney($init_services);
        $calc_services_dollars = $init_services/$selected_year_rate;
        $goods_services_dollars = $this->formatMoney($calc_services_dollars);
    } else {
        $goods_services = '0.00';
        $goods_services_dollars = '0.00';
    }
    // Financial Charges (Code 226083)		
    $init_fin_charges = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
        ->where('t1.gl_acc_code', 226083)->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $fin_charges = decryptArray(convertStdClassObjToArray($init_fin_charges));
        
    if($fin_charges[0]['total_emolments']) {
        $init_charges = $fin_charges[0]['total_emolments'];
        $fin_charges = $this->formatMoney($init_charges);
        $calc_charges_dollars = $init_charges/$selected_year_rate;
        $fin_charges_dollars = $this->formatMoney($calc_charges_dollars);
    } else {
        $fin_charges = '0.00';
        $fin_charges_dollars = '0.00';
    }

    // Productivity Grants (Code 225040)	
    $init_productivity = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
        ->where('t1.gl_acc_code', 226083)->get();
//     ->whereDate('t1.receipt_date','>=', $year_from_new)
//     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $productivity = decryptArray(convertStdClassObjToArray($init_productivity));
   
    if($productivity[0]['total_emolments']) {
        $init_productivity = $productivity[0]['total_emolments'];
        $productivity = $this->formatMoney($init_productivity);
        $calc_productivity_dollars = $init_productivity/$selected_year_rate;
        $productivity_dollars = $this->formatMoney($calc_productivity_dollars);
    } else {
        $productivity = '0.00';
        $productivity_dollars = '0.00';
    }

    // Fixed Assets (Codes 310000 to 329999)
    $init_fixed_assets = DB::table('financial_chart_of_accounts as t1')
        ->join('financial_cashbook_details as t2', 't1.id', '=', 't2.chart_of_acc')
        ->select(DB::raw('SUM(IF(t2.payments IS NULL,0,t2.payments)) as total_emolments'))  
        ->whereBetween('t1.gl_acc_code', [310000, 329999])->get();
    //     ->whereDate('t1.receipt_date','>=', $year_from_new)
    //     ->whereDate('t1.receipt_date','<=', $as_at_date)->get();
    $fixed_assets = decryptArray(convertStdClassObjToArray($init_fixed_assets));
   
    if($fixed_assets[0]['total_emolments']) {
        $init_fixed_assets = $fixed_assets[0]['total_emolments'];
        $fixed_assets = $this->formatMoney($init_fixed_assets);
        $calc_fixed_assets_dollars = $init_fixed_assets/$selected_year_rate;
        $fixed_assets_dollars = $this->formatMoney($calc_fixed_assets_dollars);
    } else {
        $fixed_assets = '0.00';
        $fixed_assets_dollars = '0.00';
    }

    $htmlTable = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
        </style>
        <table border="0.25" cellpadding="0.5" align="left">
        <thead></thead>
        <tbody>
            <tr><td colspan="8"> <b>KEEPING GIRLS IN SCHOOL PROJECT</b></td></tr>
            <tr><td colspan="8"> <b>STATEMENT OF CASH RECEIPTS AND EXPENDITURE FOR THE QUARTER/YEAR ENDED '.$asAtDateFormated.'</b></td></tr>
            <tr><td style="text-align:center"colspan="8"></td></tr>
            <tr><td colspan="4" style="text-align:left"> <b>RECEIPTS</b></td>
            <td style="text-align:center"> <b>2022<br>KWACHA<br>ZMW</b></td><td style="text-align:center"><b>2022<br>DOLLAR<br>USD</b></td>
            <td style="text-align:center"> <b>2021<br>KWACHA<br>ZMW</b></td><td style="text-align:center"> <b>2021<br>DOLLAR<br>USD</b></td></tr>
        ';
        
    $htmlTable .= '
            <tr>
                <td colspan="4" style="text-align:left"><b>IDA Credit and Other Receipts</b></td>
                <td style="text-align:right">'.$ida_receipts.'</td><td style="text-align:right">'.$ida_dollars.'</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"><b>TOTAL RECEIPTS</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="8" style="text-align:left"> <b>PAYMENTS</b></td>
            </tr>';
            if($emoluments) {
                $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Personnel Emoluments</td>                            
                        <td style="text-align:right">'.$emoluments.'</td>
                        <td style="text-align:right">'.$emolument_dollars.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Personnel Emoluments</td> 
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }
            if($goods_services) {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Goods and Services</td>
                    <td style="text-align:right">'.$goods_services.'</td>
                    <td style="text-align:right">'.$goods_services_dollars.'</td>
                    <td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td>
                </tr>';
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Goods and Services</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }               
                          
            if($fin_charges) {
                    $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Financial Charges</td>
                        <td style="text-align:right">'.$fin_charges.'</td>
                        <td style="text-align:right">'.$fin_charges_dollars.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Financial Charges</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }  
                                      
            if($fixed_assets) {
                $htmlTable .= '
                    <tr>
                        <td colspan="4" style="text-align:left">Fixed Assets Acquisition</td>
                        <td style="text-align:right">'.$fixed_assets.'</td>
                        <td style="text-align:right">'.$fixed_assets_dollars.'</td>
                        <td style="text-align:right">0.00</td>
                        <td style="text-align:right">0.00</td>
                    </tr>';
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Fixed Assets Acquisition</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }                               
            if($productivity) {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left">Production Grants (School fees and other requisites for school girls)</td>
                    <td style="text-align:right">'.$productivity.'</td>
                    <td style="text-align:right">'.$productivity_dollars.'</td>
                    <td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td>
                </tr>';
            } else {
                $htmlTable .= '
                <tr>
                    <td colspan="4" style="text-align:left"> </td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                    <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                </tr>';
            }             
            $htmlTable .= '
            <tr>
                <td colspan="4" style="text-align:left"> <b>TOTAL PAYMENTS</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"> <b>Increase/(Decrease) in Cash</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"> <b>Foreign Exchange Losses</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"> <b>Cash at beginning of the year</b></td>
                <td style="text-align:right">'.$ida_receipts.'</td><td style="text-align:right">'.$ida_dollars.'</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:left"> <b>Cash at the end of the year</b></td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
                <td style="text-align:right">0.00</td><td style="text-align:right">0.00</td>
            </tr>
        </tbody>
    </table>';
    
    $htmlFinalTable = '
        <style>
            table {
                border-collapse: collapse;
                white-space:nowrap;
            }
            table, th, td {
                border: 0px white;
            }
        </style>
        <table border="1" cellpadding="2" align="left" width = "100%">
            <thead></thead>
            <tbody>
                <tr><td width = "15%">PREPARED BY</td><td>'.$approval_array['prepared_by'].'</td><td style="text-align:right" width = "15%">DESIGNATION</td><td>'.$approval_array['prep_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>CHECKED BY</td><td>'.$approval_array['checked_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['checked_by_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>AUTHORISED BY</td><td>'.$approval_array['authorsed_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['auth_desig'].'</td></tr>
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>                    
                <tr><td>AUDITED BY</td><td>'.$approval_array['audited_by'].'</td><td style="text-align:right">DESIGNATION</td><td>'.$approval_array['audit_desig'].'</td></tr>                    
                <tr><td>SIGNATURE</td><td>............................................</td><td style="text-align:right">DATE</td><td> '.$date_now.' </td><td></td></tr>
            </tbody>
        </table>';
    PDF::SetY(50);
    PDF::writeHTML($htmlTable);
    PDF::writeHTML($htmlFinalTable);
    PDF::Output('DetailedExpenditureReport' . time() . '.pdf', 'I');
}
 */