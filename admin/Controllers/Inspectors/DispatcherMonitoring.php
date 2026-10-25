<?php

class DispatcherMonitoringController
{
    private DispatcherMonitoringService $service;

    public function __construct(DispatcherMonitoringService $service)
    {
        $this->service = $service;
    }

    private function filters(): array
    {
        $filters = [];
        foreach (['date', 'fromDate', 'toDate', 'dispatcher', 'bus', 'driver', 'status', 'discrepancy'] as $key) {
            $value = $_GET[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = trim($value);
            }
        }
        return $filters;
    }

    private function actor(): array
    {
        $user = \App\Auth\Auth::user() ?? [];
        return [
            'id'   => $user['id'] ?? \App\Auth\Auth::userId(),
            'name' => $user['name'] ?? $user['username'] ?? \App\Auth\Auth::username() ?? 'Admin',
            'role' => \App\Auth\Auth::role() ?? 'admin',
        ];
    }

    public function live(): void
    {
        echo json_encode(['success' => true, 'data' => $this->service->live($this->filters())]);
    }

    public function activity(): void
    {
        echo json_encode(['success' => true, 'data' => $this->service->activity($this->filters())]);
    }

    public function busProblems(): void
    {
        echo json_encode(['success' => true, 'data' => $this->service->busProblems($this->filters())]);
    }

    public function history(): void
    {
        echo json_encode(['success' => true, 'data' => $this->service->history($this->filters())]);
    }

    public function detail(): void
    {
        $tripId = (int)($_GET['trip_id'] ?? 0);
        $auditId = (int)($_GET['audit_id'] ?? 0);

        $detail = null;
        if ($tripId > 0) {
            $detail = $this->service->detail($tripId);
        } elseif ($auditId > 0) {
            $resolvedTrip = $this->service->auditTripId($auditId);
            if ($resolvedTrip !== null) {
                $detail = $this->service->detail($resolvedTrip);
            }
        }

        if ($detail === null) {
            echo json_encode(['success' => false, 'message' => 'Trip not found']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $detail]);
    }

    public function resolve(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $tripId = (int)($input['trip_id'] ?? 0);
        $reason = trim((string)($input['reason'] ?? ''));

        $result = $this->service->resolve($tripId, $this->actor(), $reason);
        if (empty($result['success'])) {
            http_response_code(400);
        }
        echo json_encode($result);
    }
}
