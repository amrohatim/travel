<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\BookingController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';



Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return view('admin.dashboard');
    });
});

Route::middleware(['auth', 'role:office'])->group(function () {
    Route::get('/office', function () {
        return view('office.dashboard');
    });
    

     Route::get('/office/flights/create', [FlightController::class, 'create']);
     Route::get('/office/flights/myflights', [FlightController::class, 'show']);
    Route::post('/office/flights', [FlightController::class, 'store']);
        Route::get('/office/bookings', [BookingController::class, 'officeBookings']);
    Route::post('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);



});

Route::middleware(['auth', 'role:traveler'])->group(function () {
    Route::get('/traveler', function () {
        return view('traveler.dashboard');
    });

        Route::get('/flights', [FlightController::class, 'index']);
        Route::post('/flights/{flight}/book', [BookingController::class, 'store']);
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);
        Route::get('/ticket/{booking}', [BookingController::class, 'ticket']);

});
