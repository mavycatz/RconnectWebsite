<?php

namespace Finance\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;

class FinancialDocumentReader
{
    public function extract(
        string $filePath,
        string $fileType
    ): array {

        return match($fileType){

            'csv',
            'xlsx',
            'xls'

                =>

                $this->extractSpreadsheet(
                    $filePath
                ),

            'pdf'

                =>

                $this->extractPdf(
                    $filePath
                ),

            default => [

                'content' => '',
                'rows' => []
            ]
        };
    }

    /*
    |--------------------------------------------------------------------------
    | EXCEL / CSV
    |--------------------------------------------------------------------------
    */

    private function extractSpreadsheet(
        string $filePath
    ): array {

        $spreadsheet =
            IOFactory::load(
                $filePath
            );

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $rows =
            $sheet
                ->toArray();

        return [

            'content' =>

                json_encode(
                    $rows
                ),

            'rows' =>

                $rows
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    private function extractPdf(
        string $filePath
    ): array {

        $parser =
            new Parser();

        $pdf =
            $parser->parseFile(
                $filePath
            );

        $text =
            $pdf->getText();

        return [

            'content' =>
                $text,

            'rows' => []
        ];
    }
}