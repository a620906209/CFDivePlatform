<?php

return [
    'max_attempts'   => env('LOCKOUT_MAX_ATTEMPTS', 5),
    'decay_minutes'  => env('LOCKOUT_DECAY_MINUTES', 15),
];
