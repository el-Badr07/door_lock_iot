<?php

class Response {
    public static function json($data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        if ($data !== null) {
            echo json_encode([
                'success' => $statusCode >= 200 && $statusCode < 300,
                'data' => $data,
                'timestamp' => time()
            ], JSON_PRETTY_PRINT);
        }
        
        exit();
    }
    
    public static function error($message, $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $statusCode
            ]
        ];
        
        if ($errors !== null) {
            $response['error']['details'] = $errors;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    public static function success($message = 'Operation successful', $data = null, $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::json($response, $statusCode);
    }
}
