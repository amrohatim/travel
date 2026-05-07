<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\View\View;

class AdminBookingController extends Controller
{
    public function index(): View
    {
        $bookings = Booking::query()
            ->with(['flight', 'traveler', 'office'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.bookings.index', compact('bookings'));
    }
}
