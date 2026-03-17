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
 * Process beneficiary images asynchronously
 * Designed to handle high-concurrency scenarios (1000+ concurrent users)
 * 
 * @package App\Jobs
 */
class ProcessBeneficiaryImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $beneficiaries;
    public $timeout = 300; // 5 minutes timeout
    public $retries = 2;
    public $backoff = [10, 60]; // Exponential backoff

    /**
     * Create a new job instance
     *
     * @param array $beneficiaries
     */
    public function __construct(array $beneficiaries)
    {
        $this->beneficiaries = $beneficiaries;
        $this->onQueue('image-processing'); // Use dedicated queue
    }

    /**
     * Execute the job
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $publicPath = public_path();
            $timestamp = time();
            $microseconds = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT); 
            $online_url = "https:\\kgsmis.edu.gov.zm\kgs_mislive\backend\public";                     
            foreach ($this->beneficiaries as $beneficiaryData) {
                $beneficiary = (array) $beneficiaryData;                   
                // foreach ($this->beneficiaries as $beneficiaryData) {
                //     $beneficiary = (object) $beneficiaryData;
                try {
                    $updates = [];
                    // Process images concurrently-safe
                    if (!empty($beneficiary['beneficiary_image'])) {
                        try {
                            $filename = "img_{$beneficiary['id']}_{$timestamp}_{$microseconds}.png";
                            $filepath = $publicPath . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
                            $online_path = $$online_url . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $filename;
                            $this->saveBase64Image($beneficiary['beneficiary_image'], $filepath);
                            $updates['image_url'] = $filename;
                            $this->recordImage($beneficiary['school_id'], $beneficiary['id'], 1, $online_path);
                        } catch (\Exception $e) {
                            Log::warning("Image processing failed for beneficiary {$beneficiary['id']}: {$e->getMessage()}");
                        }
                    }

                    // Process consent form
                    if (!empty($beneficiary['disclaimer_form'])) {
                        try {
                            $filename = "consent_{$beneficiary['id']}_{$timestamp}_{$microseconds}.png";
                            $filepath = $publicPath . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'consentforms' . DIRECTORY_SEPARATOR . $filename;
                            $online_path = $online_url . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'consentforms' . DIRECTORY_SEPARATOR . $filename;
                            $this->saveBase64Image($beneficiary['disclaimer_form'], $filepath);
                            $updates['consentform_url'] = $filename;
                            $this->recordImage($beneficiary['school_id'], $beneficiary['id'], 3, $online_path);
                        } catch (\Exception $e) {
                            Log::warning("Consent form processing failed for beneficiary {$beneficiary['id']}: {$e->getMessage()}");
                        }
                    }

                    // Process signature
                    if (!empty($beneficiary['signature'])) {
                        try {
                            $filename = "signature_{$beneficiary['id']}_{$timestamp}_{$microseconds}.png";
                            $filepath = $publicPath . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR . $filename;
                            $online_path = $online_url . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR . $filename;
                            $this->saveBase64Image($beneficiary['signature'], $filepath);
                            $updates['signature_url'] = $filename;
                            $this->recordImage($beneficiary['school_id'], $beneficiary['id'], 2, $online_path);
                        } catch (\Exception $e) {
                            Log::warning("Signature processing failed for beneficiary {$beneficiary['id']}: {$e->getMessage()}");
                        }
                    }

                    // Update record only if processing succeeded
                    if (!empty($updates)) {
                        $updates['images_converted'] = 1;
                        $updates['updated_at'] = now();
                        DB::table('beneficiary_payresponses_stagin')
                            ->where('id', $beneficiary['id'])
                            ->update($updates);
                    }

                } catch (\Exception $e) {
                    Log::error("Critical error processing beneficiary {$beneficiary['id']}: {$e->getMessage()}");
                    // Continue processing other beneficiaries on error
                }
            }

        } catch (\Exception $e) {
            Log::error('ProcessBeneficiaryImagesJob failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Save base64 image with disk space checks
     *
     * @param string $base64Data
     * @param string $filepath
     * @return void
     * @throws \Exception
     */
    private function saveBase64Image(string $base64Data, string $filepath): void
    {
        $directory = dirname($filepath);

        // Thread-safe directory creation
        if (!is_dir($directory)) {
            $lockFile = storage_path('logs/.mkdir_' . md5($directory) . '.lock');
            $lock = fopen($lockFile, 'w');

            if (flock($lock, LOCK_EX | LOCK_NB)) { // Non-blocking to prevent deadlocks
                try {
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    @unlink($lockFile);
                }
            }
        }

        // Decode with strict validation
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            throw new \Exception('Invalid base64 data');
        }

        // Size and disk space validation
        $maxSize = 10 * 1024 * 1024; // 10MB
        if (strlen($imageData) > $maxSize) {
            throw new \Exception('Image exceeds size limit');
        }

        // Check available disk space (leave 100MB buffer)
        $freeSpace = disk_free_space($directory);
        if ($freeSpace < (strlen($imageData) + (100 * 1024 * 1024))) {
            throw new \Exception('Insufficient disk space');
        }

        // Save image
        try {
            $image = \Image::make($imageData);
            $image->save($filepath, 80); // 80% quality for compression
        } catch (\Exception $e) {
            throw new \Exception("Image save failed: {$e->getMessage()}");
        }
    }

    /**
     * Record image with batch insert support
     *
     * @param int $schoolId
     * @param int $beneficiaryId
     * @param int $imageType
     * @param string $imageName
     * @return void
     */
    private function recordImage(int $schoolId, int $beneficiaryId, int $imageType, string $imageName): void
    {
        DB::table('beneficiary_images_staging')->insertOrIgnore([
            'school_id' => $schoolId,
            'beneficiary_id' => $beneficiaryId,
            'image_type' => $imageType,
            'image_name' => $imageName,
            'created_at' => now()
        ]);
    }

    /**
     * Handle job failure
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception): void
    {
        Log::error('ProcessBeneficiaryImagesJob permanently failed', [
            'exception' => $exception->getMessage(),
            'beneficiaries_count' => count($this->beneficiaries)
        ]);
    }
}
