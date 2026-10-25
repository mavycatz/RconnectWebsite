<?php

class DispatcherMonitoringService
{
    private DispatcherMonitoringModel $model;

    public function __construct(DispatcherMonitoringModel $model)
    {
        $this->model = $model;
    }

    public function live(array $filters): array
    {
        return [
            'summary'      => $this->model->summary($filters),
            'rows'         => $this->model->liveActivity($filters),
            'filters'      => $this->model->filterOptions(),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function activity(array $filters): array
    {
        return ['rows' => $this->model->activityList($filters)];
    }

    public function busProblems(array $filters): array
    {
        return ['rows' => $this->model->busProblems($filters)];
    }

    public function history(array $filters): array
    {
        return $this->model->dispatcherHistory($filters);
    }

    public function detail(int $tripId): ?array
    {
        return $this->model->detail($tripId);
    }

    public function auditTripId(int $auditId): ?int
    {
        return $this->model->auditIdToTripId($auditId);
    }

    public function resolve(int $tripId, array $actor, string $reason): array
    {
        if ($tripId <= 0) {
            return ['success' => false, 'message' => 'Trip is required'];
        }
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'A reason is required to resolve a discrepancy'];
        }
        $ok = $this->model->resolveDiscrepancy($tripId, $actor, trim($reason));
        return $ok
            ? ['success' => true, 'message' => 'Discrepancy resolved']
            : ['success' => false, 'message' => 'No open discrepancy for this trip'];
    }
}
