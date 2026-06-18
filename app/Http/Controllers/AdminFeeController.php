<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminFeeController extends Controller
{
    public function index(): View
    {
        $payableBookings = Booking::query()
            ->selectRaw('office_id, COUNT(*) as payable_bookings_count, COALESCE(SUM(seats_booked), 0) as payable_seats_sum')
            ->where('demanded', true)
            ->where('status', '!=', 'rejected')
            ->groupBy('office_id');

        $offices = User::query()
            ->select('users.*')
            ->selectRaw('COALESCE(payable_bookings.payable_bookings_count, 0) as payable_bookings_count')
            ->selectRaw('COALESCE(payable_bookings.payable_seats_sum, 0) as payable_seats_sum')
            ->selectRaw('COALESCE(payable_bookings.payable_seats_sum, 0) * 5000 as invoice_amount')
            ->leftJoinSub($payableBookings, 'payable_bookings', function ($join): void {
                $join->on('payable_bookings.office_id', '=', 'users.id');
            })
            ->where('users.role', 'office')
            ->orderBy('users.name')
            ->get();

        return view('admin.fees.index', compact('offices'));
    }

    public function clearOfficeFees(User $office): RedirectResponse
    {
        if ($office->role !== 'office') {
            abort(404);
        }

        $updatedCount = Booking::query()
            ->where('office_id', $office->id)
            ->where('demanded', true)
            ->where('status', '!=', 'rejected')
            ->update(['demanded' => false]);

        return redirect()
            ->route('admin.fees.index')
            ->with('success', sprintf('Cleared fees for %s. %d booking(s) updated.', $office->name, $updatedCount));
    }
}
