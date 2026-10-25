<?php

namespace Finance\Controllers;

use Finance\Models\FinancialUploadModel;
use Finance\Services\AIFinancialService;
use Finance\Services\FinancialDocumentReader;
use Finance\Services\NotificationService;
use App\Models\User;

class FinancialUploadController
{
    public function upload(): void
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | MODEL + SERVICES
            |--------------------------------------------------------------------------
            */

            $model =
                new FinancialUploadModel();

            $aiService =
                new AIFinancialService();

            $documentReader =
                new FinancialDocumentReader();

            /*
            |--------------------------------------------------------------------------
            | MANUAL FORM DATA
            |--------------------------------------------------------------------------
            */

            $category =
                trim(
                    $_POST['financial_category']
                    ?? ''
                );

            $date =
                trim(
                    $_POST['transaction_date']
                    ?? ''
                );

            $amount =
                (float)(
                    $_POST['amount']
                    ?? 0
                );

            $busDriver =
                trim(
                    $_POST['bus_driver']
                    ?? ''
                );

            $notes =
                trim(
                    $_POST['notes']
                    ?? ''
                );

            $importMode =
                trim(
                    $_POST['import_mode']
                    ?? 'append'
                );

            $dataSource =
                trim(
                    $_POST['data_source']
                    ?? 'Manual Entry'
                );

            /*
            |--------------------------------------------------------------------------
            | FILE DETECTION
            |--------------------------------------------------------------------------
            */

            $uploadedFile = null;

            if(
                !empty(
                    $_FILES['financial_excel']['name']
                )
            ){

                $uploadedFile =
                    $_FILES['financial_excel'];
            }

            elseif(
                !empty(
                    $_FILES['financial_pdf']['name']
                )
            ){

                $uploadedFile =
                    $_FILES['financial_pdf'];
            }

            /*
            |--------------------------------------------------------------------------
            | FILE VARIABLES
            |--------------------------------------------------------------------------
            */

            $storedName = null;

            $filePath = null;

            $fileType = null;

            $originalName = null;

            $documentContent = '';

            $documentRows = [];

            /*
            |--------------------------------------------------------------------------
            | HANDLE FILE UPLOAD
            |--------------------------------------------------------------------------
            */

            if($uploadedFile){

                /*
                |--------------------------------------------------------------------------
                | VALIDATE FILE
                |--------------------------------------------------------------------------
                */

                if(
                    $uploadedFile['error'] !== 0
                ){

                    throw new \Exception(
                        'Upload failed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ALLOWED TYPES
                |--------------------------------------------------------------------------
                */

                $allowedExtensions = [

                    'xlsx',
                    'xls',
                    'csv',
                    'pdf'
                ];

                $extension =
                    strtolower(

                        pathinfo(

                            $uploadedFile['name'],

                            PATHINFO_EXTENSION
                        )
                    );

                if(
                    !in_array(
                        $extension,
                        $allowedExtensions
                    )
                ){

                    throw new \Exception(
                        'Invalid file type.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | UPLOAD DIRECTORY
                |--------------------------------------------------------------------------
                */

                $uploadDir =

                    dirname(__DIR__, 2)

                    .

                    '/public/uploads/financial_reports/';

                if(
                    !is_dir($uploadDir)
                ){

                    mkdir(

                        $uploadDir,

                        0777,

                        true
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | FILE NAMES
                |--------------------------------------------------------------------------
                */

                $originalName =
                    $uploadedFile['name'];

                $storedName =

                    uniqid(
                        'financial_',
                        true
                    )

                    .

                    '_'

                    .

                    preg_replace(

                        '/[^a-zA-Z0-9._-]/',

                        '',

                        basename($originalName)
                    );

                $targetPath =

                    $uploadDir

                    .

                    $storedName;

                /*
                |--------------------------------------------------------------------------
                | MOVE FILE
                |--------------------------------------------------------------------------
                */

                if(
                    !move_uploaded_file(
                        $uploadedFile['tmp_name'],
                        $targetPath
                    )
                ){

                    throw new \Exception(
                        'Failed to move uploaded file.'
                    );
                }

                $filePath =
                    $targetPath;

                $fileType =
                    $extension;

                /*
                |--------------------------------------------------------------------------
                | EXTRACT DOCUMENT CONTENT
                |--------------------------------------------------------------------------
                */

                $documentData =
                    $documentReader->extract(
                        $filePath,
                        $fileType
                    );

                $documentContent =
                    $documentData['content']
                    ?? '';

                $documentRows =
                    $documentData['rows']
                    ?? [];
            }

            /*
            |--------------------------------------------------------------------------
            | PREPARE AI DATA
            |--------------------------------------------------------------------------
            */

            $financialData = [

                'category' =>
                    $category,

                'transaction_date' =>
                    $date,

                'amount' =>
                    $amount,

                'bus_driver' =>
                    $busDriver,

                'notes' =>
                    $notes,

                'import_mode' =>
                    $importMode,

                'data_source' =>
                    $dataSource,

                'original_name' =>
                    $originalName,

                'file_type' =>
                    $fileType,

                'document_content' =>
                    $documentContent,

                'document_rows' =>
                    $documentRows
            ];

            /*
            |--------------------------------------------------------------------------
            | AI ANALYSIS
            |--------------------------------------------------------------------------
            */

            $aiAnalysis =

                $aiService
                    ->analyzeFinancialReport(
                        $financialData
                    );

            /*
            |--------------------------------------------------------------------------
            | SAVE DATABASE
            |--------------------------------------------------------------------------
            */

            $model->save([

                'category' =>
                    $category,

                'transaction_date' =>
                    $date,

                'amount' =>
                    $amount,

                'bus_driver' =>
                    $busDriver,

                'notes' =>
                    $notes,

                'import_mode' =>
                    $importMode,

                'data_source' =>
                    $dataSource,

                'original_name' =>
                    $originalName,

                'stored_name' =>
                    $storedName,

                'file_path' =>
                    $filePath,

                'file_type' =>
                    $fileType,

                'processing_status' =>
                    'completed',

                'ai_summary' =>
                    $aiAnalysis['summary']
                    ?? null,

                'ai_category' =>
                    $aiAnalysis['category']
                    ?? null,

                'ai_insights' =>
                    json_encode(
                        $aiAnalysis['insights']
                        ?? []
                    ),

                'ai_risks' =>
                    json_encode(
                        $aiAnalysis['risks']
                        ?? []
                    ),

                'ai_recommendations' =>
                    json_encode(
                        $aiAnalysis['recommendations']
                        ?? []
                    ),

                'ai_processed_at' =>
                    date(
                        'Y-m-d H:i:s'
                    )
            ]);

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $userModel = new User();
            $financeUsers = $userModel->getByRole('finance_manager');
            $fileName = $originalName ?? ($storedName ?? '');
            foreach ($financeUsers as $fu) {
                NotificationService::create(
                    (int) $fu['id'],
                    'Financial Report Uploaded',
                    ($originalName ?? 'Report') . ' was uploaded and analyzed.',
                    'success'
                );
            }

            $_SESSION['success'] =

                'Financial report uploaded and analyzed successfully.';

            header(
                'Location: '
                .
                $_SERVER['HTTP_REFERER']
            );

            exit;

        }catch(\Throwable $e){

            /*
            |--------------------------------------------------------------------------
            | ERROR LOG
            |--------------------------------------------------------------------------
            */

            file_put_contents(

                __DIR__
                .
                '/financial_upload_error.log',

                date('Y-m-d H:i:s')

                .

                ' | '

                .

                $e->getMessage()

                .

                PHP_EOL,

                FILE_APPEND
            );

            $_SESSION['error'] =
                $e->getMessage();

            header(
                'Location: '
                .
                $_SERVER['HTTP_REFERER']
            );

            exit;
        }
    }
}