<?php

return [
    // Activate only after API, mobile and privacy acceptance gates pass together.
    'v2' => (bool) env('RT05_FINANCE_V2', false),
];
