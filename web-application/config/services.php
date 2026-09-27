<?php

return [
    'bananashield' => [
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'token' => env('AI_SERVICE_TOKEN', 'change-me'),
        'mode' => env('AI_MODE', 'mock'),
        'timeout' => env('AI_SERVICE_TIMEOUT', 15),
        'part_detection_mode' => env('PART_DETECTION_MODE', env('AI_MODE', 'mock')),
        'part_detection_mock_scenario' => env('PART_DETECTION_MOCK_SCENARIO', 'Leaf'),
    ],
];
