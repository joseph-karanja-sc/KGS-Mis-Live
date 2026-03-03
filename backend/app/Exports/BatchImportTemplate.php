<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BatchImportTemplate implements FromArray, WithHeadings
{
    protected $headings;

    public function __construct(array $headings)
    {
        $this->headings = $headings;
    }

    public function array():array
    {
        return array();
    }

    public function headings(): array
    {
        return $this->headings;
    }

}
