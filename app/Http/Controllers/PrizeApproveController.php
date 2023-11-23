<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\People;


class PrizeApproveController extends Controller
{
    public function distributePrizes(Request $request)
    {

        // $totalAmount = $request->input('total_amount');
        // $numberOfPeople = $request->input('number_of_people');

        $totalAmount = 1000;
        $numberOfPeople = 3;

        if ($numberOfPeople <= 0) {
            return response()->json(['message' => 'Número de pessoas deve ser maior que 0'], 422);
        }

        $prizePerPerson = $totalAmount / $numberOfPeople;

        $winners = People::inRandomOrder()->limit($numberOfPeople)->get();

        foreach ($winners as $winner) {
            $winnerFullName = $winner->first_name . ' ' . $winner->last_name;
            $winnerPrize = $prizePerPerson;

            $winnersList[] = [
                'person' => $winnerFullName,
                'prize' => $winnerPrize,
            ];
        }

        return response()->json(['winners' => $winnersList], 200);
    }
}
