<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = Notification::forUser($request->user()->id)->latest();
        if ($filter === 'unread') $query->unread();

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => Notification::forUser($request->user()->id)->unread()->count(),
        ]);
    }

    public function read(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->markRead();

        $goto = $request->input('goto');
        if ($goto === '1' && ($url = $notification->safeUrl())) return redirect($url);

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        Notification::forUser($request->user()->id)->unread()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
