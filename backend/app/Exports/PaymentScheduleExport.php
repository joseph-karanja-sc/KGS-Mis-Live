<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PaymentScheduleExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    protected $data,$details_info;

    public function __construct(array $data, $details_info)
    {
        $this->data = $data;
        $this->details_info = $details_info;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            array('PAYMENT SCHEDULE FOR PAYMENT REQUEST REF NO: ' . $this->details_info->payment_ref_no . ' OF ' . $this->details_info->payment_year),
            array(
                'School#',
                'School Code',
                'School Name',
                'Bank Name',
                'Account Number',
                'Branch Name',
                'Sort Code',
                'Indicated Amount(K)',
                'Suspense Amount(K)',
                'Payable Amount(K)',
            )
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                //todo: A1:J1
                $event->sheet->mergeCells('A1:J1');
                $event->sheet->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getRowDimension('1')->setOutlineLevel(1);
                $event->sheet->getStyle('A1:J1')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A1:J1')->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                //todo: A2:J2
                $event->sheet->getStyle('A2:J2')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'left',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A2:J2')->getFont()->applyFromArray(
                    array(
                        'name' => 'Arial',
                        'bold' => true,
                        'size'=>10
                    )
                );
                $event->sheet->getStyle('A2:J2')->getAlignment()->setWrapText(true);
                //$event->sheet->getColumnDimension('B')->setWidth(15);//remove ShouldAutoSize to use this line
            }
        ];
    }
}
