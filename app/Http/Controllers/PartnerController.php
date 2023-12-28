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
                 if($data['category'] == 'mega_kino') {
                    foreach ($type_games as $type_game) {
                        $has_competition = DB::connection($data_partner['connection'])->table('competitions')->where('type_game_id', $type_game->id)->where('number', $data['number'])->exists();
                        if(!$has_competition) {
                             $letras = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
                            foreach ($letras as $letra) {
                                DB::connection($data_partner['connection'])->table('competitions')->insert([
                                'number' => $data['number'] . $letra,
                                'type_game_id' => $type_game->id,
                                'sort_date' => $data['date_of_sort'],
                                'created_at' => Carbon::now('America/Sao_Paulo'),
                                'updated_at' => Carbon::now('America/Sao_Paulo')
                                ]);
                            }
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
                if($data['category'] != 'dupla_sena' && $data['category'] != 'mega_kino' ) {
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
    
            // Ajuste para pesquisa por data
            $concurses = DB::connection($data_partner['connection'])
                ->table('competitions')
                ->whereDate('sort_date', '=', $data['number'])
                ->pluck('id');
    
            $draws = DB::connection($data_partner['connection'])
                ->table('draws')
                ->whereIn('competition_id', $concurses)
                ->get();
    
            $games = [];
    
            foreach ($draws as $draw) {
                if ($draw != null) {
                    $competition = DB::connection($data_partner['connection'])
                        ->table('competitions')
                        ->where('id', $draw->competition_id)
                        ->first();
    
                    $numbers_draw = array_map('intval', explode(',', $draw->games));
                    $num_tickets = count($numbers_draw);
    
                    $drawGames = DB::connection($data_partner['connection'])
                        ->table('games')
                        ->select(['games.id', 'clients.name as name', 'games.premio', 'games.status', 'type_games.name as game_name'])
                        ->join('clients', 'clients.id', '=', 'games.client_id')
                        ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                        ->where('games.checked', 1)
                        ->whereIn('games.id', $numbers_draw)
                        ->get();
    
                    foreach ($drawGames as $game) {
                        $game->sort_date = $competition->sort_date;
                        $game->num_tickets = $num_tickets;
                        $game->premio_formatted = $this->formatMoney($game->premio);
    
                        $games[] = $game;
                    }
                }
            }
    
            $winners = collect($games)
                ->groupBy('game_name')
                ->map(function ($group) {
                    return $group->sortByDesc('premio')->values()->all();
                })
                ->collapse()
                ->all();
    
            $winners = $this->consolidateResultsByGameName($winners);
            return $winners;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
    
    public function consolidateResultsByGameName($rawResults)
    {
        try {
            $consolidatedResults = collect($rawResults)
                ->groupBy('game_name')
                ->map(function ($group, $gameName) {
                    $consolidatedWinners = collect($group)->groupBy('name')->map(function ($winnerGroup) {
                        // Conta quantas vezes cada pessoa apareceu na modalidade
                        $occurrences = $winnerGroup->count();

                        $totalPrize = $winnerGroup->sum('premio');

                        return [
                            'id' => $winnerGroup->first()->id,
                            'name' => $winnerGroup->first()->name,
                            'premio' => number_format($totalPrize, 2, ',', '.'),
                            'status' => $winnerGroup->first()->status,
                            'game_name' => $winnerGroup->first()->game_name, // Certifique-se de que $gameName está definido aqui
                            'sort_date' => $winnerGroup->first()->sort_date,
                            'num_tickets' => $occurrences, // Adiciona a contagem de ocorrências
                            'premio_formatted' => 'R$ ' . number_format($totalPrize, 2, ',', '.'),
                        ];
                    })->values()->all();

                    return [
                        'game_name' => $gameName,
                        'winners' => $consolidatedWinners,
                    ];
                })
                ->sortBy('game_name')
                ->pluck('winners')
                ->collapse() // Flatten the array
                ->values()
                ->all();

            return $consolidatedResults;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function aprovePrize(Request $request)
    {
        try {
            $data = $request->all();
            $winners = [];
            $data_partner = Partner::findOrFail($data['partner']);

            $concurses = DB::connection($data_partner['connection'])
                ->table('competitions')
                ->whereDate('sort_date', '=', $data['date'])
                ->pluck('id');

            $draws = DB::connection($data_partner['connection'])
                ->table('draws')
                ->whereIn('competition_id', $concurses)
                ->get();

            $games = [];

            foreach ($draws as $draw) {
                if ($draw != null) {
                    $competition = DB::connection($data_partner['connection'])
                        ->table('competitions')
                        ->where('id', $draw->competition_id)
                        ->first();

                    $numbers_draw = array_map('intval', explode(',', $draw->games));
                    $num_tickets = count($numbers_draw);

                    $drawGames = DB::connection($data_partner['connection'])
                        ->table('games')
                        ->select(['games.id', 'clients.name as name', 'games.premio', 'games.status', 'type_games.name as game_name'])
                        ->join('clients', 'clients.id', '=', 'games.client_id')
                        ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                        ->where('games.checked', 1)
                        ->where('games.status', 1)
                        ->whereIn('games.id', $numbers_draw)
                        ->get();

                    foreach ($drawGames as $game) {
                        $game->sort_date = $competition->sort_date;
                        $game->num_tickets = $num_tickets;
                        $game->premio_formatted = $this->formatMoney($game->premio);

                        $games[] = $game;
                    }
                }
            }

            $winners = collect($games)
                ->groupBy('game_name')
                ->map(function ($group) {
                    return $group->sortByDesc('premio')->values()->all();
                })
                ->collapse()
                ->all();

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
    
            $allGameNames = [];
    
            foreach ($resultInMultiplePartners as $result) {
                $gameName = $result['game_name'] ?? null;
                $allGameNames[] = $gameName;
    
                $sortDate = Carbon::parse($result['sort_date'] ?? now())->format('d/m/Y');
                $num_tickets = $result['num_tickets'] ?? null;
    
                foreach ($winners as $key => $winner) {
                    // Check if the current winner is associated with the current game_name
                    if ($winner->game_name === $gameName) {
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
                            'num_tickets' => $num_tickets,
                            'premio_formatted' => $this->formatMoney($winnerPrize),
                        ];
                    }
                }
            }
    
            // Add fake winners for each unique game_name
            $uniqueGameNames = array_unique($allGameNames);
            foreach ($uniqueGameNames as $gameName) {
                $fakeWinners = $this->generateFakeWinners($numberOfPeople, $totalAmount, $gameName);
                $winnersList = array_merge($winnersList, $fakeWinners);
            }
    
            $mergedResults = array_merge($resultInMultiplePartners, $winnersList);
            $mergedResults = collect($mergedResults)->sortByDesc('premio')->values()->all();
            $mergedResults = $this->organizarPorCategoria($mergedResults);
    
            return response()->json($mergedResults, 200);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
    
    private function generateFakeWinners($numberOfWinners, $totalAmount, $gameName)
    {
        $fakeWinnersList = [];

        for ($i = 0; $i < $numberOfWinners; $i++) {
            $fakeWinner = People::inRandomOrder()->first(); // Obter um registro aleatório da tabela people
            $fakeWinnerFullName = $fakeWinner->first_name . ' ' . $fakeWinner->last_name;

            $winnerPrize = intval($totalAmount / $numberOfWinners);
            $winnerStatus = rand(1, 3);
            $winnerId = str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);

            $fakeWinnersList[] = [
                'id' => $winnerId,
                'name' => $fakeWinnerFullName,
                'premio' => $winnerPrize,
                'status' => $winnerStatus,
                'game_name' => $gameName,
                'premio_formatted' => $this->formatMoney($winnerPrize),
            ];
        }

        return $fakeWinnersList;
    }
    
    public function organizarPorCategoria($resultados)
    {
        // Ordenar os resultados por game_name
        usort($resultados, function ($a, $b) {
            return strcmp($a['game_name'], $b['game_name']);
        });

        return $resultados;
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
            $partners = isset($data['partners']) ? explode(',', $data['partners']) : [];

            $competitions = collect();

            foreach ($partners as $partnerId) {
                $partner = Partner::findOrFail($partnerId);

                $query = DB::connection($partner->connection)
                    ->table('competitions')
                    ->join('type_games', 'competitions.type_game_id', '=', 'type_games.id')
                    ->select(
                        'competitions.id',
                        'competitions.number',
                        'type_games.name as type_game_name',
                        'competitions.sort_date',
                        'competitions.created_at',
                        DB::raw($partnerId . ' as partner_id') // Adiciona o ID do parceiro
                    )
                    ->orderBy('competitions.created_at', 'desc')
                    ->where('competitions.sort_date', '>=', now());

                if (isset($data['number'])) {
                    $query->where('competitions.number', $data['number']);
                }

                $competitionsForPartner = $query->take(10)->get();

                // Format the sort_date field
                $competitionsForPartner->transform(function ($item) {
                    $item->sort_date = Carbon::parse($item->sort_date)->format('d-m-Y H:i:s');
                    return $item;
                });

                $competitions = $competitions->merge($competitionsForPartner);
            }

            return response()->json($competitions);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }




    public function deleteCompetition(Request $request)
    {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);

        $competition = DB::connection($data_partner['connection'])
            ->table('competitions')
            ->select('id', 'sort_date')
            ->where('id', $data['id'])
            ->first();

        if ($competition) {
            if (now() >= $competition->sort_date) {
                return response()->json(['error' => 'O concurso já foi sorteado. Não pode ser excluído.'], 400);
            }

            DB::connection($data_partner['connection'])
                ->table('competitions')
                ->where('id', $data['id'])
                ->delete();

            return response()->json(['success' => 'Competição excluída com sucesso.'], 200);
        } else {
            return response()->json(['error' => 'Competição não encontrada.'], 404);
        }
    }

    public function updateDrawNumbers(Request $request)
    {
        try {
            $data = $request->all();

            foreach ($data['partners'] as $partnerId) {
                $data_partner = Partner::findOrFail($partnerId);

                // Obtém os IDs das competições relacionadas ao número
                $competitionIds = DB::connection($data_partner['connection'])
                    ->table('competitions')
                    ->where('number', $data['number'])
                    ->pluck('id')
                    ->toArray();

                // Atualiza os números na tabela 'draws'
                DB::connection($data_partner['connection'])
                    ->table('draws')
                    ->whereIn('competition_id', $competitionIds)
                    ->update(['numbers' => $data['result']]);

                // Chama a função para atualizar os vencedores
                $this->updateWinners([
                    'partners' => [$partnerId],
                    'competitions' => [$competitionIds],
                ]);
            }

            return response()->json(['message' => 'Números atualizados com sucesso.'], 200);
        } catch (\Throwable $th) {
            // Lida com a exceção aqui
            throw new Exception($th);
        }
    }
    

    public function updateWinners($data)
    {
        try {
            foreach ($data['partners'] as $partnerId) {
                $data_partner = Partner::findOrFail($partnerId);
    
                foreach ($data['competitions'] as $competitionId) {
                    // Encontrar números na tabela de draws
                    $drawNumbers = DB::connection($data_partner->connection)
                        ->table('draws')
                        ->where('competition_id', $competitionId)
                        ->pluck('numbers')
                        ->toArray();
    
                    // Converter as strings de números em arrays
                    $drawNumbersArrays = array_map(function ($numbers) {
                        return explode(',', $numbers);
                    }, $drawNumbers);
    
                    // Encontrar jogos na tabela de games que contenham todos os números
                    $matchingGames = DB::connection($data_partner->connection)
                        ->table('games')
                        ->where('competition_id', $competitionId)
                        ->where(function ($query) use ($drawNumbersArrays) {
                            foreach ($drawNumbersArrays as $numbers) {
                                // Use a função FIND_IN_SET para verificar se os números estão presentes
                                $query->where(function ($query) use ($numbers) {
                                    foreach ($numbers as $number) {
                                        $query->whereRaw("FIND_IN_SET('$number', games.numbers) > 0");
                                    }
                                });
                            }
                        })
                        ->get();
    
                    // Retornar os IDs dos jogos encontrados
                    $gameIds = $matchingGames->pluck('id')->toArray();
    
                    // Atualizar os valores na tabela de draws
                    DB::connection($data_partner->connection)
                        ->table('draws')
                        ->where('competition_id', $competitionId)
                        ->update(['games' => implode(',', $gameIds)]);
                }
            }
    
            return response()->json(['message' => 'Vencedores Atualizados.'], 200);
        } catch (\Throwable $th) {
            // Lida com a exceção aqui
            throw new Exception($th);
        }
    }
    
    

}
