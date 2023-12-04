<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGameInMultiplePartnersRequest;
use App\Models\Log;
use App\Models\Partner;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Return_;
use App\Models\People;


class PartnerController extends Controller
{

    public function index() {
        try {
            $partners = Partner::all();
            return Response($partners, 200);
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function createGameInMultiplePartners(CreateGameInMultiplePartnersRequest $request) {
        ini_set('max_execution_time', 180);
        try {
            $data = $request->all();

            foreach ($data['partners'] as $partner) {
                $data_partner = Partner::findOrFail($partner);
                $_SESSION['partner_create_game'] = $data_partner;
                $type_games = DB::connection($data_partner['connection'])->table('type_games')->where('category', $data['category'])->get();
                if($data['category'] == 'dupla_sena') {
                    foreach ($type_games as $type_game) {
                        $has_competition = DB::connection($data_partner['connection'])->table('competitions')->where('type_game_id', $type_game->id)->where('number', $data['number'])->exists();
                        if(!$has_competition) {
                            DB::connection($data_partner['connection'])->table('competitions')->insert([
                                'number' => $data['number'].'A',
                                'type_game_id' => $type_game->id,
                                'sort_date' => $data['date_of_sort'],
                                'created_at' => Carbon::now('America/Sao_Paulo'),
                                'updated_at' => Carbon::now('America/Sao_Paulo')
                            ]);
                            DB::connection($data_partner['connection'])->table('competitions')->insert([
                                'number' => $data['number'],
                                'type_game_id' => $type_game->id,
                                'sort_date' => $data['date_of_sort'],
                                'created_at' => Carbon::now('America/Sao_Paulo'),
                                'updated_at' => Carbon::now('America/Sao_Paulo')
                            ]);
                        }
                    }
                }
                if($data['category'] != 'dupla_sena') {
                    foreach ($type_games as $type_game) {
                        $has_competition = DB::connection($data_partner['connection'])->table('competitions')->where('type_game_id', $type_game->id)->where('number', $data['number'])->exists();
                        if(!$has_competition) {
                            DB::connection($data_partner['connection'])->table('competitions')->insert([
                                'number' => $data['number'],
                                'type_game_id' => $type_game->id,
                                'sort_date' => $data['date_of_sort'],
                                'created_at' => Carbon::now('America/Sao_Paulo'),
                                'updated_at' => Carbon::now('America/Sao_Paulo')
                            ]);
                        }
                    }
                }
            }
            return Response('Criação Finalizada', 200);
        } catch (\Throwable $th) {
            $data_partner = $_SESSION['partner_create_game'];
            $log = new Log();
            $log->user_id = Auth::user()->id;
            $log->user_name = Auth::user()->name;
            $log->action = 'Problemas de integração com a banca '.$data_partner->name;
            $log->response = json_encode("MESSAGE: ".$th->getMessage()." LINE: ".$th->getLine()." REQUEST: ".json_encode($request->all()));
            $log->save();
            throw new Exception($th);
        }
    }

    public function sendResultInMultiplePartners(Request $request) {
        ini_set('max_execution_time', 180);
        try {
            $data = $request->all();
            $resultsArray = array_map('intval', explode(',', $data['result']));
    
            foreach ($data['partners'] as $partner) {
                $winners = null;
                $draw_data = null;
                $concurses_id = null;
                $draws_id = [];
                $data_partner = Partner::findOrFail($partner);
                $_SESSION['partner_send_result'] = $data_partner;
                $categorys = DB::connection($data_partner['connection'])->table('type_games')->where('category', $data['category'])->pluck('id');
                $concurses = DB::connection($data_partner['connection'])->table('competitions')->where('number', $data['number'])->whereIn('type_game_id', $categorys)->get();
                foreach ($concurses as $concurse) {
                    $has_draw = DB::connection($data_partner['connection'])->table('draws')->where('type_game_id', $concurse->type_game_id)->where('competition_id', $concurse->id)->exists();
                    if(!$has_draw) {
                        $concurses_id[] = $concurse->id;
                        $draws_id[] = DB::connection($data_partner['connection'])->table('draws')->insertGetId([
                            'type_game_id' => $concurse->type_game_id,
                            'competition_id' => $concurse->id,
                            'numbers' => $data['result'],
                            'created_at' => Carbon::now('America/Sao_Paulo'),
                            'updated_at' => Carbon::now('America/Sao_Paulo')
                        ]);
                    }
                }

                foreach ($draws_id as $draw) {
                    $draw_data = DB::connection($data_partner['connection'])->table('draws')->where('id',$draw)->first();
                    $competitions = DB::connection($data_partner['connection'])->table('games')->where('checked', 1)->where('competition_id', $draw_data->competition_id)->get();
                    foreach ($competitions as $competition) {
                        $numbers_result = array_map('intval', explode(',', $competition->numbers));
                        $count_numbers_correct = count(array_intersect($numbers_result, $resultsArray));
                        if(count($resultsArray) == $count_numbers_correct) {
                            $winners[] = $competition->id;
                        }
                    }
                    if(is_countable($winners) && count($winners) > 0) {
                        $winners_string = strval(implode(",", $winners));
                        DB::connection($data_partner['connection'])->table('draws')->where('id',$draw)->update(['games' => $winners_string]);
                    }
                    $winners = null;
                }
            }
            return Response('Resultados enviados com sucesso', 200);
        } catch (\Throwable $th) {
            $data_partner = $_SESSION['partner_send_result'];
            $log = new Log();
            $log->user_id = Auth::user()->id;
            $log->user_name = Auth::user()->name;
            $log->action = 'Problemas de integração com a banca '.$data_partner->name;
            $log->response = json_encode("MESSAGE: ".$th->getMessage()." LINE: ".$th->getLine()." REQUEST: ".json_encode($request->all()));
            $log->save();
            throw new Exception($th);
        }

    }

    public function getResultInMultiplePartners(Request $request)
    {
            try {
                $data = $request->all();
                $winners = [];
                $data_partner = Partner::findOrFail($data['partner']);
                $concurses = DB::connection($data_partner['connection'])->table('competitions')->where('number', $data['number'])->pluck('id');
                $draws = DB::connection($data_partner['connection'])->table('draws')->whereIn('competition_id', $concurses)->get();

                foreach ($draws as $draw) {
                    if ($draw != null) {
                        $competition = DB::connection($data_partner['connection'])->table('competitions')->where('id', $draw->competition_id)->first();

                        $numbers_draw = array_map('intval', explode(',', $draw->games));
                        $num_tickets = count($numbers_draw); // Conta a quantidade de bilhetes sorteados

                        $games = DB::connection($data_partner['connection'])
                            ->table('games')
                            ->select(['games.id', 'clients.name as name', 'games.premio', 'games.status', 'type_games.name as game_name'])
                            ->join('clients', 'clients.id', '=', 'games.client_id')
                            ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                            ->where('games.checked', 1)
                            ->whereIn('games.id', $numbers_draw)
                            ->get();

                        foreach ($games as $game) {
                            $game->sort_date = $competition->sort_date;
                            $game->num_tickets = $num_tickets; // Adiciona a quantidade de bilhetes sorteados

                            // Formata o valor como dinheiro
                            $game->premio_formatted = $this->formatMoney($game->premio);

                            array_push($winners, $game);
                        }
                    }
                }

                return $winners;
            } catch (\Throwable $th) {
                throw new Exception($th);
            }
        }

    private function formatMoney($value)
    {
        return $value >= 1000 ? 'R$ ' . number_format($value, 2, ',', '.') : 'R$ ' . number_format($value, 2, ',', '.');
    }




    public function updateStatus(Request $request) {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);
        
        if($data['status'] == 2) {
            $data_game = DB::connection($data_partner['connection'])->table('games')->where('id', $data['id'])->first();
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($data_game->client_id);
            $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
            if($user_data) {
                $value = floatval($data_game->premio) + floatval($user_data->available_withdraw);
                $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $value]);
            }else {
                $user_data_default = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                $value = floatval($data_game->premio) + floatval($user_data_default->available_withdraw);
                $user_update = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->update(['available_withdraw' => $value]);
            }

        }
        $game = DB::connection($data_partner['connection'])->table('games')->where('id', $data['id'])->update(['status' => $data['status']]);
        return Response("Alteração finalizadas com sucesso!", 200);
    }

    public function distributePrizes(Request $request)
    {
        try {
            $totalAmount = $request->premio;
            $numberOfPeople = $request->ganhadores;
    
            if ($numberOfPeople <= 0) {
                return response()->json(['message' => 'Número de pessoas deve ser maior que 0'], 422);
            }
    
            $distributionFactors = $this->generateDistributionFactors($numberOfPeople);
    
            $winners = People::inRandomOrder()->limit($numberOfPeople)->get();
    
            $winnersList = [];
    
            $winners = $winners->sortByDesc(function ($winner) {
                return $winner->premio;
            });
    
            $resultInMultiplePartners = $this->getResultInMultiplePartners($request);
            $resultInMultiplePartners = array_values(array_filter($resultInMultiplePartners));
    
            $gameName = $resultInMultiplePartners[0]->game_name;
            $sortDate = Carbon::parse($resultInMultiplePartners[0]->sort_date)->format('d/m/Y');
            $num_tickets = $resultInMultiplePartners[0]->num_tickets;
    
            foreach ($winners as $key => $winner) {
                $winnerFullName = $winner->first_name . ' ' . $winner->last_name;
    
                $winnerPrize = intval($totalAmount * $distributionFactors[$key]);
                $winnerStatus = rand(1, 3);
                $winnerId = str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);
    
                $winnersList[] = [
                    'id' => $winnerId,
                    'name' => $winnerFullName,
                    'premio' => $winnerPrize,
                    'status' => $winnerStatus,
                    'game_name' => $gameName,
                    'sort_date' => $sortDate,
                    'num_tickets' =>$num_tickets,
                    'premio_formatted' => $this->formatMoney($winnerPrize),

                ];
            }
    
            $mergedResults = array_merge($resultInMultiplePartners, $winnersList);
            $mergedResults = collect($mergedResults)->sortByDesc('premio')->values()->all();
    
            return response()->json($mergedResults, 200);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
    
    
    private function generateDistributionFactors($numberOfPeople)
    {
        $factors = [];
    
        for ($i = 0; $i < $numberOfPeople; $i++) {
            $factors[] = mt_rand(1, 100) / 100;
        }
    
        $sum = array_sum($factors);
        $factors = array_map(function ($factor) use ($sum) {
            return $factor / $sum;
        }, $factors);
    
        return $factors;
    }

    public function listCompetitions(Request $request)
    {
        try {
            $data = $request->all();
            $data_partner = Partner::findOrFail($data['partner']);

            $query = DB::connection($data_partner['connection'])
                ->table('competitions')
                ->join('type_games', 'competitions.type_game_id', '=', 'type_games.id')
                ->select(
                    'competitions.id',
                    'competitions.number',
                    'competitions.type_game_id',
                    'type_games.name as type_game_name',
                    'competitions.sort_Date',
                    'competitions.created_at',
                    'competitions.updated_at'
                )
                ->orderBy('competitions.created_at', 'desc');

            if (isset($data['number'])) {
                $query->where('competitions.number', $data['number']);
            }

            $competitions = $query->take(10)->get();

            return response()->json($competitions);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }


}