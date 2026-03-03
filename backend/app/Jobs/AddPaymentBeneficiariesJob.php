<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handle adding default payment beneficiaries asynchronously
 * Prevents long beneficiary queries from blocking main request
 *
 * @package App\Jobs
 */
class AddPaymentBeneficiariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $batchId;
    protected $schoolId;
    protected $termId;
    protected $yearOfEnrollment;
    protected $checklistFormId;

    public $timeout = 600; // 10 minutes
    public $retries = 3;
    public $backoff = [30, 60, 300];

    public function __construct(
        int $batchId,
        int $schoolId,
        int $termId,
        int $yearOfEnrollment,
        int $checklistFormId
    ) {
        $this->batchId = $batchId;
        $this->schoolId = $schoolId;
        $this->termId = $termId;
        $this->yearOfEnrollment = $yearOfEnrollment;
        $this->checklistFormId = $checklistFormId;
        $this->onQueue('payment-beneficiaries');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            Log::info("Adding beneficiaries for batch: {$this->batchId}");

            // Get eligible beneficiaries with optimized query
            $beneficiaries = $this->getEligibleBeneficiaries();

            if ($beneficiaries->isEmpty()) {
                Log::info("No eligible beneficiaries for batch {$this->batchId}");
                return;
            }

            // Process in chunks to avoid memory issues
            $beneficiaries->chunk(100)->each(function ($chunk) {
                $this->addBeneficiaryEnrollments($chunk);
            });

            Log::info("Successfully added beneficiaries for batch {$this->batchId}");

        } catch (\Exception $e) {
            Log::error("Error adding beneficiaries: {$e->getMessage()}", [
                'batch_id' => $this->batchId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get eligible beneficiaries with optimized query
     *
     * @return \Illuminate\Support\Collection
     */
    private function getEligibleBeneficiaries()
    {
        return DB::table('beneficiary_information as t1')
            ->select('t1.id', 't1.current_school_grade')
            ->where('t1.school_id', $this->schoolId)
            ->where('t1.beneficiary_status', 4)
            ->where('t1.enrollment_status', 1)
            ->where('t1.payment_eligible', 1)
            ->where(function ($query) {
                $query->where('t1.under_promotion', 0)
                    ->orWhereNull('t1.under_promotion');
            })->get();
    }

    /**
     * Add beneficiary enrollments in batch
     *
     * @param \Illuminate\Support\Collection $beneficiaries
     */
    private function addBeneficiaryEnrollments($beneficiaries): void
    {
        try {
            $enrollmentData = [];
            $currentYear = date('Y');
            foreach ($beneficiaries as $beneficiary) {
                // Check if enrollment already exists
                $exists = DB::table('beneficiary_enrollments')
                    ->where('beneficiary_id', $beneficiary->id)
                    ->where('batch_id', $this->batchId)
                    ->where('year_of_enrollment', $currentYear)
                    ->exists();
                if (!$exists) {
                    $enrollmentData[] = [
                        'batch_id' => $this->batchId,
                        'beneficiary_id' => $beneficiary->id,
                        'school_id' => $this->schoolId,
                        'year_of_enrollment' => $currentYear,
                        'term_id' => $this->termId,
                        'school_grade' => $beneficiary->current_school_grade,
                        'submission_status' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }

            // Batch insert
            if (!empty($enrollmentData)) {
                DB::table('beneficiary_enrollments')->insert($enrollmentData);
            }

        } catch (\Exception $e) {
            Log::warning("Error adding enrollments for batch {$this->batchId}: {$e->getMessage()}");
            // Continue despite individual batch errors
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Exception $exception): void
    {
        Log::error('AddPaymentBeneficiariesJob permanently failed', [
            'batch_id' => $this->batchId,
            'exception' => $exception->getMessage()
        ]);
    }
}
