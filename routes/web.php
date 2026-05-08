<?php

use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminFlightController;
use App\Http\Controllers\AdminStateController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/users');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/flights', [AdminFlightController::class, 'index'])->name('flights.index');
    Route::get('/flights/{flight}/seats', [AdminFlightController::class, 'seats'])->name('flights.seats');
    Route::delete('/flights/{flight}', [AdminFlightController::class, 'destroy'])->name('flights.destroy');
    Route::post('/flights/bulk-delete', [AdminFlightController::class, 'bulkDestroy'])->name('flights.bulk-destroy');
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}/seats', [AdminBookingController::class, 'seats'])->name('bookings.seats');
    Route::delete('/bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');
    Route::post('/bookings/bulk-delete', [AdminBookingController::class, 'bulkDestroy'])->name('bookings.bulk-destroy');

    Route::get('/states', [AdminStateController::class, 'index'])->name('states.index');
    Route::post('/states', [AdminStateController::class, 'store'])->name('states.store');
    Route::post('/states/{state}/image', [AdminStateController::class, 'updateImage'])->name('states.image.update');
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
