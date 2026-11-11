<?php

return [
    // Server-side only; never include keys in views, prompts, logs or job payloads.
    'openai_key' => env('WORKFORCE_OPENAI_API_KEY'),
    'model' => env('WORKFORCE_OPENAI_MODEL', 'gpt-5.4'),
];
