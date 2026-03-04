<?php

namespace App\Modules\SchoolManagement\Http\Controllers;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SchoolManagementController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('schoolmanagement::index');
    }

    public function schoolsBankInformation()
    {
        // Fetch districts (adjust query as needed - e.g. only active ones, ordered)
        $districts = DB::table('districts')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Fetch banks for the dropdown (assuming bank_details table has the banks)
        $banks = DB::table('bank_details')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('schoolmanagement::schools_bank_information', compact('districts', 'banks'));
    }


    public function schoolsBankList(Request $request)
    {
        $query = DB::table('school_information as s')
            ->leftJoin('districts as d', 's.district_id', '=', 'd.id')
            ->leftJoin('school_bankinformation as sb', function($join) {
                $join->on('s.id', '=', 'sb.school_id')
                    ->where('sb.account_type', '=', 1); // Only School Fees
            })
            ->leftJoin('bank_details as b', 'sb.bank_id', '=', 'b.id')
            ->leftJoin('bank_branches as bb', 'sb.branch_name', '=', 'bb.id')
            ->select([
                's.id as school_id',
                's.name as school_name',
                's.code as emis',
                'd.name as district_name',
                'b.name as bank_name',
                'bb.name as branch_name',
                DB::raw('CASE WHEN sb.account_no IS NOT NULL THEN decrypt(sb.account_no) ELSE NULL END as account_no'),
                'sb.sort_code',
            ]);

        if ($request->district_id) {
            $query->where('s.district_id', $request->district_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('s.name', 'like', "%{$request->search}%")
                ->orWhere('s.code', 'like', "%{$request->search}%");
            });
        }

        // isDeleted = 0
        $query->where('s.isDeleted', 0);

        // Get total count BEFORE pagination
        $total = $query->count();
        
        // Add pagination
        $perPage = $request->per_page ?? 50;
        $page = $request->page ?? 1;
        $offset = ($page - 1) * $perPage;
        
        $schools = $query->orderBy('s.name')
                        ->offset($offset)
                        ->limit($perPage)
                        ->get();

        return response()->json([
            'success' => true,
            'schools' => $schools,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }

    public function getSchoolBankInfo($schoolId)
    {
        $info = DB::table('school_bankinformation')
            ->where('school_id', $schoolId)
            ->where('account_type', 1) // Only School Fees
            ->select([
                '*',
                DB::raw('decrypt(account_no) as account_no'),
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $info
        ]);
    }


    public function getBankBranches($bankId)
    {
        try {
            // Get bank details
            $bank = DB::table('bank_details')
                ->where('id', $bankId)
                ->first();

            // Get unique branches (first occurrence of each unique name per bank)
            $branches = DB::table('bank_branches')
                ->select([
                    DB::raw('MIN(id) as id'),
                    'bank_id',
                    DB::raw('UPPER(TRIM(name)) as name'),
                    DB::raw('MIN(sort_code) as sort_code')
                ])
                ->where('bank_id', $bankId)
                ->groupBy(DB::raw('UPPER(TRIM(name))'), 'bank_id')
                ->orderBy('name', 'asc')
                ->get();

            // Format the results
            $formattedBranches = $branches->map(function($branch) {
                return [
                    'id' => $branch->id,
                    'bank_id' => $branch->bank_id,
                    'name' => $branch->name,
                    'sort_code' => $branch->sort_code
                ];
            });

            return response()->json([
                'success' => true,
                'branches' => $formattedBranches,
                'bankName' => $bank ? $bank->name : ''
            ]);

        } catch (\Exception $e) {
            Log::error('Get branches error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'branches' => [],
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteSchoolBank($school_id)
    {
        // Delete school by updating isDeleted column to 1
        try {
            $deleted = DB::table('school_information')
                ->where('id', $school_id)
                ->update(['isDeleted' => 1]);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'School deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No school found with this ID'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateSchoolBank(Request $request)
    {
        if(!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Please Log in'], 401);
        }

        $request->validate([
            'school_id'   => 'required|integer|exists:school_information,id',
            'bank_id'     => 'required|integer|exists:bank_details,id',
            'branch_id'   => 'required|integer|exists:bank_branches,id',
            'account_no'  => 'nullable|string|max:50',
            'sort_code'   => 'nullable|string|max:30',
        ]);

        try {
            DB::beginTransaction();

            // Update or insert school bank information
            DB::table('school_bankinformation')
                ->updateOrInsert(
                    [
                        'school_id' => $request->school_id,
                        'account_type' => 1  // School Fees
                    ],
                    [
                        'bank_id'     => $request->bank_id,
                        'branch_name' => $request->branch_id,
                        'account_no'  => aes_encrypt($request->account_no ?? ''),
                        'sort_code'   => $request->sort_code ?? '',
                        'is_activeaccount' => 1,
                        'updated_at'  => now(),
                        'updated_by'  => auth()->id(),
                    ]
                );

            // Also update the sort code in bank_branches table
            if ($request->sort_code && $request->branch_id) {
                DB::table('bank_branches')
                    ->where('id', $request->branch_id)
                    ->update([
                        'sort_code' => $request->sort_code,
                        'updated_at' => now(),
                        'updated_by' => auth()->id()
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Bank details updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update school bank error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false, 
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createBranch(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank_id' => 'required|integer|exists:bank_details,id',
                'name' => 'required|string|max:200',
                'sort_code' => 'nullable|string|max:20'
            ]);

            // Capitalize branch name
            $branchName = strtoupper(trim($validated['name']));
            $sortCode = $validated['sort_code'] ? strtoupper(trim($validated['sort_code'])) : null;

            // Check if branch name already exists for this bank
            $existsByName = DB::table('bank_branches')
                ->where('bank_id', $validated['bank_id'])
                ->whereRaw('UPPER(name) = ?', [$branchName])
                ->exists();

            if ($existsByName) {
                return response()->json([
                    'success' => false,
                    'message' => 'A branch with this name already exists for the selected bank. Please use a different name or edit the existing branch.'
                ], 422);
            }

            // Check if sort code already exists for this bank (if provided)
            if ($sortCode) {
                $existsBySortCode = DB::table('bank_branches')
                    ->where('bank_id', $validated['bank_id'])
                    ->whereRaw('UPPER(sort_code) = ?', [$sortCode])
                    ->exists();

                if ($existsBySortCode) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A branch with this sort code already exists for the selected bank. The sort code ' . $sortCode . ' is already in use.'
                    ], 422);
                }
            }

            // Create branch
            $branchId = DB::table('bank_branches')->insertGetId([
                'bank_id' => $validated['bank_id'],
                'name' => $branchName,
                'sort_code' => $sortCode,
                'created_at' => now(),
                'created_by' => auth()->id() ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'branch_id' => $branchId,
                'branch_name' => $branchName
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Create branch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch. Please try again.'
            ], 500);
        }
    }

    /**
     * Update an existing bank branch
     */
    public function updateBranch(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer|exists:bank_branches,id',
                'bank_id' => 'required|integer|exists:bank_details,id',
                'name' => 'required|string|max:200',
                'sort_code' => 'nullable|string|max:20'
            ]);

            // Capitalize branch name
            $branchName = strtoupper(trim($validated['name']));
            $sortCode = $validated['sort_code'] ? strtoupper(trim($validated['sort_code'])) : null;

            // Check if branch name already exists for this bank (excluding current branch)
            $existsByName = DB::table('bank_branches')
                ->where('bank_id', $validated['bank_id'])
                ->where('id', '!=', $validated['branch_id'])
                ->whereRaw('UPPER(name) = ?', [$branchName])
                ->exists();

            if ($existsByName) {
                return response()->json([
                    'success' => false,
                    'message' => 'A branch with this name already exists for the selected bank. Please use a different name.'
                ], 422);
            }

            // Check if sort code already exists for this bank (excluding current branch)
            if ($sortCode) {
                $existsBySortCode = DB::table('bank_branches')
                    ->where('bank_id', $validated['bank_id'])
                    ->where('id', '!=', $validated['branch_id'])
                    ->whereRaw('UPPER(sort_code) = ?', [$sortCode])
                    ->exists();

                if ($existsBySortCode) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A branch with this sort code already exists for the selected bank. The sort code ' . $sortCode . ' is already in use.'
                    ], 422);
                }
            }

            // Update branch
            DB::table('bank_branches')
                ->where('id', $validated['branch_id'])
                ->update([
                    'name' => $branchName,
                    'sort_code' => $sortCode,
                    'updated_at' => now(),
                    'updated_by' => auth()->id() ?? 1
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'branch_id' => $validated['branch_id'],
                'branch_name' => $branchName
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Update branch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch. Please try again.'
            ], 500);
        }
    }

    /**
     * Get branch details for editing
     */
    public function getBranchDetails($branchId)
    {
        try {
            $branch = DB::table('bank_branches')
                ->where('id', $branchId)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $branch
            ]);
        } catch (\Exception $e) {
            \Log::error('Get branch details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get branch details'
            ], 500);
        }
    }

    /**
     * School Consolidation - Show the consolidation view
     */
    public function schoolConsolidation()
    {
        // Fetch districts
        $districts = DB::table('districts')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Fetch provinces
        $provinces = DB::table('provinces')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Fetch running agencies
        $runningAgencies = DB::table('running_agencies')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('schoolmanagement::school_consolidation', compact('districts', 'provinces', 'runningAgencies'));
    }

    /**
     * Get schools for consolidation with beneficiary counts
     */
    public function getSchoolsForConsolidation(Request $request)
    {
        $query = DB::table('school_information as s')
            ->leftJoin('districts as d', 's.district_id', '=', 'd.id')
            ->leftJoin('provinces as p', 'd.province_id', '=', 'p.id')
            ->leftJoin('beneficiary_information as be', 's.id', '=', 'be.school_id')
            ->select([
                's.id as school_id',
                's.name as school_name',
                's.code as emis',
                's.district_id',
                'd.name as district_name',
                's.province_id',
                'p.name as province_name',
                's.school_type_id',
                's.isDeleted',
                's.telephone_no',
                's.email_address',
                's.physical_address',
                's.mobile_no',
                's.running_agency_id',
                DB::raw('COUNT(CASE WHEN be.enrollment_status = 1 THEN 1 END) as active_beneficiary_count'),
                DB::raw('COUNT(be.id) as beneficiary_count')
            ])
            ->groupBy('s.id');

        if ($request->district_id) {
            $query->where('s.district_id', $request->district_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('s.name', 'like', "%{$request->search}%")
                  ->orWhere('s.code', 'like', "%{$request->search}%");
            });
        }

        // Only get non-deleted schools
        $query->where('s.isDeleted', 0);
        $query->limit(1200);

        $schools = $query->orderBy('s.name')->get();

        return response()->json([
            'success' => true,
            'schools' => $schools
        ]);
    }

    /**
     * Get detailed school information for editing
     */
    public function getSchoolDetails($schoolId)
    {
        $school = DB::table('school_information as s')
            ->leftJoin('districts as d', 's.district_id', '=', 'd.id')
            ->leftJoin('provinces as p', 's.province_id', '=', 'p.id')
            ->leftJoin('school_running_agencies as ra', 's.running_agency_id', '=', 'ra.id')
            ->select([
                's.*',
                'd.name as district_name',
                'p.name as province_name',
                'ra.name as running_agency_name'
            ])
            ->where('s.id', $schoolId)
            ->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'school' => $school
        ]);
    }

    /**
     * Update school details
     */
    public function updateSchoolDetails(Request $request)
    {
        if(!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'school_id' => 'required|integer|exists:school_information,id',
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50'
        ]);

        try {
            // Check if EMIS code is unique (excluding current school)
            $exists = DB::table('school_information')
                ->where('code', $request->code)
                ->where('id', '!=', $request->school_id)
                ->where('isDeleted', 0)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'EMIS code already exists for another school'
                ], 422);
            }

            $updateData = [
                'name' => $request->name,
                'code' => $request->code,
                'updated_at' => now(),
                'updated_by' => auth()->id()
            ];

            // Optional editable fields
            if ($request->has('telephone_no') && $request->telephone_no) {
                $updateData['telephone_no'] = $request->telephone_no;
            }
            if ($request->has('mobile_no') && $request->mobile_no) {
                $updateData['mobile_no'] = $request->mobile_no;
            }
            if ($request->has('email_address') && $request->email_address) {
                $updateData['email_address'] = $request->email_address;
            }
            if ($request->has('postal_address') && $request->postal_address) {
                $updateData['postal_address'] = $request->postal_address;
            }
            if ($request->has('physical_address') && $request->physical_address) {
                $updateData['physical_address'] = $request->physical_address;
            }

            DB::table('school_information')
                ->where('id', $request->school_id)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'School details updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Update school details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consolidate schools - transfer beneficiaries to mother school and mark others as deleted
     */
    public function consolidateSchools(Request $request)
    {
        if(!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'mother_school_id' => 'required|integer|exists:school_information,id',
            'child_school_ids' => 'required|array|min:1',
            'child_school_ids.*' => 'integer|exists:school_information,id'
        ]);

        try {
            DB::beginTransaction();

            $motherSchoolId = $request->mother_school_id;
            $childSchoolIds = $request->child_school_ids;

            // DISTRICT VALIDATION: Get mother school's district
            $motherSchool = DB::table('school_information')
                ->where('id', $motherSchoolId)
                ->first();

            if (!$motherSchool) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mother school not found'
                ], 404);
            }

            // Check that all child schools are in the same district
            $childSchoolsInWrongDistrict = DB::table('school_information as si')
                ->leftJoin('districts as d', 'si.district_id', '=', 'd.id')
                ->whereIn('si.id', $childSchoolIds)
                ->where('si.district_id', '!=', $motherSchool->district_id)
                ->select('si.id', 'si.name', 'd.name as district_name')
                ->get();

            if ($childSchoolsInWrongDistrict->isNotEmpty()) {
                $wrongDistrictSchools = $childSchoolsInWrongDistrict->pluck('name')->implode(', ');
                $motherDistrict = DB::table('districts')->where('id', $motherSchool->district_id)->value('name');
                
                return response()->json([
                    'success' => false,
                    'message' => "Cannot consolidate: Schools from different districts detected. Only schools within the same district can be consolidated. The following schools are not in {$motherDistrict} district: {$wrongDistrictSchools}"
                ], 422);
            }

            // Get total beneficiaries to be transferred
            $childBeneficiaryCount = DB::table('beneficiary_information')
                ->whereIn('school_id', $childSchoolIds)
                ->count();

            // Update all beneficiary enrollments from child schools to mother school
            DB::table('beneficiary_information')
                ->whereIn('school_id', $childSchoolIds)
                ->update([
                    'school_id' => $motherSchoolId,
                    'updated_at' => now()
                ]);

            // // Update all beneficiary master info
            // DB::table('beneficiary_master_info')
            //     ->whereIn('id', $childIds)
            //     ->update([
            //         'school_id' => $motherSchoolId,
            //         'updated_at' => now()
            //     ]);

            // Mark all child schools as deleted
            DB::table('school_information')
                ->whereIn('id', $childSchoolIds)
                ->update([
                    'isDeleted' => 1,
                    'updated_at' => now(),
                    'updated_by' => auth()->id()
                ]);

            // Log the consolidation
            Log::info('School consolidation performed', [
                'mother_school_id' => $motherSchoolId,
                'child_school_ids' => $childSchoolIds,
                'beneficiaries_transferred' => $childBeneficiaryCount,
                'performed_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully consolidated {$childBeneficiaryCount} beneficiaries to the mother school"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School consolidation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to consolidate: ' . $e->getMessage()
            ], 500);
        }
    }

    public function viewBankDuplicates()
    {
        // Banks with duplicates
        $bankDuplicates = DB::table('bank_details')
            ->select([
                DB::raw('UPPER(TRIM(name)) as clean_name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(id ORDER BY id) as ids'),
                DB::raw('GROUP_CONCAT(name ORDER BY id SEPARATOR " | ") as names')
            ])
            ->groupBy(DB::raw('UPPER(TRIM(name))'))
            ->having('count', '>', 1)
            ->get();

        // Branches with duplicates
        $branchDuplicates = DB::table('bank_branches as bb')
            ->join('bank_details as bd', 'bb.bank_id', '=', 'bd.id')
            ->select([
                'bb.bank_id',
                'bd.name as bank_name',
                DB::raw('UPPER(TRIM(bb.name)) as clean_branch_name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(bb.id ORDER BY bb.id) as ids'),
                DB::raw('GROUP_CONCAT(bb.name ORDER BY bb.id SEPARATOR " | ") as names')
            ])
            ->groupBy('bb.bank_id', DB::raw('UPPER(TRIM(bb.name))'), 'bd.name')
            ->having('count', '>', 1)
            ->get();

        return view('duplicates', compact('bankDuplicates', 'branchDuplicates'));
    }

    public function saveCommonData(Request $req)
    {
        $user_id = $this->user_id;
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
        $table_data = encryptArray($post_data, $skipArray);
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
                if ($success) {
                    $res = array(
                        'success' => true,
                        'message' => 'Data updated Successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Problem encountered while updating data. Try again later!!'
                    );
                }
            }
        } else {
            $success = insertRecord($table_name, $table_data, $user_id);
            if ($success) {
                $res = array(
                    'success' => true,
                    'message' => 'Data Saved Successfully!!'
                );
            } else {
                $res = array(
                    'success' => false,
                    'message' => 'Problem encountered while saving data. Try again later!!'
                );
            }
        }
        return response()->json($res);
    }

    public function getSchoolManagementParam(Request $req)
    {
        $model = $req->input('model_name');
        $table = $req->input('table_name');
        try {
            $model = 'App\\Modules\\schoolmanagement\\Models\\' . $model;
            $results = $model::all()->toArray();
            $results = decryptArray($results);
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
        }
        return response()->json($res);
    }

    public function getMainCapacityAssessments()
    {
        try {
            $qry = DB::table('school_capacity_assessments')
                ->groupBy('year');
            $data = $qry->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getCapacityAssessmentDetails(Request $req)
    {
        $year = $req->input('year');
        $start = $req->input('start');
        $limit = $req->input('limit');
        $qry = DB::table('school_information')
            ->leftJoin('districts', 'school_information.district_id', '=', 'districts.id')
            //->select(DB::raw('SUM(IF(grade = 2, 1, 0)) AS continuing_girls'))
            ->select('school_information.id', 'school_information.name', 'districts.id as district_id', 'districts.name as district_name');
        $total = $qry->count();
        $qry->offset($start)
            ->limit($limit);
        $schools = $qry->get();
        $schools = convertStdClassObjToArray($schools);
        //$schools = decryptArray($schools);
        foreach ($schools as $key => $school) {
            $schools[$key]['grade_8_max'] = $this->getGradeMaxSpace($school['id'], 8, $year);
            $schools[$key]['grade_8_occupied'] = $this->getGradeOccupiedSpace($school['id'], 8, $year);
            $schools[$key]['grade_9_max'] = $this->getGradeMaxSpace($school['id'], 9, $year);
            $schools[$key]['grade_9_occupied'] = $this->getGradeOccupiedSpace($school['id'], 9, $year);
            $schools[$key]['grade_10_max'] = $this->getGradeMaxSpace($school['id'], 10, $year);
            $schools[$key]['grade_10_occupied'] = $this->getGradeOccupiedSpace($school['id'], 10, $year);
            $schools[$key]['grade_11_max'] = $this->getGradeMaxSpace($school['id'], 11, $year);
            $schools[$key]['grade_11_occupied'] = $this->getGradeOccupiedSpace($school['id'], 11, $year);
            $schools[$key]['grade_12_max'] = $this->getGradeMaxSpace($school['id'], 12, $year);
            $schools[$key]['grade_12_occupied'] = $this->getGradeOccupiedSpace($school['id'], 12, $year);
        }
        $res = array(
            'results' => $schools,
            'totalCount' => $total
        );
        return response()->json($res);
    }

    public function getGradeMaxSpace($school_id, $grade, $year)
    {
        $where = array(
            'school_id' => $school_id,
            'grade' => $grade,
            'year' => $year
        );
        $max = 0;
        $info = DB::table('school_capacity_assessments')
            ->where($where)
            ->first();
        if (!is_null($info)) {
            $max = $info->classroom_max;
        }
        return $max;
    }

    public function getGradeOccupiedSpace($school_id, $grade, $year)
    {
        $where = array(
            'school_id' => $school_id,
            'grade' => $grade,
            'year' => $year
        );
        $max = 0;
        $info = DB::table('school_capacity_assessments')
            ->where($where)
            ->first();
        if (!is_null($info)) {
            $max = $info->current_capacity;
        }
        return $max;
    }

    public function getClassroomMaxDetails()
    {
        try {
            $data = DB::table('classroom_max')->get();
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => true,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateSchoolCapacityInfo(Request $req)
    {
        $school_id = $req->input('id');
        $year = $req->input('year');
        //records insertion...many rows
        $params = array();
        try {
            for ($i = 8; $i <= 12; $i++) {
                $key_max = 'grade_' . $i . '_max';
                $key_occupied = 'grade_' . $i . '_occupied';
                $class_max = $req->input($key_max);
                $class_occupied = $req->input($key_occupied);
                $params[] = array(
                    'school_id' => $school_id,
                    'year' => $year,
                    'grade' => $i,
                    'classroom_max' => $class_max,
                    'current_capacity' => $class_occupied
                );
            }
            DB::table('school_capacity_assessments')->where('school_id', $school_id)->delete();
            DB::table('school_capacity_assessments')->insert($params);
            $res = array(
                'success' => true,
                'message' => 'Information updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    //the school management functions
    public function getSchools_infomanagementStr(Request $req)
    {
        try {
            $school_filter = getEnquiryfilter($req->input('school_id'), 't2.id');
            $district_filter = getEnquiryfilter($req->input('district_id'), 't2.district_id');
            $province_filter = getEnquiryfilter($req->input('province_id'), 't3.province_id');
            $status_filter = array('beneficiary_status' => 4, 'enrollment_status' => 1);
            $filter = array_merge($status_filter, $school_filter, $district_filter, $province_filter);

            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->leftJoin('school_bankinformation as t5', 't2.id', '=', 't5.school_id')
                ->select(DB::raw('t5.bank_id, t5.branch_name,t5.account_no,t5.sort_code, t2.*, t2.code as school_emisno, 
                                t3.code as district_code, t2.name as school_name, t3.name as district_name, t4.name as province_name, 
                                count(t1.id) as no_of_beneficiairies, (select sum(decrypt(amount_transfered)) from payment_disbursement_details where school_id = t2.id)  as totalfees_disbursed, 
                                t2.id as school_id'))
                ->where($filter)
                ->groupBy('t2.id');
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
        return \response()->json($res);
    }

    public function getSchooldistrict_summaryStr(Request $req)
    {
        try {
            $filter = array('beneficiary_status' => 4, 'enrollment_status' => 1);

            $qry = DB::table('beneficiary_information as t1')
                ->join('school_information as t2', 't1.school_id', '=', 't2.id')
                ->join('districts as t3', 't2.district_id', '=', 't3.id')
                ->join('provinces as t4', 't3.province_id', '=', 't4.id')
                ->select(DB::raw('t3.name as district_name, t4.name as province_name, count(t1.id) as no_of_beneficiairies, 
                            (select sum(decrypt(amount_transfered)) from payment_disbursement_details q inner join school_information  j on q.school_id= j.id where j.district_id = t2.district_id)  as totalfees_disbursed'))
                ->where($filter)
                ->groupBy('t2.district_id');
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
        return \response()->json($res);
    }

    public function getBeneficiarySchEnrollmentinfoStr(Request $req)
    {
        $school_id = $req->school_id;
        $term_id = $req->term_id;
        $year_of_enrollment = $req->year_of_enrollment;
        $enrollment_status = $req->enrollment_status;
        //the the query
        $school_filter = getEnquiryfilter($req->school_id, 't2.school_id');
        $term_filter = getEnquiryfilter($req->term_id, 't2.term_id');
        $year_filter = getEnquiryfilter($req->year_of_enrollment, 't2.year_of_enrollment');
        $filter = array_merge($school_filter, $term_filter, $year_filter);

        $qry = DB::table('beneficiary_information as t1')
            ->select('t8.name as school_term', 't7.enrollment_id as payment_chk', 't6.no_of_days as total_learning_days', 't1.*', 't5.benficiary_attendance as attendance_rate', 't2.id as enrollement_id', 't2.*', 't4.name as home_district', 't1.beneficiary_id as beneficiary_no', 'science_score', 'mathematics_score', 'mathsclass_average', 'english_score', 'engclass_average', 'scienceclass_average', 'aggregate_average_score', 'benficiary_attendance', 't5.grade as performance_grade')
            ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
            ->join('school_information as t3', 't2.school_id', '=', 't3.id')
            ->join('districts as t4', 't1.district_id', '=', 't4.id')
            ->leftJoin('beneficiary_attendanceperform_details as t5', 't2.id', '=', 't5.enrollment_id')
            ->leftJoin('school_term_days as t6', function ($join) {
                $join->on('t2.year_of_enrollment', '=', 't6.year_of_enrollment')
                    ->on('t2.term_id', '=', 't6.term_id');
            })
            ->join('school_terms as t8', 't2.term_id', '=', 't8.id')
            ->leftJoin('beneficiary_payment_records as t7', 't2.id', '=', 't7.enrollment_id')
            ->where($filter)
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        json_output($res);

    }

    public function getSchool_benficiariesinfoStr(Request $req)
    {
        $school_id = $req->school_id;
        $term_id = $req->term_id;
        $year_of_enrollment = $req->year_of_enrollment;
        $enrollment_status = $req->enrollment_status;
        //the the query
        $school_filter = getEnquiryfilter($req->school_id, 't1.school_id');
        $status_filter = getEnquiryfilter($req->enrollment_status, 't1.enrollment_status');
        $grade_filter = getEnquiryfilter($req->grade_id, 't1.current_school_grade');
        $filter = array_merge($school_filter, $status_filter, $grade_filter);

        $qry = DB::table('beneficiary_information as t1')
            ->select('t1.*', 't2.hhh_nrc_number', 't2.hhh_fname', 't3.name as enrollmentstatus', 't4.name as home_district')
            ->leftJoin('households as t2', 't1.household_id', '=', 't2.id')
            ->join('beneficiary_enrollement_statuses as t3', 't1.enrollment_status', '=', 't3.id')
            ->join('districts as t4', 't1.district_id', '=', 't4.id')
            ->where($filter)
            ->get();
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);


    }

    public function getSchoolfeesdisbursementinfstr(Request $req)
    {
        $term_id = $req->term_id;
        $year_of_enrollment = $req->year_of_enrollment;
        $school_id = $req->school_id;

        $school_filter = getEnquiryfilter($req->school_id, 't11.school_id');
        $year_of_enrollment_filter = getEnquiryfilter($req->year_of_enrollment, 't13.payment_year');
        $term_filter = getEnquiryfilter($req->term_id, 't13.term_id');

        $filter = array_merge($school_filter, $year_of_enrollment_filter, $term_filter);

        $qry = DB::table('beneficiary_information as t1')
            ->select(DB::raw('t14.name as school_term, t13.payment_year, t12.name as payment_status, t11.id as payment_disbursement_id,t11.amount_transfered,t11.transaction_no,t11.transaction_date,t11.remarks, t2.id as school_id,t8.payment_request_id, t10.name as bank_name, t9.branch_name,t9.account_no,t9.sort_code,count(t1.id) as no_of_beneficiary,sum(school_fees) as school_feessummary,t2.name as school_name, t3.name as district_name,t4.name as province_name'))
            ->join('beneficiary_enrollments as t5', 't1.id', '=', 't5.beneficiary_id')
            ->join('school_information as t2', 't2.id', '=', 't5.school_id')
            ->join('districts as t3', 't2.district_id', '=', 't3.id')
            ->join('provinces as t4', 't3.province_id', '=', 't4.id')
            ->join('beneficiary_payment_records as t8', 't5.id', '=', 't8.enrollment_id')
            ->leftJoin('school_bankinformation as t9', 't2.id', '=', 't9.school_id')
            ->leftJoin('bank_details as t10', 't9.bank_id', '=', 't10.id')
            ->join('payment_disbursement_details as t11', function ($join) {
                $join->on('t8.payment_request_id', '=', 't11.payment_request_id');
                $join->on('t2.id', '=', 't11.school_id');
            })
            ->leftJoin('payment_disbursement_status as t12', 't11.payment_status_id', '=', 't12.id')
            ->join('payment_request_details as t13', 't11.payment_request_id', 't13.id')
            ->join('school_terms as t14', 't13.term_id', 't14.id')
            ->where($filter)
            ->groupBy('t11.id')
            ->get();

        //print_r(DB::getQueryLog());
        $results = convertStdClassObjToArray($qry);
        $results = decryptArray($results);
        $res = array(
            'results' => $results
        );
        //var_dump($res);
        json_output($res);

    }

    public function saveMonitoringDetails(Request $req)
    {
        $monitoring_type = $req->input('monitoring_type');
        $monitoring_date = $req->input('monitoring_date');
        $monitoring_year = $req->input('monitoring_year');
        $monitoring_term = $req->input('monitoring_term');
        $school_id = $req->input('school_id');
        $monitoring_id = $req->input('monitoring_id');
        $inspectors = $req->input('inspectors');
        $inspectors = json_decode($inspectors);
        $user_id = \Auth::user()->id;
        if (count($inspectors) < 1) {
            $res = array(
                'success' => false,
                'message' => 'You have not selected inspector(s). Please select at least one inspector by clicking on the \'Add\' button!!'
            );
            return response()->json($res);
        }
        try {
            if (isset($monitoring_id) && $monitoring_id != '') {
                $where = array(
                    'id' => $monitoring_id
                );
                $update_params = array(
                    'monitoring_type' => $monitoring_type,
                    'school_id' => $school_id,
                    'monitoring_date' => $monitoring_date,
                    'monitoring_year' => $monitoring_year,
                    'monitoring_term' => $monitoring_term
                );
                $prev_data = getPreviousRecords('school_monitoring_rpt', $where);
                updateRecord('school_monitoring_rpt', $prev_data, $where, $update_params, $user_id);
                $results = array(
                    'monitoring_id' => '',
                    'reference_no' => ''
                );
            } else {
                $year = date('Y');
                $last_id = DB::table('school_monitoring_rpt')->max('id');
                $curr_id = $last_id + 1;
                $serial = str_pad($curr_id, 4, 0, STR_PAD_LEFT);
                $ref_number = 'KGS-SCH-MON-' . substr($year, -2) . '-' . $serial;
                $params = array(
                    'reference_number' => $ref_number,
                    'monitoring_type' => $monitoring_type,
                    'school_id' => $school_id,
                    'monitoring_date' => $monitoring_date,
                    'monitoring_year' => $monitoring_year,
                    'monitoring_term' => $monitoring_term
                );
                $monitoring_id = insertReturnID('school_monitoring_rpt', $params);
                if ($monitoring_type == 2) {
                    $this->initializePaymentsSchoolMonitoringSummary($monitoring_id, $school_id, $monitoring_year, $monitoring_term);
                } else {
                    $this->initializeSchoolMonitoringSummary($monitoring_id, $school_id);
                }
                $results = array(
                    'monitoring_id' => $monitoring_id,
                    'reference_no' => $ref_number
                );
            }
            $inspectors_insert = array();
            foreach ($inspectors as $inspector) {
                $inspectors_insert[] = array(
                    'report_id' => $monitoring_id,
                    'inspector_id' => $inspector
                );
            }
            DB::table('school_monitoring_inspectors')->where('report_id', $monitoring_id)->delete();
            DB::table('school_monitoring_inspectors')->insert($inspectors_insert);
            $res = array(
                'success' => true,
                'results' => $results,
                'message' => 'Monitoring details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveSchoolDetails(Request $req)
    {
        $school_id = $req->input('school_id');
        $district_id = $req->input('district_id');
        $school_name = $req->input('name');
        $school_code = $req->input('code');
        $school_email = $req->input('email_address');
        $school_type = $req->input('school_type_id');

        $school_head_name = $req->input('full_names');
        $school_head_phone = $req->input('head_telephone');
        $school_head_mobile = $req->input('head_mobile');
        $school_head_email = $req->input('head_email');

        $school_params = array(
            'name' => $school_name,
            'code' => $school_code,
            'province_id' => '',
            'district_id' => $district_id,
            'email_address' => $school_email,
            'school_type_id' => $school_type
        );
        $contact_params = array(
            'full_names' => $school_head_name,
            'telephone_no' => $school_head_phone,
            'mobile_no' => $school_head_mobile,
            'email_address' => $school_head_email
        );

        try {
            DB::table('school_information')
                ->where('id', $school_id)
                ->update($school_params);
            $exists = DB::table('school_contactpersons')
                ->where(array('school_id' => $school_id, 'designation_id' => 1))
                ->first();
            if (is_null($exists)) {
                $contact_params['school_id'] = $school_id;
                $contact_params['designation_id'] = 1;
                $contact_params['created_at'] = Carbon::now();
                $contact_params['created_by'] = \Auth::user()->id;
                DB::table('school_contactpersons')
                    ->insert($contact_params);
            } else {
                $contact_params['updated_at'] = Carbon::now();
                $contact_params['updated_by'] = \Auth::user()->id;
                DB::table('school_contactpersons')
                    ->where('school_id', $school_id)
                    ->where('designation_id', 1)
                    ->update($contact_params);
            }
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveMonitoringBeneficiaryDetails(Request $req)
    {
        $post_data = $req->all();
        $discrepancy = $post_data['discrepancy'];
        $monitoring_id = $post_data['monitoring_id'];
        unset($post_data['discrepancy']);
        unset($post_data['monitoring_id']);
        unset($post_data['_token']);
        try {
            foreach ($post_data as $key => $value) {
                $record_id = $post_data[$key]['id'];
                $reason = $post_data[$key]['reason'];
                $remark = $post_data[$key]['remark'];
                $update_params = array(
                    'reason' => $reason,
                    'remark' => $remark
                );
                DB::table('monitoring_missing_beneficiaries')
                    ->where('id', $record_id)
                    ->update($update_params);
            }
            DB::table('school_monitoring_rpt')
                ->where('id', $monitoring_id)
                ->update(array('discrepancy' => $discrepancy));
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function saveEducationQualityDetails(Request $req)
    {
        $user_id = \Auth::user()->id;
        $post_data = $req->input();
        $monitoring_report_id = $post_data['monitoring_id'];
        unset($post_data['monitoring_id']);
        unset($post_data['_token']);
        $where = array(
            'id' => $monitoring_report_id
        );
        try {
            $prev_data = getPreviousRecords('school_monitoring_rpt', $where);
            updateRecord('school_monitoring_rpt', $prev_data, $where, $post_data, $user_id);
            $res = array(
                'success' => true,
                'message' => 'Details updated successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSchMonitoringInspectors(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('school_monitoring_inspectors')
                ->join('users', 'school_monitoring_inspectors.inspector_id', '=', 'users.id')
                ->select('school_monitoring_inspectors.id as monitoring_inspectors_id', 'users.id', 'users.first_name', 'users.last_name', 'users.phone', 'users.email')
                ->where('report_id', $monitoring_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function getSchoolMonitoringReports(Request $req)
    {
        //$school_id = $req->input('school_id');
        try {
            $qry = DB::table('school_monitoring_rpt')
                ->join('school_monitoring_types', 'school_monitoring_rpt.monitoring_type', '=', 'school_monitoring_types.id')
                ->join('school_information', 'school_monitoring_rpt.school_id', '=', 'school_information.id')
                ->select(DB::raw('school_monitoring_rpt.id,school_monitoring_rpt.school_id,school_monitoring_rpt.reference_number,school_monitoring_rpt.monitoring_date,
                        school_monitoring_rpt.stage as monitoring_stage,school_monitoring_rpt.monitoring_year,school_monitoring_rpt.monitoring_term,school_monitoring_rpt.discrepancy,school_monitoring_rpt.monitoring_type,school_monitoring_rpt.ave_pupils_class,school_monitoring_rpt.ave_learning_hours_day,
                        school_monitoring_rpt.desk_type,school_monitoring_rpt.ave_pupils_desk,school_monitoring_rpt.school_comments,school_monitoring_rpt.girls_comments,
                        YEAR(school_monitoring_rpt.monitoring_date) as monitoring_year,school_monitoring_types.name as monitoring_type_name,school_information.name as school_name'))
                //->where('school_monitoring_rpt.school_id', $school_id)
                ->where('school_monitoring_rpt.stage', 1);
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
        }
        return response()->json($res);
    }

    public function removeMonitoringInspector(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        $inspector_id = $req->input('inspector_id');
        $user_id = \Auth::user()->id;
        try {
            $where = array(
                'report_id' => $monitoring_id,
                'inspector_id' => $inspector_id
            );
            $prev_data = getPreviousRecords('school_monitoring_inspectors', $where);
            $res = deleteRecord('school_monitoring_inspectors', $prev_data, $where, $user_id);
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getRecommendationMonitoringList(Request $req)
    {
        $stage = $req->input('stage_id');
        try {
            $qry = DB::table('school_monitoring_rpt')
                ->leftJoin('school_monitoring_summary', 'school_monitoring_rpt.id', '=', 'school_monitoring_summary.report_id')
                ->join('school_information', 'school_monitoring_rpt.school_id', '=', 'school_information.id')
                ->join('school_monitoring_types', 'school_monitoring_rpt.monitoring_type', '=', 'school_monitoring_types.id')
                ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                    $join->on('school_information.id', '=', 'school_contactpersons.school_id')
                        ->where('school_contactpersons.designation_id', '=', DB::raw(1));
                })
                ->select(DB::raw('school_monitoring_rpt.*,sum(school_monitoring_summary.total_indicated) as total_enrolled,sum(school_monitoring_summary.total_missing) as total_missing,school_monitoring_types.name as monitoring_type_name,
                                  school_information.name,school_contactpersons.full_names,school_contactpersons.telephone_no as head_telephone,school_contactpersons.mobile_no as head_mobile,school_contactpersons.email_address as head_email,
                                  school_information.district_id,school_information.code,school_information.email_address,school_information.school_type_id'))
                ->groupBy('school_monitoring_rpt.id')
                ->where('school_monitoring_rpt.stage', $stage);
            $results = $qry->get();
            $results = convertStdClassObjToArray($results);
            $results = decryptArray($results);
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
        }
        return response()->json($res);
    }

    public function getSchoolMonitoringBeneficiaries1(Request $req)
    {
        $school_id = $req->input('school_id');
        $monitoring_report_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('beneficiary_information')
                ->select('beneficiary_information.id', 'beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.dob', 'beneficiary_information.current_school_grade')
                ->where('school_id', $school_id)
                ->whereIn('enrollment_status', array(1, 5))
                ->whereNotIn('beneficiary_information.id', function ($query) use ($monitoring_report_id) {
                    $query->select(DB::raw('monitoring_missing_beneficiaries.girl_id'))
                        ->from('monitoring_missing_beneficiaries')
                        ->whereRaw('monitoring_missing_beneficiaries.report_id=' . $monitoring_report_id);
                });
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function getSchoolMonitoringBeneficiaries(Request $req)
    {
        $monitoring_report_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('monitoring_found_beneficiaries')
                ->join('beneficiary_information', 'beneficiary_information.id', '=', 'monitoring_found_beneficiaries.girl_id')
                ->select('beneficiary_information.id', 'beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.dob', 'beneficiary_information.current_school_grade', 'monitoring_found_beneficiaries.grade')
                ->where('report_id', $monitoring_report_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function getSchMonitoringMissingGirls(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('monitoring_missing_beneficiaries')
                ->join('beneficiary_information', 'monitoring_missing_beneficiaries.girl_id', '=', 'beneficiary_information.id')
                ->leftJoin('beneficiary_enrollement_statuses as t3', 't3.id', '=', 'beneficiary_information.enrollment_status')
                ->select('monitoring_missing_beneficiaries.id', 'monitoring_missing_beneficiaries.girl_id', 'beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.beneficiary_id', 'beneficiary_information.current_school_grade',
                    'monitoring_missing_beneficiaries.grade', 't3.name as enrollment_status_name', 'beneficiary_information.enrollment_status', 'monitoring_missing_beneficiaries.reason', 'monitoring_missing_beneficiaries.remark')
                ->where('report_id', $monitoring_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function addMissingBeneficiaries(Request $req)
    {
        $postdata = $req->all();
        $school_id = $postdata['school_id'];
        $monitoring_id = $postdata['monitoring_id'];
        unset($postdata['school_id']);
        unset($postdata['monitoring_id']);
        unset($postdata['_token']);
        $insertdata = array();
        $missing_girls_ids = array();
        try {
            foreach ($postdata as $key => $value) {
                $missing_girls_ids[] = array(
                    'id' => $postdata[$key]['id']
                );
                $insertdata [] = array(
                    'girl_id' => $postdata[$key]['id'],
                    'grade' => $postdata[$key]['grade'],
                    'report_id' => $postdata[$key]['report_id']
                );
            }
            $missing_girls_ids = convertAssArrayToSimpleArray($missing_girls_ids, 'id');
            DB::table('monitoring_missing_beneficiaries')->insert($insertdata);
            DB::table('monitoring_found_beneficiaries')
                ->whereIn('girl_id', $missing_girls_ids)
                ->delete();
            $this->updateSchoolMonitoringSummary($monitoring_id);
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries added Successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function removeGirlFromMissingBeneficiariesList(Request $req)
    {
        $record_id = $req->input('record_id');
        $girl_id = $req->input('girl_id');
        $grade = $req->input('grade');
        $monitoring_id = $req->input('monitoring_id');
        $school_id = $req->input('school_id');
        $user_id = \Auth::user()->id;
        try {
            $found_insert = array(
                'report_id' => $monitoring_id,
                'girl_id' => $girl_id,
                'grade' => $grade,
                'reason' => '',
                'remark' => '',
                'verified' => 1,
                'created_at' => Carbon::now(),
                'created_by' => $user_id
            );
            DB::table('monitoring_found_beneficiaries')
                ->insert($found_insert);
            DB::table('monitoring_missing_beneficiaries')
                ->where('id', $record_id)
                ->delete();
            $this->updateSchoolMonitoringSummary($monitoring_id);
            $res = array(
                'success' => true,
                'message' => 'Beneficiary removed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function initializeSchoolMonitoringSummary($monitoring_id, $school_id)
    {
        try {
            //summary
            DB::table('school_monitoring_summary')->where('report_id', $monitoring_id)->delete();
            $totals_enrolled = DB::table('beneficiary_information')
                ->select(DB::raw('SUM(IF(current_school_grade = 7, 1, 0)) AS grade_7,
                              SUM(IF(current_school_grade = 8, 1, 0)) AS grade_8,
                              SUM(IF(current_school_grade = 9, 1, 0)) AS grade_9,
                              SUM(IF(current_school_grade = 10, 1, 0)) AS grade_10,
                              SUM(IF(current_school_grade = 11, 1, 0)) AS grade_11,
                              SUM(IF(current_school_grade = 12, 1, 0)) AS grade_12'))
                ->where('school_id', $school_id)
                ->whereIn('enrollment_status', array(1, 5))
                ->get();
            for ($i = 7; $i <= 12; $i++) {
                $grade = 'grade_' . $i;
                $summary_params_insert[] = array(
                    'report_id' => $monitoring_id,
                    'grade' => $i,
                    'total_indicated' => $totals_enrolled[0]->$grade,
                    'total_missing' => 0
                );
            }
            DB::table('school_monitoring_summary')
                ->insert($summary_params_insert);
            //end summary
            //log found beneficiaries
            DB::table('monitoring_found_beneficiaries')->where('report_id', $monitoring_id)->delete();
            $qry = DB::table('beneficiary_information')
                ->select('beneficiary_information.id as girl_id', 'beneficiary_information.current_school_grade as grade')
                ->where('school_id', $school_id)
                ->whereIn('enrollment_status', array(1, 5));
            $data = $qry->get();
            $data->map(function ($datum) use ($monitoring_id) {
                $datum->report_id = $monitoring_id;
                return $datum;
            });
            $found_girls = convertStdClassObjToArray($data);
            DB::table('monitoring_found_beneficiaries')->insert($found_girls);
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries added Successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function initializePaymentsSchoolMonitoringSummary($monitoring_id, $school_id, $enrollment_year, $term)
    {
        try {
            //summary
            DB::table('school_monitoring_summary')->where('report_id', $monitoring_id)->delete();
            $totals_enrolled = DB::table('beneficiary_information')
                ->join('beneficiary_enrollments as t2', 'beneficiary_information.id', '=', 't2.beneficiary_id')
                ->select(DB::raw('SUM(IF(t2.school_grade = 7, 1, 0)) AS grade_7,
                              SUM(IF(t2.school_grade = 8, 1, 0)) AS grade_8,
                              SUM(IF(t2.school_grade = 9, 1, 0)) AS grade_9,
                              SUM(IF(t2.school_grade = 10, 1, 0)) AS grade_10,
                              SUM(IF(t2.school_grade = 11, 1, 0)) AS grade_11,
                              SUM(IF(t2.school_grade = 12, 1, 0)) AS grade_12'))
                ->where('t2.school_id', $school_id)
                ->where(array('t2.year_of_enrollment' => $enrollment_year, 't2.term_id' => $term, 't2.is_validated' => 1))
                ->get();
            for ($i = 7; $i <= 12; $i++) {
                $grade = 'grade_' . $i;
                $summary_params_insert[] = array(
                    'report_id' => $monitoring_id,
                    'grade' => $i,
                    'total_indicated' => $totals_enrolled[0]->$grade,
                    'total_missing' => 0
                );
            }
            DB::table('school_monitoring_summary')
                ->insert($summary_params_insert);
            //end summary
            //log found beneficiaries
            DB::table('monitoring_found_beneficiaries')->where('report_id', $monitoring_id)->delete();
            $qry = DB::table('beneficiary_information')
                ->join('beneficiary_enrollments as t2', 'beneficiary_information.id', '=', 't2.beneficiary_id')
                ->select('beneficiary_information.id as girl_id', 't2.school_grade as grade')
                ->where('t2.school_id', $school_id)
                ->where(array('t2.year_of_enrollment' => $enrollment_year, 't2.term_id' => $term, 't2.is_validated' => 1));
            $data = $qry->get();
            $data->map(function ($datum) use ($monitoring_id) {
                $datum->report_id = $monitoring_id;
                return $datum;
            });
            $found_girls = convertStdClassObjToArray($data);
            DB::table('monitoring_found_beneficiaries')->insert($found_girls);
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries added Successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateSchoolMonitoringSummary($monitoring_id)
    {
        try {
            //summary
            $missings_found = DB::table('monitoring_missing_beneficiaries')
                ->select(DB::raw('SUM(IF(grade = 7, 1, 0)) AS missing_grade_7,
                              SUM(IF(grade = 8, 1, 0)) AS missing_grade_8,
                              SUM(IF(grade = 9, 1, 0)) AS missing_grade_9,
                              SUM(IF(grade = 10, 1, 0)) AS missing_grade_10,
                              SUM(IF(grade = 11, 1, 0)) AS missing_grade_11,
                              SUM(IF(grade = 12, 1, 0)) AS missing_grade_12'))
                ->where('report_id', $monitoring_id)
                ->get();
            for ($i = 7; $i <= 12; $i++) {
                $grade = 'missing_grade_' . $i;
                DB::table('school_monitoring_summary')
                    ->where('report_id', $monitoring_id)
                    ->where('grade', $i)
                    ->update(array('total_missing' => $missings_found[0]->$grade));
            }
            //end summary
            $res = array(
                'success' => true,
                'message' => 'Beneficiaries added Successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function submitMonitoringToDiffStage(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        $comment = $req->input('comment');
        $from_stage = $req->input('from_stage');
        $to_stage = $req->input('to_stage');
        try {
            DB::table('school_monitoring_rpt')
                ->where('id', $monitoring_id)
                ->update(array('stage' => $to_stage));
            $trans_report = array(
                'report_id' => $monitoring_id,
                'from_stage' => $from_stage,
                'to_stage' => $to_stage,
                'comment' => $comment,
                'author' => \Auth::user()->id
            );
            DB::table('monitoring_transitional_report')->insert($trans_report);
            $res = array(
                'success' => true,
                'message' => 'Report moved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSchMonitoringSummary(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        try {
            $results = DB::table('school_monitoring_summary')
                ->where('report_id', $monitoring_id)
                ->get();
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
        }
        return response()->json($res);
    }

    public function getSchoolMonitoringVerifiedGirls(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('monitoring_found_beneficiaries')
                ->join('beneficiary_information', 'monitoring_found_beneficiaries.girl_id', '=', 'beneficiary_information.id')
                ->leftJoin('beneficiary_enrollement_statuses as t3', 't3.id', '=', 'beneficiary_information.enrollment_status')
                ->select('monitoring_found_beneficiaries.id', 'monitoring_found_beneficiaries.grade', 'monitoring_found_beneficiaries.remark', 'beneficiary_information.first_name', 'beneficiary_information.last_name',
                    't3.name as enrollment_status_name', 'beneficiary_information.enrollment_status', 'beneficiary_information.current_school_grade', 'beneficiary_information.beneficiary_id')
                ->where('monitoring_found_beneficiaries.report_id', $monitoring_id);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function saveVerifiedGirlsDetails(Request $req)
    {
        $post_data = $req->all();
        unset($post_data['_token']);
        try {
            foreach ($post_data as $key => $value) {
                $record_id = $post_data[$key]['id'];
                $remark = $post_data[$key]['remark'];
                $update_params = array(
                    'remark' => $remark
                );
                DB::table('monitoring_found_beneficiaries')
                    ->where('id', $record_id)
                    ->update($update_params);
            }
            $res = array(
                'success' => true,
                'message' => 'Details saved successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getSchoolMonitoringRegister(Request $req)
    {
        $monitoring_id = $req->input('monitoring_id');
        try {
            $missing_girls_qry = DB::table('monitoring_missing_beneficiaries as tm1')
                ->join('beneficiary_information as tm2', 'tm1.girl_id', '=', 'tm2.id')
                ->select(DB::raw('tm1.report_id,tm1.girl_id,tm1.grade,tm1.reason,tm1.remark,tm1.verified,tm2.beneficiary_id,
                                  CASE WHEN decrypt(tm2.first_name) IS NULL THEN first_name ELSE decrypt(tm2.first_name) END as first_name, CASE WHEN decrypt(tm2.last_name) IS NULL THEN last_name ELSE decrypt(tm2.last_name) END as last_name'))
                ->where('tm1.report_id', $monitoring_id);

            $verified_girls_qry = DB::table('monitoring_found_beneficiaries as tv1')
                ->join('beneficiary_information as tv2', 'tv1.girl_id', '=', 'tv2.id')
                ->select(DB::raw('tv1.report_id,tv1.girl_id,tv1.grade,tv1.reason,tv1.remark,tv1.verified,tv2.beneficiary_id,
                                  CASE WHEN decrypt(tv2.first_name) IS NULL THEN first_name ELSE decrypt(tv2.first_name) END as first_name, CASE WHEN decrypt(tv2.last_name) IS NULL THEN last_name ELSE decrypt(tv2.last_name) END as last_name'))
                ->where('tv1.report_id', $monitoring_id);

            $missing_girls = convertStdClassObjToArray($missing_girls_qry->get());
            $verified_girls = convertStdClassObjToArray($verified_girls_qry->get());
            $beneficiaries = array_merge($missing_girls, $verified_girls);

            $res = array(
                'success' => true,
                'results' => $beneficiaries,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function updateMonitoringRegister(Request $req)
    {
        $register_details = $req->input('register_details');
        $report_id = $req->input('monitoring_id');
        $school_id = $req->input('school_id');
        $register_details = json_decode($register_details);
        $user_id = \Auth::user()->id;
        $verified_girls = array();
        $missing_girls = array();
        $res = array();
        DB::transaction(function () use ($register_details, $report_id, $school_id, $user_id, $verified_girls, $missing_girls, &$res) {
            try {
                foreach ($register_details as $register_detail) {
                    $verified = $register_detail->verified;
                    $girl_id = $register_detail->girl_id;
                    $grade = $register_detail->grade;
                    $reason = $register_detail->reason;
                    $remark = $register_detail->remark;
                    if ($verified == 1 || $verified == true) {
                        $verified_girls[] = array(
                            'report_id' => $report_id,
                            'girl_id' => $girl_id,
                            'grade' => $grade,
                            'reason' => '',
                            'remark' => $remark,
                            'verified' => 1,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                    } else {
                        $missing_girls[] = array(
                            'report_id' => $report_id,
                            'girl_id' => $girl_id,
                            'grade' => $grade,
                            'reason' => $reason,
                            'remark' => $remark,
                            'verified' => 0,
                            'created_at' => Carbon::now(),
                            'created_by' => $user_id
                        );
                    }
                }
                DB::table('monitoring_missing_beneficiaries')
                    ->where('report_id', $report_id)
                    ->delete();
                DB::table('monitoring_found_beneficiaries')
                    ->where('report_id', $report_id)
                    ->delete();
                DB::table('monitoring_missing_beneficiaries')
                    ->insert($missing_girls);
                DB::table('monitoring_found_beneficiaries')
                    ->insert($verified_girls);
                $this->updateSchoolMonitoringSummary($report_id);
                $res = array(
                    'success' => true,
                    'message' => 'Register updated successfully!!'
                );
            } catch (\Exception $e) {
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }, 5);
        return response()->json($res);
    }

    public function sendSuspensionRequest(Request $req)
    {
        $sus_reason = $req->input('sus_reason');
        $system_reason = $req->input('system_reason');
        $sus_remark = $req->input('user_reason');
        $sus_girls = $req->input('selected');
        $sus_girls = json_decode($sus_girls);
        $user_id = \Auth::user()->id;
        $sus_request_params = array();
        $log_params = array();
        $girl_ids = array();
        try {
            foreach ($sus_girls as $sus_girl) {
                $sus_request_params[] = array(
                    'girl_id' => $sus_girl->girl_id,
                    'reason_id' => $sus_reason,
                    'system_reason' => $system_reason,
                    'user_reason' => $sus_remark,
                    'request_by' => $user_id,
                    'request_date' => Carbon::now(),
                    'stage' => 1,
                    'created_by' => $user_id,
                    'created_at' => Carbon::now()
                );
                $log_params[] = array(
                    'girl_id' => $sus_girl->girl_id,
                    'from_stage' => $sus_girl->enrollment_status,
                    'to_stage' => 5,
                    'reason' => $sus_remark,
                    'author' => $user_id,
                    'created_at' => Carbon::now(),
                    'created_by' => $user_id
                );
                $girl_ids[] = array(
                    'id' => $sus_girl->girl_id
                );
            }
            DB::table('beneficiaries_transitional_report')->insert($log_params);
            DB::table('suspension_requests')->insert($sus_request_params);
            DB::table('beneficiary_information')
                ->whereIn('id', $girl_ids)
                ->update(array('enrollment_status' => 5));
            $res = array(
                'success' => true,
                'message' => 'Request executed successfully!!'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getCurrentlyEnrolledGirls(Request $req)
    {
        $school_id = $req->input('school_id');
        try {
            $qry = DB::table('beneficiary_information')
                ->select('beneficiary_information.id', 'beneficiary_information.beneficiary_id', 'beneficiary_information.first_name', 'beneficiary_information.last_name', 'beneficiary_information.dob', 'beneficiary_information.current_school_grade')
                ->where('school_id', $school_id)
                ->where('enrollment_status', 1);
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $results = decryptArray($data);
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
        }
        return response()->json($res);
    }

    public function getSchoolsForMonitoring(Request $req)
    {
        $district_id = $req->input('district_id');
        $province_id = $req->input('province_id');
        $qry = DB::table('school_information as t1')
            ->join('beneficiary_information as q', 'q.school_id', '=', 't1.id')
            //->leftJoin('school_types', 'q.school_id', '=', 't1.id')
            /*  ->join('districts as t3', 't1.district_id', '=', 't3.id')
              ->join('provinces as t4', 't3.province_id', '=', 't4.id')*/
            ->leftJoin('school_contactpersons', function ($join) {//get head teacher details
                $join->on('t1.id', '=', 'school_contactpersons.school_id')
                    ->where('school_contactpersons.designation_id', '=', DB::raw(1));
            })
            ->select('school_contactpersons.full_names', 'school_contactpersons.telephone_no as head_telephone', 'school_contactpersons.mobile_no as head_mobile',
                'school_contactpersons.email_address as head_email',
                't1.*', 't1.id as school_id');

        if (isset($district_id) && $district_id != '') {
            $qry = $qry->where('t1.district_id', $district_id);
        }
        if (isset($province_id) && $province_id != '') {
            $qry = $qry->where('t1.province_id', $province_id);
        }
        $qry->groupBy('t1.id');
        $results = $qry->get();
        $res = array(
            'results' => $results
        );
        return response()->json($res);
    }

    public function getMonitoringTransitionalStages(Request $req)
    {
        $report_id = $req->input('monitoring_id');
        try {
            $qry = DB::table('monitoring_transitional_report')
                ->join('users', 'monitoring_transitional_report.author', '=', 'users.id')
                ->join('school_monitoring_stages as t1', 'monitoring_transitional_report.from_stage', '=', 't1.id')
                ->join('school_monitoring_stages as t2', 'monitoring_transitional_report.to_stage', '=', 't2.id')
                ->select('t1.name as from_stage_name', 't2.name as to_stage_name', 'monitoring_transitional_report.comment', 'monitoring_transitional_report.created_at as changes_date', 'users.first_name', 'users.last_name')
                ->where('monitoring_transitional_report.report_id', $report_id)
                ->orderBy('monitoring_transitional_report.id');
            $data = $qry->get();
            $data = convertStdClassObjToArray($data);
            $data = decryptArray($data);
            $res = array(
                'success' => true,
                'results' => $data,
                'message' => 'All is well'
            );
        } catch (\Exception $e) {
            $res = array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getPlannerDistricts()
    {
        try {
            $user_id = \Auth::user()->id;
            $districts = getUserDistricts($user_id);
            $results = DB::table('districts as t2')
                ->join('school_information as t1', 't1.district_id', '=', 't2.id')
                ->join('beneficiary_information as t3', 't3.school_id', '=', 't1.id')
                ->select(DB::raw("count(t3.id) as no_of_beneficiaries,t2.name as district_name, t1.name as school_name, t2.id as district_id,
                                  CONCAT_WS(' ',t1.code,t1.name) as school,CONCAT_WS(' ',t2.code,t2.name) as district"))
                ->where('t3.beneficiary_status', 4)
                ->where('t3.enrollment_status', 1)
                ->whereIn('t2.id', $districts)
                ->groupBy('t1.id')
                ->get();
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
        }
        return response()->json($res);
    }

    public function getPlannerSchools(Request $req)
    {
        $groupField = $req->input('groupField');
        $filter = $req->input('filter');
        $whereClauses = array();
        $filter_string = '';
        if (isset($filter)) {
            $filters = json_decode($filter);
            if ($filters != NULL) {
                foreach ($filters as $filter) {
                    switch ($filter->property) {
                        case 'beneficiary_id' :
                            $whereClauses[] = "t1.beneficiary_id like '%" . ($filter->value) . "%'";
                            break;
                        case 'fullname' :
                            $whereClauses[] = "decrypt(t1.first_name) like '%" . ($filter->value) . "%' OR decrypt(t1.last_name) like '%" . ($filter->value) . "%'";
                            break;
                        case 'enroll_status' :
                            $whereClauses[] = "t1.enrollment_status = '" . ($filter->value) . "'";
                            break;
                    }
                }
                $whereClauses = array_filter($whereClauses);
            }
            if (!empty($whereClauses)) {
                $filter_string = implode(' AND ', $whereClauses);
            }
        }
        $groupTable = 't1';
        $districtTable = 't2';
        if (isset($groupField) && $groupField != '') {
            if ($groupField == 1) {
                $groupTable = 't3';
                $districtTable = 't4';
            }
        }
        try {
            $user_id = \Auth::user()->id;
            $districts = getUserDistricts($user_id);
            $qry = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->join('beneficiary_enrollement_statuses as t5', 't1.enrollment_status', '=', 't5.id')
                ->select(DB::raw("t4.name school_district_name, t2.name as home_district_name, t3.name as school_name, t1.id, t1.beneficiary_id, t1.school_id," . $groupTable . ".district_id," . $districtTable . ".name as district_name,
                                  t5.name as enroll_status,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as fullname"))
                ->where('t1.beneficiary_status', 4)
                ->whereIn($groupTable . '.district_id', $districts);
            if ($filter_string != '') {
                $qry->whereRAW($filter_string);
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
        }
        return response()->json($res);
    }

    public function getPlannerExternalBeneficiaries()
    {
        try {
            $user_id = \Auth::user()->id;
            $districts = getUserDistricts($user_id);
            $results = DB::table('beneficiary_information as t1')
                ->join('districts as t2', 't1.district_id', '=', 't2.id')
                ->join('school_information as t3', 't1.school_id', '=', 't3.id')
                ->join('districts as t4', 't3.district_id', '=', 't4.id')
                ->select(DB::raw("t4.id as school_district_id,t4.name as school_district_name,t2.name as home_district_name, t3.name as school_name, t1.id, t1.beneficiary_id, t1.school_id,t1.district_id,
                                  CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as fullname"))
                ->whereIn('t1.district_id', $districts)
                ->whereNotIn('t3.district_id', $districts)
                ->get();
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
        }
        return response()->json($res);
    }

    public function getPlannerSchoolPayments(Request $req)
    {
        $year = $req->input('year');
        $term = $req->input('term');
        try {
            $user_id = \Auth::user()->id;
            $districts = getUserDistricts($user_id);
            $qry = DB::table('school_information as t1')
                ->join('districts as t3', 't1.district_id', '=', 't3.id')
                ->join('beneficiary_enrollments as t2', function ($join) use ($term, $year) {
                    $join->on('t2.school_id', '=', 't1.id')
                        ->on('t2.year_of_enrollment', '=', DB::raw($year))
                        ->on('t2.term_id', '=', DB::raw($term));
                })
                ->select(DB::raw('t1.id,t1.district_id,sum(t2.school_fees) as school_fees,t1.name as school_name,t3.name as district_name'))
                ->where('t2.is_validated', 1)
                ->whereIn('t1.district_id', $districts)
                ->groupBy('t1.id');
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
        }
        return response()->json($res);
    }

    //feb 2026
    //created by Jose, for school bank info view
    public function getSchoolBankDetails(Request $request)
    {
        try {

            $schoolDistrict = $request->query('school_district');

            $query = DB::table('school_information as t1')
                ->leftJoin('districts as t4', 't1.district_id', '=', 't4.id')
                ->leftJoin('school_bankinformation as t3', 't1.id', '=', 't3.school_id')
                ->leftJoin('bank_details as t6', 't3.bank_id', '=', 't6.id')
                ->leftJoin('bank_branches as t5', 't3.branch_name', '=', 't5.id')
                ->leftJoin('district_bank_accounts as t2_eg', function ($join) {
                    $join->on('t1.district_id', '=', 't2_eg.district_id')
                        ->where('t2_eg.bank_account_type_id', 2);
                })
                ->leftJoin('district_bank_accounts as t2_af', function ($join) {
                    $join->on('t1.district_id', '=', 't2_af.district_id')
                        ->where('t2_af.bank_account_type_id', 3);
                })
                ->select([
                    // SCHOOL BASIC INFO
                    't1.name as school_name',
                    't1.code as school_emis',
                    't4.name as school_district',

                    // SCHOOL FEES BANK
                    't6.name as school_fees_bank_name',
                    DB::raw("IFNULL(decrypt(t3.account_no), '') as school_fees_bank_account"),
                    't5.name as school_fees_bank_branch',
                    't3.sort_code as school_fees_bank_code',

                    // EDUCATION GRANT BANK
                    't2_eg.bank_name as education_grant_bank_name',
                    't2_eg.account_number as education_grant_bank_account',
                    't2_eg.branch_name as education_grant_bank_branch',
                    't2_eg.sort_code as education_grant_bank_code',

                    // ADMIN FEE BANK
                    't2_af.bank_name as administrative_fee_bank_name',
                    't2_af.account_number as administrative_fee_bank_account',
                    't2_af.branch_name as administrative_fee_bank_branch',
                    't2_af.sort_code as administrative_fee_bank_code',
                ]);

            // Apply filter if provided
            if (!empty($schoolDistrict)) {
                $query->where('t4.name', $schoolDistrict);
            }

            $results = $query->get();

            return response()->json([
                'success' => true,
                'count'   => $results->count(),
                'data'    => $results
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch school bank details.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
