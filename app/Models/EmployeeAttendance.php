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
        return $this->photoUrl($this->check_in_photo_path, 'in');
    }

    public function checkOutPhotoUrl(): ?string
    {
        return $this->photoUrl($this->check_out_photo_path, 'out');
    }

    /**
     * URL selfie yang aman di shared hosting (hindari /storage 403).
     * Prioritas: public/uploads → route Laravel (baca storage) → asset /storage.
     */
    private function photoUrl(?string $path, string $type): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $uploadRelative = 'uploads/'.$path;
        if (is_file(public_path($uploadRelative))) {
            return asset($uploadRelative);
        }

        if ($this->id && Storage::disk('public')->exists($path)) {
            return route('admin.attendances.selfie', [
                'attendance' => $this->id,
                'type' => $type,
            ]);
        }

        // Fallback lokal bila symlink public/storage sudah ada.
        if (is_file(public_path('storage/'.$path))) {
            return asset('storage/'.$path);
        }

        return null;
    }
}
