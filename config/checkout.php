<?php

return [
    'currency' => env('CHECKOUT_CURRENCY', 'EGP'),
    'mock_enabled' => env('CHECKOUT_MOCK_ENABLED', true),
    'reservation_timeout_minutes' => env('CHECKOUT_RESERVATION_TIMEOUT_MINUTES', 5),
];
