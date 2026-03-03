<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

class GenericExporter implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    protected $data;
    protected $headings;
    protected $title;

    public function __construct(array $data,$headings,$title)
    {
        $this->data = $data;
        $this->headings = $headings;
        $this->title = $title;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            array('KGS MIS SYSTEM EXPORTED REPORT [ '.$this->title.'-'.date('d/m/y H:i:s').' ]'),
            $this->headings
            //$this->returnHeadings()
        ];
    }

    public function returnHeadings()
    {
        return array_keys($this->data[0]);
    }

    public function registerEvents(): array
    {
        $maxAlpha=excelNumberToAlpha(count($this->returnHeadings()),'');
        $row1=$maxAlpha.'1';
        $row2=$maxAlpha.'2';
        return [
            AfterSheet::class => function (AfterSheet $event) use($row1,$row2) {
                //todo: Row 1
                $event->sheet->mergeCells('A1:'.$row1);
                $event->sheet->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getRowDimension('1')->setOutlineLevel(1);
                $event->sheet->getStyle('A1:'.$row1)->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A1:'.$row1)->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                //todo: Row 2
                $event->sheet->getStyle('A2:'.$row2)->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'left',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A2:'.$row2)->getFont()->applyFromArray(
                    array(
                        'name' => 'Arial',
                        'bold' => true,
                        'size'=>9
                    )
                );
                $event->sheet->getStyle('A2:'.$row2)->getAlignment()->setWrapText(true);
            }
        ];
    }

}
