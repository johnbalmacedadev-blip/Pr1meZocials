<?php

return [

    'upload_max_kb' => (int) env('UPLOAD_MAX_KB', 512000),

    // Compose videos are uploaded in chunks, so this is not limited by post_max_size.
    'video_upload_max_mb' => (int) env('VIDEO_UPLOAD_MAX_MB', 1024),

    'allowed_uploads' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'webm' => ['video/webm'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ],

    'deadline_warning_days' => 2,

    'stale_after_days' => 7,

    'schedule_conflict_threshold' => 3,

    /*
    | Closed workflow stages. Overdue and "active work" exclude these.
    */
    'closed_statuses' => ['published', 'archived'],

];
