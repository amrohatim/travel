<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Illuminate\View\View;

class AdminFlightController extends Controller
{
    public function index(): View
    {
        $flights = Flight::query()
            ->orderByDesc('travel_date')
            ->orderByDesc('departure_time')
            ->paginate(20);

        return view('admin.flights.index', compact('flights'));
    }
}
