<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Http\Request;


class FlightController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    if ($request->date) {
        $flights = Flight::where('travel_date', $request->date)->get();
    } else {
        $flights = Flight::all();
    }

    return view('traveler.flights.index', compact('flights'));
}


    /**
     * Show the form for creating a new resource.
     */
   public function create()
{
    return view('office.flights.create');
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'price' => ['required', 'integer', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
        ]);

        $departureTime = Carbon::parse($validated['departure_time']);

        Flight::create([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'travel_date' => $departureTime->toDateString(),
            'departure_time' => $departureTime->toDateTimeString(),
            'price' => $validated['price'],
            'seats' => $validated['seats'],
            'office_id' => auth()->id(),
            'office_name' => auth()->user()->name,
        ]);

        return redirect('/office');
    }

    /**
     * Display the specified resource.
     */
    public function show(Flight $flight)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Flight $flight)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Flight $flight)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flight $flight)
    {
        //
    }
}
