<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Handle payment verification details processing asynchronously
 * Offloads heavy school/beneficiary operations from request cycle
 * 
 * @package App\Jobs
 */
class SavePaymentVerificationDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    protected $validatedData;
    protected $userId;
    protected $dmsId;

    public $timeout = 600; // 10 minutes
    public $retries = 3;
    public $backoff = [30, 60, 300]; // 30s, 1m, 5m

    /**
     * Create job instance
     */
    public function __construct(int $batchId, array $validatedData, int $userId, int $dmsId)
    {
        $this->batchId = $batchId;
        $this->validatedData = $validatedData;
        $this->userId = $userId;
        $this->dmsId = $dmsId;
        $this->onQueue('payment-processing');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            Log::info("Processing payment verification batch: {$this->batchId}");

            // Extract data
            $schoolId = $this->validatedData['school_id'];
            $termId = $this->validatedData['term_id'];
            $yearOfEnrollment = $this->validatedData['year_of_enrollment'];
            $runningAgencyId = $this->validatedData['running_agency_id'];

            // Process school details (fast operations)
            $this->updateSchoolDetails($schoolId);

            // Process school fees setup (potentially heavy)
            if ($runningAgencyId == 3) {
                $this->setupPrivateSchoolFees($schoolId, $termId);
            }

            // Update bank information (fast)
            $this->updateSchoolBankDetails($schoolId);
            $form_id = $this->validatedData['checklist_form_id'] ?? 0;
            // Add default beneficiaries (async within async - chunked)
            dispatch(new AddPaymentBeneficiariesJob(
                $this->batchId,
                $schoolId,
                $termId,
                $yearOfEnrollment,
                $form_id                
            ))->onQueue('payment-beneficiaries');

            Log::info("Payment verification batch {$this->batchId} processed successfully");

        } catch (\Exception $e) {
            Log::error("Error processing payment verification: {$e->getMessage()}", [
                'batch_id' => $this->batchId,
                'user_id' => $this->userId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Trigger retry
        }
    }

    /**
     * Update school contact and general information
     */
    private function updateSchoolDetails(int $schoolId): void
    {
        try {
            // Update headteacher info
            DB::table('school_contactpersons')->upsert([
                [
                    'school_id' => $schoolId,
                    'designation_id' => 1,
                    'full_names' => $this->validatedData['school_headteacher'],
                    'telephone_no' => $this->validatedData['headteacher_tel_no'],
                    'mobile_no' => $this->validatedData['headteacher_tel_no'],
                    'updated_at' => now()
                ]
            ], ['school_id', 'designation_id'], ['full_names', 'telephone_no', 'mobile_no', 'updated_at']);

            // Update guidance teacher info
            DB::table('school_contactpersons')->upsert([
                [
                    'school_id' => $schoolId,
                    'designation_id' => 2,
                    'full_names' => $this->validatedData['school_guidance_counselling_teacher'],
                    'telephone_no' => $this->validatedData['guidance_counselling_teacher_phone_number'],
                    'mobile_no' => $this->validatedData['guidance_counselling_teacher_phone_number'],
                    'updated_at' => now()
                ]
            ], ['school_id', 'designation_id'], ['full_names', 'telephone_no', 'mobile_no', 'updated_at']);

            // Update school information
            DB::table('school_information')
                ->where('id', $schoolId)
                ->update([
                    'district_id' => $this->validatedData['district_id'],
                    'cwac_id' => $this->validatedData['cwac_id'] ?? null,
                    'school_type_id' => $this->validatedData['school_type_id'],
                    'running_agency_id' => $this->validatedData['running_agency_id'],
                    'updated_at' => now()
                ]);

        } catch (\Exception $e) {
            Log::warning("Error updating school details for school {$schoolId}: {$e->getMessage()}");
            // Continue processing despite this error
        }
    }

    /**
     * Setup school fees for private schools
     * Uses batch insert for efficiency
     */
    private function setupPrivateSchoolFees(int $schoolId, int $termId): void
    {
        try {
            $currentYear = date('Y');
            $grades = [4, 5, 6, 7, 8, 9, 10, 11, 12];
            $enrollmentTypes = [1, 2, 3]; // Day, Boarder, Weekly
            $feesInserts = [];

            // Prepare all inserts at once
            foreach ($grades as $grade) {
                foreach ($enrollmentTypes as $enrollmentType) {
                    $feesInserts[] = [
                        'year' => $currentYear,
                        'school_enrollment_id' => $enrollmentType,
                        'school_id' => $schoolId,
                        'grade_id' => $grade,
                        'term_id' => $termId,
                        'term1_fees' => 0,
                        'term2_fees' => 0,
                        'term3_fees' => 0,
                        'updated_at' => now()
                    ];
                }
            }

            // Batch upsert (more efficient than individual updates)
            DB::table('school_feessetup')->upsert(
                $feesInserts,
                ['year', 'school_enrollment_id', 'school_id', 'grade_id'],
                ['term1_fees', 'term2_fees', 'term3_fees', 'updated_at']
            );

        } catch (\Exception $e) {
            Log::warning("Error setting up private school fees for school {$schoolId}: {$e->getMessage()}");
        }
    }

    /**
     * Update school bank information
     * Uses upsert for atomic operation
     */
    private function updateSchoolBankDetails(int $schoolId): void
    {
        try {
            // Update bank information
            DB::table('school_bankinformation')->upsert([
                [
                    'school_id' => $schoolId,
                    'bank_id' => $this->validatedData['bank_id'],
                    'account_no' => aes_encrypt($this->validatedData['account_no']),
                    'branch_name' => $this->validatedData['branch_name'],
                    'is_activeaccount' => 1,
                    'updated_at' => now()
                ]
            ], ['school_id'], ['bank_id', 'account_no', 'branch_name', 'is_activeaccount', 'updated_at']);

            // Update branch sort code
            DB::table('bank_branches')
                ->where('id', $this->validatedData['branch_name'])
                ->update([
                    'sort_code' => $this->validatedData['sort_code'],
                    'updated_at' => now()
                ]);

        } catch (\Exception $e) {
            Log::warning("Error updating bank details for school {$schoolId}: {$e->getMessage()}");
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Exception $exception): void
    {
        Log::error('SavePaymentVerificationDetailsJob permanently failed', [
            'batch_id' => $this->batchId,
            'exception' => $exception->getMessage()
        ]);

        // Update batch status to failed
        DB::table('payment_verificationbatch')
            ->where('id', $this->batchId)
            ->update([
                'status_id' => 99, // Or appropriate error status
                'error_message' => $exception->getMessage(),
                'updated_at' => now()
            ]);
    }
}
