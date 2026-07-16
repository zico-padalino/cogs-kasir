<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Face match: Euclidean distance threshold (lower = stricter)
    |--------------------------------------------------------------------------
    | Akun yang dikecualikan dari absensi wajib dipilih di Admin → Pengaturan.
    */
    'face_match_threshold' => (float) env('ATTENDANCE_FACE_THRESHOLD', 0.55),
];
