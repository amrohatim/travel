<?php

namespace App\Http\Controllers;

use App\Models\Flight;
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
    Flight::create([
    'from' => $request->from,
    'to' => $request->to,
    'travel_date' => $request->travel_date,
    'price' => $request->price,
    'seats' => $request->seats,
    'office_id' => auth()->id(),
    'office_name' => auth()->user()->name, // اسم المكتب
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
