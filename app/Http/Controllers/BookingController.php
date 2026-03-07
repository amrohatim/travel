<?php

namespace App\Http\Controllers;
use App\Models\Booking;
use App\Models\Flight;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;


class BookingController extends Controller
{
    // للمسافر: يحجز رحلة
    public function store(Request $request, Flight $flight)
    {
        $request->validate([
            'seats_booked' => 'required|integer|min:1|max:' . $flight->seats,
        ]);

        Booking::create([
            'flight_id' => $flight->id,
            'traveler_id' => auth()->id(),
            'seats_booked' => $request->seats_booked,
            'status' => 'pending',
        ]);

        // نقص المقاعد المتاحة
        $flight->decrement('seats', $request->seats_booked);

return redirect('/my-bookings')->with('success', 'تم إرسال طلب الحجز بنجاح');
    }

    // عرض حجوزات المسافر
    public function myBookings()
    {
        $bookings = Booking::where('traveler_id', auth()->id())->with('flight')->get();
        return view('traveler.bookings.index', compact('bookings'));
    }

    // عرض حجوزات المكتب
    public function officeBookings()
    {
       $bookings = Booking::whereHas('flight', function($q){
    $q->where('office_id', auth()->id());
})->with('flight', 'traveler')->get();


        return view('office.bookings.index', compact('bookings'));
    }

    // تحديث حالة الحجز
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:confirmed,rejected',
        ]);

        $booking->update(['status' => $request->status]);

return redirect('/office/bookings')->with('success', 'تم تحديث حالة الحجز');
    }


public function ticket(Booking $booking)
{
    // تأكد إن الحجز للمستخدم نفسه
    if ($booking->traveler_id != auth()->id()) {
        abort(403);
    }

    // فقط لو مؤكد
    if ($booking->status != 'confirmed') {
        return redirect('/my-bookings')->with('error', 'الحجز غير مؤكد بعد');
        
    }

    $pdf = Pdf::loadView('traveler.ticket', compact('booking'));
    return $pdf->download('ticket.pdf');
    
}

}
