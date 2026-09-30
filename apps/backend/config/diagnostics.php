<?php

return [
    'occurrence_days' => max(1, min(365, (int) env('DIAGNOSTIC_OCCURRENCE_DAYS', 30))),
    'closed_incident_days' => max(1, min(1095, (int) env('DIAGNOSTIC_CLOSED_DAYS', 90))),
];
