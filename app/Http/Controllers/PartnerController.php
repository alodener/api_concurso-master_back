<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGameInMultiplePartnersRequest;
use App\Models\Partner;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerController extends Controller
{

    public function index() {
        try {
            return Partner::all();
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function createGameInMultiplePartners(CreateGameInMultiplePartnersRequest $request) {
        try {
            $data = $request->all();

            foreach ($data['partners'] as $partner) {
                $data_partner = Partner::findOrFail($partner);
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
            throw new Exception($th);
        }
    }
}
