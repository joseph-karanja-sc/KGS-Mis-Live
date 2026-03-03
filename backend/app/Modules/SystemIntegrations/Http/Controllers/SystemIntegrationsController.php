<?php

namespace App\Modules\SystemIntegrations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SystemIntegrationsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('systemintegrations::index');
    }

    public function getKGSBeneficiaries(Request $request)
    {
        $hhh_nrc = $request->input('hhh_nrc');
        $enrollment_status = $request->input('enrollment_status');
        try {
            $qry = DB::table('beneficiary_information as t1')
                ->select(DB::raw("t1.beneficiary_id,decrypt(t1.first_name) as first_name,decrypt(t1.last_name) as last_name"));
            if (validateisNumeric($enrollment_status)) {
                $qry->where('t1.enrollment_status', $enrollment_status);
            }
            if (isset($hhh_nrc)) {
                $qry->whereRaw("t1.household_id= (SELECT id FROM households WHERE hhh_nrc_number='$hhh_nrc')");
            }
            $results = $qry->get();
            $res = array(
                'success' => true,
                'message' => returnMessage($results),
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

}
