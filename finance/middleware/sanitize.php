<?php

namespace Finance\Middleware;

class Sanitize
{
    public static function clean(mixed $value, string $type = 'string'): mixed
    {
        if (is_array($value)) {
            return array_map(fn($v) => self::clean($v, $type), $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        return match ($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT),
            'email' => filter_var(trim($value), FILTER_SANITIZE_EMAIL),
            'url' => filter_var(trim($value), FILTER_SANITIZE_URL),
            default => htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8'),
        };
    }

    public static function validateParam(array $params, string $key, string $type = 'string', bool $required = false): mixed
    {
        if (!array_key_exists($key, $params) || $params[$key] === '') {
            if ($required) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing required parameter: $key"]);
                exit;
            }
            return null;
        }

        $value = self::clean($params[$key], $type);

        if ($type === 'int' && $value === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Parameter '$key' must be an integer"]);
            exit;
        }

        if ($type === 'float' && $value === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Parameter '$key' must be a number"]);
            exit;
        }

        return $value;
    }

    public static function whitelist(array $params, array $allowedKeys): array
    {
        $cleaned = [];
        foreach ($allowedKeys as $key => $type) {
            if (is_int($key)) {
                $cleaned[$type] = self::validateParam($params, $type);
            } else {
                $cleaned[$key] = self::validateParam($params, $key, $type);
            }
        }

        foreach ($params as $key => $value) {
            if (!isset($allowedKeys[$key]) && !in_array($key, $allowedKeys, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Unknown parameter: $key"]);
                exit;
            }
        }

        return $cleaned;
    }
}
