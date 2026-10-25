<?php

namespace Finance\Models;

use App\Core\Database;

class FinancialUploadModel
{
    private $conn;

    public function __construct()
    {
        $this->conn =
            Database::get();
    }

    public function save(
        array $data
    ): bool {

        $stmt =
            $this->conn->prepare("

                INSERT INTO financial_uploads (

                    category,
                    transaction_date,
                    amount,
                    bus_driver,
                    notes,
                    import_mode,
                    data_source,

                    original_name,
                    stored_name,
                    file_path,
                    file_type,

                    processing_status

                )

                VALUES (

                    :category,
                    :transaction_date,
                    :amount,
                    :bus_driver,
                    :notes,
                    :import_mode,
                    :data_source,

                    :original_name,
                    :stored_name,
                    :file_path,
                    :file_type,

                    :processing_status
                )

            ");

        return $stmt->execute($data);
    }
}