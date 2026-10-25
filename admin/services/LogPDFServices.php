<?php

declare(strict_types=1);

namespace Admin\Services;

require_once $_SERVER['DOCUMENT_ROOT']
. '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Views/pdf/template.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class LogPDFService
{
    private Dompdf $dompdf;

    public function __construct()
    {
        $options = new Options();

        $options->set(
            'isRemoteEnabled',
            true
        );

        $this->dompdf =
            new Dompdf($options);
    }

    public function generate(
        string $title,
        array $report
    ): string {
        
        /*
|--------------------------------------------------------------------------
| DIRECTORY
|--------------------------------------------------------------------------
*/

$directory =

    $_SERVER['DOCUMENT_ROOT']

    .

    '/generated_reports/log_reports';
    
    if(
    !is_dir($directory)
){

    mkdir(
        $directory,
        0777,
        true
    );
}

        /*
        |--------------------------------------------------------------------------
        | BODY HTML
        |--------------------------------------------------------------------------
        */

        $bodyContent = '

            <div class="section">

                <div class="section-title">

                    Executive Summary

                </div>

                <div>

                    '.(
                        $report['summary']
                        ??
                        'No summary available.'
                    ).'

                </div>

            </div>

            <div class="section">

                <div class="section-title">

                    Analysis

                </div>
        ';

        /*
        |--------------------------------------------------------------------------
        | ANALYSIS
        |--------------------------------------------------------------------------
        */

        if(
            !empty($report['analysis'])
        ){

            foreach(
                $report['analysis']
                as $analysis
            ){

                $bodyContent .= '

                <p>

                    '.$analysis.'

                </p>

                ';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSIGHTS
        |--------------------------------------------------------------------------
        */

        $bodyContent .= '

            </div>

            <div class="section">

                <div class="section-title">

                    Key Insights

                </div>

                <ul>
        ';

        if(
            !empty($report['insights'])
        ){

            foreach(
                $report['insights']
                as $insight
            ){

                $bodyContent .= '

                <li>

                    '.$insight.'

                </li>

                ';
            }
        }

        $bodyContent .= '

                </ul>

            </div>

            <div class="section">

                <div class="section-title">

                    Recommendations

                </div>

                <ul>
        ';

        /*
        |--------------------------------------------------------------------------
        | RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        if(
            !empty($report['recommendations'])
        ){

            foreach(
                $report['recommendations']
                as $recommendation
            ){

                $bodyContent .= '

                <li>

                    '.$recommendation.'

                </li>

                ';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | RENDER PDF
        |--------------------------------------------------------------------------
        */

        $html = renderPdfHtml(
            title: $title,
            bodyContent: $bodyContent,
            orgSub: 'AI Generated Operational Report',
            meta: ['Report Type: System Activity Logs', 'Generated: ' . date('F d, Y h:i A')]
        );

        /*
        |--------------------------------------------------------------------------
        | LOAD HTML
        |--------------------------------------------------------------------------
        */

        $this->dompdf->loadHtml($html);

        /*
        |--------------------------------------------------------------------------
        | PAPER
        |--------------------------------------------------------------------------
        */

        $this->dompdf->setPaper(
            'A4',
            'portrait'
        );

        /*
        |--------------------------------------------------------------------------
        | RENDER
        |--------------------------------------------------------------------------
        */

        $this->dompdf->render();

        /*
        |--------------------------------------------------------------------------
        | FILE NAME
        |--------------------------------------------------------------------------
        */

        $safeTitle = preg_replace(
            '/[^A-Za-z0-9]/',
            '_',
            $title
        );

        $filename =

            $safeTitle
            .
            '_'
            .
            time()
            .
            '.pdf';

        /*
        |--------------------------------------------------------------------------
        | PATH
        |--------------------------------------------------------------------------
        */

        $path =

            $directory
            .
            '/'
            .
            $filename;

        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        file_put_contents(

            $path,

            $this->dompdf->output()
        );

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

return

    '/generated_reports/log_reports/'
    .
    $filename;
    }
}