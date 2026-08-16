<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:32'],
            'action' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::query()->with('user:id,name,email')->latest('id');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (! empty($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from'].' 00:00:00');
        }
        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to'].' 23:59:59');
        }
        if (! empty($validated['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('actor_name', 'like', $term)
                    ->orWhere('actor_email', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('url', 'like', $term);
            });
        }

        $todayStart = now()->startOfDay();
        $counts = [
            'all' => ActivityLog::query()->count(),
            'auth' => ActivityLog::query()->where('category', 'auth')->where('created_at', '>=', $todayStart)->count(),
            'transaksi' => ActivityLog::query()->where('category', 'transaksi')->where('created_at', '>=', $todayStart)->count(),
            'pesan' => ActivityLog::query()->where('category', 'pesan')->where('created_at', '>=', $todayStart)->count(),
        ];

        return view('admin.activity-logs.index', [
            'logs' => $query->paginate(40)->withQueryString(),
            'filters' => [
                'category' => $validated['category'] ?? '',
                'action' => $validated['action'] ?? '',
                'q' => $validated['q'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
            'categories' => ActivityLog::CATEGORIES,
            'actions' => ActivityLog::ACTIONS,
            'counts' => $counts,
        ]);
    }
}
