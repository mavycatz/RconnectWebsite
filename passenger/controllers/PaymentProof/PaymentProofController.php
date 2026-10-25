<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PaymentScreenshotValidator.php';

use App\Core\Database;

class PaymentProofController
{
    public function handle(): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['passenger_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit;
        }

        if (!passenger_rate_limit('payment_proof', (string) $_SESSION['passenger_id'] . '|' . passenger_client_ip(), 10, 3600, 0)) {
            echo json_encode(['success' => false, 'message' => 'Too many upload attempts. Please try again later.']);
            exit;
        }

        if (!isset($_FILES['gcash_screenshot']) || (int) $_FILES['gcash_screenshot']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'No file selected.']);
            exit;
        }

        [$uploadOk, $uploadDetail] = passenger_validate_image_upload($_FILES['gcash_screenshot']);
        if (!$uploadOk) {
            echo json_encode(['success' => false, 'message' => $uploadDetail]);
            exit;
        }

        $file = $_FILES['gcash_screenshot'];

        $tripId = isset($_POST['trip_id']) ? (int) $_POST['trip_id'] : 0;
        if ($tripId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid trip ID.']);
            exit;
        }

        try {
            $pdo = Database::get();

            $stmt = $pdo->prepare("SELECT id, passenger_id, payment_method, payment_status, fare, gcash_amount FROM passenger_trips WHERE id = :id AND passenger_id = :pid LIMIT 1");
            $stmt->execute([':id' => $tripId, ':pid' => $_SESSION['passenger_id']]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                echo json_encode(['success' => false, 'message' => 'Trip not found.']);
                exit;
            }

            $gcashAmount = (float) ($trip['gcash_amount'] ?? 0);
            $expectedAmount = null;
            if ($gcashAmount > 0) {
                $expectedAmount = $gcashAmount;
            } elseif (strtolower($trip['payment_method'] ?? '') === 'gcash') {
                $expectedAmount = (float) ($trip['fare'] ?? 0);
            }

            $validator = new PaymentScreenshotValidator();
            $result = $validator->validate($file['tmp_name'], $expectedAmount);

            $hasRefCol = $this->hasColumn($pdo, 'gcash_reference_no');

            if ($result['ok']) {
                $extracted = $result['extracted'];
                $ref = $extracted['reference_no'] ?? null;

                if ($ref !== null && $hasRefCol) {
                    $dupStmt = $pdo->prepare("SELECT id FROM passenger_trips WHERE gcash_reference_no = :ref AND id <> :id LIMIT 1");
                    $dupStmt->execute([':ref' => $ref, ':id' => $tripId]);
                    if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'This payment reference was already submitted for another transaction. Please upload a different payment screenshot.',
                            'validated' => true,
                            'rejection_reason' => 'duplicate_reference',
                            'extracted' => $extracted,
                        ]);
                        exit;
                    }
                }

                $filename = 'gcash_' . $tripId . '_' . bin2hex(random_bytes(8)) . '.' . $uploadDetail;
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/gcash/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $dest = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
                    exit;
                }

                $set = ['gcash_screenshot = :screenshot'];
                $params = [':screenshot' => $filename];

                if ($ref !== null && $hasRefCol) {
                    $set[] = 'gcash_reference_no = :ref';
                    $params[':ref'] = $ref;
                }

                $amount = $extracted['amount'] ?? null;
                if ($this->hasColumn($pdo, 'gcash_verified_amount')) {
                    $set[] = 'gcash_verified_amount = :vamount';
                    $params[':vamount'] = $amount !== null ? $amount : null;
                }

                $paymentDate = null;
                if (!empty($extracted['date'])) {
                    $paymentDate = ($extracted['time'] ?? null) !== null
                        ? date('Y-m-d H:i:s', strtotime($extracted['date'] . ' ' . $extracted['time']))
                        : date('Y-m-d', strtotime($extracted['date']));
                }
                if ($this->hasColumn($pdo, 'gcash_payment_date')) {
                    $set[] = 'gcash_payment_date = :pdate';
                    $params[':pdate'] = $paymentDate !== null ? $paymentDate : null;
                }

                $provider = $extracted['provider'] ?? null;
                if ($this->hasColumn($pdo, 'gcash_provider')) {
                    $set[] = 'gcash_provider = :provider';
                    $params[':provider'] = $provider;
                }

                if (strtolower($trip['payment_status'] ?? '') === 'rejected') {
                    $set[] = 'payment_status = :status';
                    $params[':status'] = 'pending';
                    if ($this->hasColumn($pdo, 'resubmitted_at')) {
                        $set[] = 'resubmitted_at = NOW()';
                    }
                }

                $updateStmt = $pdo->prepare("UPDATE passenger_trips SET " . implode(', ', $set) . " WHERE id = :id AND passenger_id = :pid");
                $updateStmt->execute(array_merge($params, [':id' => $tripId, ':pid' => $_SESSION['passenger_id']]));

                echo json_encode([
                    'success' => true,
                    'message' => 'Screenshot uploaded. Your payment is now pending verification.',
                    'validated' => true,
                    'extracted' => $extracted,
                ]);
                exit;
            }

            $reasonCode = $result['reason_code'] ?? 'invalid';
            $reason = $result['reason'] ?? 'Screenshot could not be verified. Please try again.';

            $set = ['payment_status = :status'];
            $params = [':status' => 'rejected'];

            if ($this->hasColumn($pdo, 'rejection_reason')) {
                $set[] = 'rejection_reason = :reason';
                $params[':reason'] = $reasonCode;
            }
            if ($this->hasColumn($pdo, 'gcash_validation_reason')) {
                $set[] = 'gcash_validation_reason = :vreason';
                $params[':vreason'] = $reason;
            }

            $failStmt = $pdo->prepare("UPDATE passenger_trips SET " . implode(', ', $set) . " WHERE id = :id AND passenger_id = :pid");
            $failStmt->execute(array_merge($params, [':id' => $tripId, ':pid' => $_SESSION['passenger_id']]));

            echo json_encode([
                'success' => false,
                'message' => $reason,
                'validated' => false,
                'rejection_reason' => $reasonCode,
                'extracted' => $result['extracted'] ?? [],
            ]);
            exit;

        } catch (Throwable $e) {
            error_log('Passenger payment proof upload failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
    }

    private function hasColumn(PDO $db, string $column): bool
    {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'passenger_trips'
                  AND COLUMN_NAME = :col
            ");
            $stmt->execute([':col' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
