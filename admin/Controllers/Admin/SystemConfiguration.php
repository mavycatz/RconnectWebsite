<?php

class SystemConfigurationController
{
    private SystemConfigurationService $service;

    public function __construct(
        SystemConfigurationService $service
    ){
        $this->service = $service;
    }

    public function handle(): void
    {
        try{

            $action =
                $_GET['action']
                ?? '';

            switch(
                $action
            ){

                case 'getBusTypes':

                    echo json_encode([

                        'success' => true,

                        'data' =>
                            $this->service
                                ->getBusTypes()

                    ]);

                    break;

                case 'getFuelPrices':

                    echo json_encode([

                        'success' => true,

                        'data' =>
                            $this->service
                                ->getFuelPrices()

                    ]);

                    break;

                case 'saveFuelPrices':

                    $payload =
                        json_decode(

                            file_get_contents(
                                'php://input'
                            ),

                            true

                        );

                    $prices =
                        is_array($payload['prices'] ?? null)
                        ? $payload['prices']
                        : [];

                    // Validate every row before the transactional save so a bad
                    // row can never leave a partial write behind.
                    $rowError = null;
                    $seenNames = [];

                    foreach ($prices as $row) {

                        if (!is_array($row)) {
                            $rowError = 'Invalid fuel price row.';
                            break;
                        }

                        $rowId = (int)($row['id'] ?? 0);
                        $rowName = trim((string)($row['fuel_type'] ?? ''));
                        $rowPrice = $row['price_per_liter'] ?? null;

                        if ($rowId <= 0) {
                            $rowError = 'Invalid fuel price row.';
                            break;
                        }

                        if ($rowName === '' || mb_strlen($rowName) > 50) {
                            $rowError = 'Fuel type is required (max 50 characters).';
                            break;
                        }

                        $nameKey = mb_strtolower($rowName);
                        if (isset($seenNames[$nameKey])) {
                            $rowError = 'Duplicate fuel type "' . $rowName . '" in the submitted list.';
                            break;
                        }
                        $seenNames[$nameKey] = true;

                        if (!is_numeric($rowPrice) || (float)$rowPrice < 0) {
                            $rowError = 'Price per liter must be a non-negative number.';
                            break;
                        }
                    }

                    if ($rowError !== null) {

                        http_response_code(400);

                        echo json_encode([
                            'success' => false,
                            'message' => $rowError,
                            'field' => 'prices',
                        ]);

                        break;
                    }

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->saveFuelPrices(

                                    $prices

                                )

                    ]);

                    break;

                case 'addFuelPrice':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    if(
                        !is_array($payload)
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Invalid request payload.'

                        ]);

                        break;

                    }

                    $error =
                        $this->validateFuelType(
                            $payload
                        );

                    if(
                        $error !== null
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                $error['message'],

                            'field' =>
                                $error['field']

                        ]);

                        break;

                    }

                    echo json_encode([

                        'success' =>
                            $this->service
                                ->addFuelPrice([

                                    'fuel_type' =>
                                        trim(
                                            (string)$payload['fuel_type']
                                        ),

                                    'price_per_liter' =>
                                        round(
                                            (float)$payload['price_per_liter'],
                                            2
                                        )

                                ])

                    ]);

                    break;

                case 'deleteFuelPrice':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    $id =
                        (int)(
                            is_array($payload)
                                ? ($payload['id'] ?? 0)
                                : 0
                        );

                    if(
                        $id <= 0
                        || $this->service
                            ->getFuelPrice(
                                $id
                            ) === false
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Fuel type not found.'

                        ]);

                        break;

                    }

                    echo json_encode([

                        'success' =>
                            $this->service
                                ->deleteFuelPrice(
                                    $id
                                )

                    ]);

                    break;

                case 'getViolationCategories':

                    echo json_encode([

                        'success' => true,

                        'data' =>
                            $this->service
                                ->getViolationCategories()

                    ]);

                    break;

                case 'addViolationCategory':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->addViolationCategory(

                                    $payload['category_name']
                                    ?? ''

                                )

                    ]);

                    break;

                case 'updateViolationCategory':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->updateViolationCategory(
                                    $payload
                                )

                    ]);

                    break;

                case 'deleteViolationCategory':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->deleteViolationCategory(

                                    (int)(
                                        $payload['id']
                                        ?? 0
                                    )

                                )

                    ]);

                    break;

                case 'getViolations':

                    echo json_encode([

                        'success' => true,

                        'data' =>
                            $this->service
                                ->getViolations()

                    ]);

                    break;

                case 'addViolation':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->addViolation(
                                    $payload
                                )

                    ]);

                    break;

                case 'updateViolation':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->updateViolation(
                                    $payload
                                )

                    ]);

                    break;

                case 'deleteViolation':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    echo json_encode([

                        'success' =>

                            $this->service
                                ->deleteViolation(

                                    (int)(
                                        $payload['id']
                                        ?? 0
                                    )

                                )

                    ]);

                    break;

                case 'getVehicleTypes':

                    echo json_encode([

                        'success' => true,

                        'data' =>
                            $this->service
                                ->getVehicleTypes()

                    ]);

                    break;

                case 'addVehicleType':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    if(
                        !is_array($payload)
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Invalid request payload.'

                        ]);

                        break;

                    }

                    $error =
                        $this->validateVehicleType(
                            $payload,
                            0
                        );

                    if(
                        $error !== null
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                $error['message'],

                            'field' =>
                                $error['field']

                        ]);

                        break;

                    }

                    echo json_encode([

                        'success' =>
                            $this->service
                                ->addVehicleType([

                                    'vehicle_name' =>
                                        trim(
                                            (string)$payload['vehicle_name']
                                        ),

                                    'seats' =>
                                        $this->toWholeNumber(
                                            $payload['seats']
                                        ),

                                    'standing_capacity' =>
                                        $this->toWholeNumber(
                                            $payload['standing_capacity']
                                        )

                                ])

                    ]);

                    break;

                case 'updateVehicleType':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    if(
                        !is_array($payload)
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Invalid request payload.'

                        ]);

                        break;

                    }

                    $id =
                        (int)(
                            $payload['id']
                            ?? 0
                        );

                    if(
                        $id <= 0
                        || $this->service
                            ->getVehicleType(
                                $id
                            ) === false
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Vehicle type not found.'

                        ]);

                        break;

                    }

                    $error =
                        $this->validateVehicleType(
                            $payload,
                            $id
                        );

                    if(
                        $error !== null
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                $error['message'],

                            'field' =>
                                $error['field']

                        ]);

                        break;

                    }

                    echo json_encode([

                        'success' =>
                            $this->service
                                ->updateVehicleType([

                                    'id' =>
                                        $id,

                                    'vehicle_name' =>
                                        trim(
                                            (string)$payload['vehicle_name']
                                        ),

                                    'seats' =>
                                        $this->toWholeNumber(
                                            $payload['seats']
                                        ),

                                    'standing_capacity' =>
                                        $this->toWholeNumber(
                                            $payload['standing_capacity']
                                        )

                                ])

                    ]);

                    break;

                case 'deleteVehicleType':

                    $payload =
                        json_decode(
                            file_get_contents(
                                'php://input'
                            ),
                            true
                        );

                    $id =
                        (int)(
                            $payload['id']
                            ?? 0
                        );

                    if(
                        !is_array($payload)
                        || $id <= 0
                        || $this->service
                            ->getVehicleType(
                                $id
                            ) === false
                    ){

                        echo json_encode([

                            'success' => false,

                            'message' =>
                                'Vehicle type not found.'

                        ]);

                        break;

                    }

                    echo json_encode([

                        'success' =>
                            $this->service
                                ->deleteVehicleType(
                                    $id
                                )

                    ]);

                    break;

                default:

                    throw new Exception(
                        'Invalid action.'
                    );

            }

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }

    private function validateFuelType(
        array $payload
    ): ?array
    {
        $name =
            trim(
                (string)(
                    $payload['fuel_type']
                    ?? ''
                )
            );

        if(
            $name === ''
        ){

            return [

                'field' =>
                    'fuel_type',

                'message' =>
                    'Fuel type name is required.'

            ];

        }

        if(
            strlen($name) > 50
        ){

            return [

                'field' =>
                    'fuel_type',

                'message' =>
                    'Fuel type name must be 50 characters or fewer.'

            ];

        }

        if(
            $this->service
                ->findFuelPriceByName(
                    $name
                ) !== false
        ){

            return [

                'field' =>
                    'fuel_type',

                'message' =>
                    'A fuel type with this name already exists.'

            ];

        }

        $price =
            $payload['price_per_liter']
            ?? null;

        if(
            !is_numeric($price)
            || (float)$price < 0
        ){

            return [

                'field' =>
                    'price_per_liter',

                'message' =>
                    'Price per liter must be a non-negative number.'

            ];

        }

        return null;
    }

    private function validateVehicleType(
        array $payload,
        int $excludeId
    ): ?array
    {
        $name =
            trim(
                (string)(
                    $payload['vehicle_name']
                    ?? ''
                )
            );

        if(
            $name === ''
        ){

            return [

                'field' =>
                    'vehicle_name',

                'message' =>
                    'Vehicle name is required.'

            ];

        }

        if(
            strlen($name) > 100
        ){

            return [

                'field' =>
                    'vehicle_name',

                'message' =>
                    'Vehicle name must be 100 characters or fewer.'

            ];

        }

        $seats =
            $this->toWholeNumber(
                $payload['seats']
                ?? null
            );

        if(
            $seats === null
            || $seats < 1
            || $seats > 999
        ){

            return [

                'field' =>
                    'seats',

                'message' =>
                    'Seats must be a whole number between 1 and 999.'

            ];

        }

        $standing =
            $this->toWholeNumber(
                $payload['standing_capacity']
                ?? null
            );

        if(
            $standing === null
            || $standing < 0
            || $standing > 999
        ){

            return [

                'field' =>
                    'standing_capacity',

                'message' =>
                    'Standing capacity must be a whole number between 0 and 999.'

            ];

        }

        if(
            $this->service
                ->findVehicleTypeByName(
                    $name,
                    $excludeId
                ) !== false
        ){

            return [

                'field' =>
                    'vehicle_name',

                'message' =>
                    'A vehicle type with this name already exists.'

            ];

        }

        return null;
    }

    private function toWholeNumber(
        mixed $value
    ): ?int
    {
        if(
            is_int($value)
        ){

            return $value;

        }

        if(
            is_float($value)
            && floor($value) === $value
        ){

            return (int)$value;

        }

        if(
            is_string($value)
            && preg_match(
                '/^\d+$/',
                trim($value)
            ) === 1
        ){

            return (int)trim(
                $value
            );

        }

        return null;
    }
}