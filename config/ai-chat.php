<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_CHAT_ENABLED', false),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    ],

    'daily_message_limit' => (int) env('AI_CHAT_DAILY_MESSAGE_LIMIT', 50),

    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 20),

    'auto_title' => [
        'enabled' => (bool) env('AI_CHAT_AUTO_TITLE_ENABLED', true),
    ],
];
