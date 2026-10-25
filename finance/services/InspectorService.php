<?php

namespace Finance\Services;

require_once __DIR__ .
    '/../models/InspectorModel.php';

use Finance\Models\InspectorModel;

class InspectorService
{
    private InspectorModel $inspectorModel;

    public function __construct()
    {
        $this->inspectorModel =
            new InspectorModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL INSPECTORS
    |--------------------------------------------------------------------------
    */

    public function getAllInspectors(): array
    {
        return $this
            ->inspectorModel
            ->getAllInspectors();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE INSPECTORS
    |--------------------------------------------------------------------------
    */

    public function getActiveInspectors(): array
    {
        return $this
            ->inspectorModel
            ->getInspectorsByStatus(
                'active'
            );
    }
}