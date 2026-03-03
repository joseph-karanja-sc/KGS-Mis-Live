<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewPaymentModuleController extends Controller
{
    //get payment disbursements

    public function testing()
    {
        return response()->json([
            "message" => "everything is ok"
        ]);
    }

    public function getPaymentSummaries()
    {
        $data = DB::table('payment_disbursements_summary as t1')
            ->leftJoin('kgs_mis_users as t3', 't3.id', '=', 't1.prepared_by')
            ->select(
                't1.*',
                DB::raw("CONCAT(decrypt(t3.first_name), ' ', decrypt(t3.last_name)) AS prepared_by")
            )
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getPaymentPhases(Request $request)
    {
        $refNo = $request->payment_ref_no;

        if (!$refNo) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no is required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.payment_phase',
                DB::raw('COUNT(DISTINCT t1.school_id) as total_schools'),
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', '>', 0)
            ->groupBy('t1.payment_phase')
            ->orderBy('t1.payment_phase')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "total_phases" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function getPhaseSchools(Request $request)
    {
        $refNo = $request->payment_ref_no;
        $phase = $request->payment_phase;

        if (!$refNo || !$phase) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no and payment_phase are required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.school_name',
                't1.school_district',
                't1.school_id',
                DB::raw('COUNT(t1.beneficiary_id) as total_beneficiaries'),
                DB::raw('SUM(t1.grant_amount) as total_amount')
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', $phase)
            ->groupBy('t1.school_id', 't1.school_name', 't1.school_district')
            ->orderBy('t1.school_name')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "payment_phase" => $phase,
            "total_schools" => $rows->count(),
            "data" => $rows
        ]);
    }

    public function getPaymentBeneficiaries(Request $request)
    {
        $refNo  = $request->payment_ref_no;
        $phase  = $request->payment_phase;
        $school = $request->school_id;

        if (!$refNo || !$phase || !$school) {
            return response()->json([
                "status" => false,
                "message" => "payment_ref_no, payment_phase and school_id are required"
            ], 400);
        }

        $rows = DB::table('sa_app_beneficiary_list_3 as t1')
            ->select(
                't1.beneficiary_no',
                't1.first_name',
                't1.last_name',
                't1.school_name',
                't1.hhh_nrc_number',
                't1.hhh_fname',
                't1.hhh_lname',
                't1.payment_status_id',
                't1.grant_amount'
            )
            ->where('t1.payment_ref_no', $refNo)
            ->where('t1.payment_phase', $phase)
            ->where('t1.school_id', $school)
            ->orderBy('t1.beneficiary_no')
            ->get();

        return response()->json([
            "status" => true,
            "payment_ref_no" => $refNo,
            "payment_phase" => $phase,
            "school_id" => $school,
            "total_beneficiaries" => $rows->count(),
            "data" => $rows
        ]);
    }
    
}

