<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\HomeMessageController;
use App\Http\Controllers\Api\NotificationTokenController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\AppVersionController;

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/flights', [FlightController::class, 'index']);
    Route::get('/states', [StateController::class, 'index']);
    Route::get('/home-messages', [HomeMessageController::class, 'index']);

    Route::middleware(['auth:sanctum', 'device.status'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/offices', [OfficeController::class, 'index']);
        Route::post('/notifications/token', [NotificationTokenController::class, 'store']);
        Route::delete('/notifications/token', [NotificationTokenController::class, 'destroy']);
        Route::get('/flights/{flight}/reserved-seats', [FlightController::class, 'reservedSeats']);

        Route::middleware('role:office,support')->group(function (): void {
            Route::get('/office/profile', [OfficeController::class, 'profile']);
            Route::post('/office/profile', [OfficeController::class, 'updateProfile']);
            Route::post('/office/password', [OfficeController::class, 'updatePassword']);
            Route::post('/office/flights', [FlightController::class, 'store']);
            Route::post('/office/flights/future', [FlightController::class, 'storeFuture']);
            Route::patch('/office/flights/{flight}', [FlightController::class, 'update']);
            Route::get('/office/flights/today', [FlightController::class, 'officeToday']);
            Route::get('/office/flights/upcoming', [FlightController::class, 'officeUpcoming']);
            Route::get('/office/flights/previous', [FlightController::class, 'officePrevious']);
            Route::get('/office/flights/{flight}/passengers', [FlightController::class, 'officePassengers']);
            Route::get('/office/bookings', [BookingController::class, 'officeBookings']);
            Route::get('/office/bookings/summary', [BookingController::class, 'officeBookingsSummary']);
            Route::patch('/office/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
            Route::patch('/office/bookings/{booking}/seats', [BookingController::class, 'updateSeatNumbers']);
        });

        Route::middleware('role:traveler')->group(function (): void {
            Route::post('/flights/{flight}/bookings', [BookingController::class, 'store']);
            Route::get('/traveler/bookings', [BookingController::class, 'travelerBookings']);
            Route::post('/traveler/password', [AuthController::class, 'updateTravelerPassword']);
            Route::post('/traveler/backup-number', [AuthController::class, 'updateBackupNumber']);
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::post('/admin/states', [StateController::class, 'store']);
        });
    });
    Route::get('/getAppVersion', [AppVersionController::class, 'getVersion']);
});
