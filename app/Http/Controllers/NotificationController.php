<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications with optional type filter.
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'all');

        $query = Notification::latest();

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $totalUnread   = Notification::where('is_read', false)->count();
        $pumpUnread    = Notification::where('type', 'pump')->where('is_read', false)->count();
        $rainfallUnread = Notification::where('type', 'rainfall')->where('is_read', false)->count();

        return view('notification.index', compact(
            'notifications', 'type', 'totalUnread', 'pumpUnread', 'rainfallUnread'
        ));
    }

    /**
     * Mark all notifications as read (optionally filter by type).
     */
    public function markAllAsRead(Request $request)
    {
        $type = $request->query('type', 'all');

        $query = Notification::where('is_read', false);
        if ($type !== 'all') {
            $query->where('type', $type);
        }
        $query->update(['is_read' => true]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification)
    {
        $notification->update(['is_read' => true]);

        return redirect()->back();
    }
}
