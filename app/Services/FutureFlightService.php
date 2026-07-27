<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\User;
use Carbon\Carbon;

class FutureFlightService
{
    /**
     * @param  array{from:string,to:string,departure_time:string,price:int,seats:int,days_ahead:int}  $data
     * @return array{
     *     created_dates: array<int, string>,
     *     skipped_dates: array<int, string>,
     *     created_count: int,
     *     skipped_count: int,
     *     flights: array<int, Flight>
     * }
     */
    public function createWeeklyFlightsForOffice(User $office, array $data): array
    {
        $baseDepartureTime = Carbon::parse($data['departure_time']);
        $horizon = $baseDepartureTime->copy()->addDays((int) $data['days_ahead']);
        $from = $data['from'];
        $to = $data['to'];
        $price = (int) $data['price'];
        $seats = (int) $data['seats'];

        $createdFlights = [];
        $createdDates = [];
        $skippedDates = [];

        for (
            $candidate = $baseDepartureTime->copy()->addDays(7);
            $candidate->lessThanOrEqualTo($horizon);
            $candidate->addDays(7)
        ) {
            $candidateDate = $candidate->toDateString();
            $candidateDateTime = $candidate->toDateTimeString();

            $exists = Flight::query()
                ->where('office_id', $office->id)
                ->where('from', $from)
                ->where('to', $to)
                ->whereDate('travel_date', $candidateDate)
                ->where('departure_time', $candidateDateTime)
                ->exists();

            if ($exists) {
                $skippedDates[] = $candidateDate;
                continue;
            }

            $flight = Flight::create([
                'from' => $from,
                'to' => $to,
                'travel_date' => $candidateDate,
                'departure_time' => $candidateDateTime,
                'price' => $price,
                'seats' => $seats,
                'office_id' => $office->id,
                'office_name' => $office->name,
            ]);

            $createdFlights[] = $flight;
            $createdDates[] = $candidateDate;
        }

        return [
            'created_dates' => $createdDates,
            'skipped_dates' => $skippedDates,
            'created_count' => count($createdDates),
            'skipped_count' => count($skippedDates),
            'flights' => $createdFlights,
        ];
    }
}
