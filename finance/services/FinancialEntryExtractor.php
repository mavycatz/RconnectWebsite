<?php

namespace Finance\Services;

class FinancialEntryExtractor
{
    public function extractRows(
        array $rows
    ): array {

        $entries = [];

        if(
            empty($rows)
        ){

            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | REMOVE HEADER ROW
        |--------------------------------------------------------------------------
        */

        $headers =
            array_map(
                'strtolower',
                $rows[0]
            );

        unset($rows[0]);

        /*
        |--------------------------------------------------------------------------
        | PROCESS ROWS
        |--------------------------------------------------------------------------
        */

        foreach($rows as $row){

            $mapped =
                array_combine(
                    $headers,
                    $row
                );

            if(
                !$mapped
            ){
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | DETECT FIELDS
            |--------------------------------------------------------------------------
            */

            $entries[] = [

                'transaction_date' =>

                    $mapped['date']
                    ??
                    date('Y-m-d'),

                'category' =>

                    $mapped['category']
                    ??
                    'Other Expense',

                'amount' =>

                    (float)(
                        $mapped['amount']
                        ?? 0
                    ),

                'notes' =>

                    $mapped['notes']
                    ??
                    null,

                'bus_driver' =>

                    $mapped['driver']
                    ??
                    $mapped['bus']
                    ??
                    null
            ];
        }

        return $entries;
    }
}