<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeeAttendance extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'check_in',
        'check_in_lat',
        'check_in_lng',
        'check_in_photo_path',
        'check_in_face_distance',
        'check_out',
        'check_out_lat',
        'check_out_lng',
        'check_out_photo_path',
        'check_out_face_distance',
        'status',
        'is_late',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => AttendanceStatus::class,
            'is_late' => 'boolean',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_in_face_distance' => 'float',
            'check_out_face_distance' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkInPhotoUrl(): ?string
    {
        return $this->photoUrl($this->check_in_photo_path);
    }

    public function checkOutPhotoUrl(): ?string
    {
        return $this->photoUrl($this->check_out_photo_path);
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
