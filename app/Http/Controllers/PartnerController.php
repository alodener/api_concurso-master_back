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
                if($data['category'] != 'dupla_sena') {
                    foreach ($type_games as $type_game) {
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
                $data_partner = Partner::findOrFail($partner);
                $_SESSION['partner_send_result'] = $data_partner;
                $categorys = DB::connection($data_partner['connection'])->table('type_games')->where('category', $data['category'])->pluck('id');
                $concurses = DB::connection($data_partner['connection'])->table('competitions')->where('number', $data['number'])->whereIn('type_game_id', $categorys)->get();
                foreach ($concurses as $concurse) {
                    $concurses_id[] = $concurse->id;
                    $draws_id[] = DB::connection($data_partner['connection'])->table('draws')->insertGetId([
                        'type_game_id' => $concurse->type_game_id,
                        'competition_id' => $concurse->id,
                        'numbers' => $data['result'],
                        'created_at' => Carbon::now('America/Sao_Paulo'),
                        'updated_at' => Carbon::now('America/Sao_Paulo')
                    ]);
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

    public function getResultInMultiplePartners(Request $request) {

        try {
            $data = $request->all();
            $winners = [];
            $data_partner = Partner::findOrFail($data['partner']);
            $concurses = DB::connection($data_partner['connection'])->table('competitions')->where('number', $data['number'])->pluck('id');
            $draws = DB::connection($data_partner['connection'])->table('draws')->whereIn('competition_id',$concurses)->get();

            foreach ($draws as $draw) {
                if($draw != null) {
                    $numbers_draw = array_map('intval', explode(',', $draw->games));
                    $games = DB::connection($data_partner['connection'])
                    ->table('games')
                    ->select(['games.id', 'clients.name', 'games.premio' ,'games.status'])
                    ->join('clients', 'clients.id', '=', 'games.client_id')
                    ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                    ->where('games.checked', 1)
                    ->whereIn('games.id', $numbers_draw)
                    ->get();
                    foreach ($games as $game) {
                        array_push($winners, $game);
                    }
                }
            }
            return Response($winners, 200);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }

    }

    public function updateStatus(Request $request) {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);
        $game = DB::connection($data_partner['connection'])->table('games')->where('id', $data['id'])->update(['status' => $data['status']]);

        if($data['status'] == 2) {
            $data_game = DB::connection($data_partner['connection'])->table('games')->where('id', $data['id'])->first();
            $user_data = DB::connection($data_partner['connection'])->table('users')->where('id', $data_game->user_id)->first();
            
            $value = floatval($data_game->premio) + floatval($user_data->available_withdraw);
            $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $data_game->user_id)->update(['available_withdraw' => $value]);
            return Response($game, 200);
        }

        return Response($game, 200);
    }
}
