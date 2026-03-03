<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BatchImportCustomTemplate implements ToArray, WithStartRow
{
    protected $batch_id;
    protected $stdTemplateFields;
    protected $customTemplateFields;

    public function __construct($batch_id, $stdTemplateFields, $customTemplateFields)
    {
        $this->batch_id = $batch_id;
        $this->stdTemplateFields = $stdTemplateFields;
        $this->customTemplateFields = $customTemplateFields;
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

    public function array(array $rows): void
    {
        $insertData = array();
        foreach ($rows as $row) {
            foreach ($this->stdTemplateFields as $stdTemplateField) {
                // $insertData[$stdTemplateField['dataindex']] = $row[$stdTemplateField['tabindex']];
                // $insertData['batch_id'] = $this->batch_id;                
                if(isset($row[$stdTemplateField['tabindex']])){
                    $insertData[$stdTemplateField['dataindex']] = $row[$stdTemplateField['tabindex']];
                    $insertData['batch_id'] = $this->batch_id;
                }
            }
            $mainTempId = insertReturnID('beneficiary_master_info', $insertData);
            if (is_numeric($mainTempId)) {
                if (!is_null($this->customTemplateFields) && count($this->customTemplateFields) > 0) {
                    foreach ($this->customTemplateFields as $customTemplateField) {
                        $params = array(
                            'main_temp_id' => $mainTempId,
                            'field_id' => $customTemplateField->id,
                            'value' => $row[$customTemplateField->tabindex],
                            'batch_id' => $this->batch_id
                        );
                        DB::table('temp_additional_fields_values')->insert($params);
                    }
                }
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }

}
