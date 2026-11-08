<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('user')->latest('created_at');
        if ($request->filled('action')) $query->where('action', 'like', $request->string('action').'%');
        $activities = $query->paginate(25)->withQueryString();
        return view('activity.index', compact('activities'));
    }
}
