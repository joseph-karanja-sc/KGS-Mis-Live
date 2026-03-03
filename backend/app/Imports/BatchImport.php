<?php

namespace App\Imports;

use App\Modules\IdentificationEnrollment\Entities\BeneficiaryMasterInfo;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException;
use mysql_xdevapi\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BatchImport implements ToModel, WithStartRow, WithBatchInserts, WithChunkReading, WithEvents
{
    protected $batch_id;
    protected $stdTemplateFields;
    use Importable, RegistersEventListeners;

    public function __construct($batch_id, $stdTemplateFields)
    {
        $this->batch_id = $batch_id;
        $this->stdTemplateFields = $stdTemplateFields;
    }

    public static function beforeImport(BeforeImport $event)
    {
        $max_upload = Config('constants.max_excel_upload');
        $sheetDetails = $event->reader->getTotalRows();
        $noOfSheets = count($sheetDetails);
        $noOfRecords = array_values($sheetDetails)[0];
        if ($noOfSheets > 1) {
            throw new \Exception('The uploaded file has ' . $noOfSheets . ' sheets, make sure the file has only ONE sheet!!');
        }
        if ($noOfRecords > $max_upload) {
            throw new \Exception('The uploaded file has ' . $noOfRecords . ' records, which exceeds the maximum allowed ' . $max_upload . ' records!!');
        }
    }

    public function model(array $row)
    {
        $insertData = array();
        foreach ($this->stdTemplateFields as $key => $stdTemplateField) {
            $insertData[$stdTemplateField['dataindex']] = $row[$stdTemplateField['tabindex']];
            $insertData['batch_id'] = $this->batch_id;
        }
        return new BeneficiaryMasterInfo(
            $insertData
        );
    }

    public function startRow(): int
    {
        return 2;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

}
