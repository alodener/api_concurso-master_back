<?php

namespace App\Http\Controllers;

use App\Models\Apostas;
use App\Models\Partner;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ApostasFeitasController extends Controller
{

    public function filter(Request $request)
    {
        try {
            $roles = ['super_admin', 'socio', 'admin', 'gerente_jogo'];

            if (in_array(Auth::user()->role, $roles)) {
                $data = $request->all();

                $validator = Validator::make($data, [
                    'banca' => 'required|string',
                    // 'modalidade' => 'required',
                    'data_sorteio' => 'required|date',
                    'bilhete_id' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                $banca          = $request->input('banca');
                $modalidade     = $request->input('modalidade');
                $data_sorteio   = $request->input('data_sorteio');
                $bilheteId      = ($request->input('bilhete_id'));
                $modalidade     = explode(',', $modalidade);
                $banca          = explode(',', $banca);

                $retorno        = [];
                $totalUsuarios  = 0;
                $totalPremios   = 0;
                $totalBilhetes  = 0;

                if($banca) {
                    foreach ( $banca as $b ) {
                        $partner = Partner::findOrFail($b);

                        if(!is_array($modalidade)) $modalidade = [$modalidade];

                        $sql = DB::connection($partner->connection)
                            ->table('competitions')
                            // ->whereIn('competitions.type_game_id', $modalidade)
                            ->whereDate('competitions.sort_date', $data_sorteio)
                            ->where('games.id', '!=', 'null')
                        ;

                        if($bilheteId) {
                            $bilheteId = explode(',', $bilheteId);

                            if(!is_array($bilheteId)) $bilheteId = [$bilheteId];

                            $sql->whereIn('games.id', $bilheteId);
                        }

                        $dados = $sql
                            ->leftJoin('games', 'competitions.id', '=', 'games.competition_id')
                            ->join('type_games', 'competitions.type_game_id', '=', 'type_games.id')
                            ->join('users', 'games.user_id', '=', 'users.id')
                            ->join('clients', 'games.client_id', '=', 'clients.id')
                            ->orderBy('competitions.sort_date')
                            ->get([
                                'competitions.id as competicao_id',
                                'games.id',
                                'type_games.name as tipo_jogo',
                                DB::raw('FORMAT(games.value, 2, "de_DE") as valor_aposta'),
                                DB::raw('FORMAT(games.premio, 2, "de_DE") as valor_premio'),
                                DB::raw("DATE_FORMAT(competitions.sort_date, '%d/%m/%Y %H:%i') as data_sorteio"),
                                DB::raw("DATE_FORMAT(games.created_at, '%d/%m/%Y %H:%i') as data_aposta"),
                                'games.random_game',
                                'competitions.number',
                                'games.numbers',
                                'users.name',
                                'users.email',
                                'games.status',
                                DB::raw('"'.$partner->name.'" as nome_banca'),
                                'clients.name as client_name',
                                'clients.id as client_id'
                            ]);

                        $usuariosDistintos = [];
                        $dados->each(function ($item) use (&$totalUsuarios, &$totalPremios, &$usuariosDistintos) {
                            $premio = (float)str_replace(',', '.', $item->valor_premio);
                            $totalPremios += $premio;
                            if (!in_array($item->name, $usuariosDistintos)) {
                                $usuariosDistintos[] = $item->name;
                            }
                        });

                        $totalUsuarios += count($usuariosDistintos);
                        $totalBilhetes += count($dados);

                        // array_merge($retorno, $dados->toArray());
                        array_push($retorno, ...$dados->toArray());
                    }

                    $resultado = [
                        'total_usuarios' => $totalUsuarios,
                        'total_premios'  => number_format($totalPremios, 2, ',', '.'),
                        'total_bilhetes' => count($retorno)
                    ];

                    return response()->json(['success' => true, 'data' => $retorno, 'analytics' => $resultado], 200);
                }

                return response()->json(['errors' => 'Nenhuma banca informada'], 400);
            }

            return response()->json(['success' => false], 403);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function store(Request $request)
    {
        try {

            $data = $request->all();

            $validator = Validator::make($data, [
                    'nome_usuario' => 'required|string|max:255',
                    'usuario_id' => 'required|integer|max:11',
                    'tipo_jogo' => 'required|string|max:255',
                    'jogo' => 'required|string|max:255',
                    'jogo_id' => 'required|integer|max:11',
                    'valor_aposta' => 'required|string|max:255',
                    'valor_premio' => 'required|string|max:255',
                    'numbers' => 'required|string',
                    'concurso' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $bilhetes = explode(',', $data['numbers']);

            foreach($bilhetes as $bilhete){
                $aposta = new Apostas();
                $aposta->nome_usuario = $data['nome_usuario'];
                $aposta->usuario_id = $data['usuario_id'];
                $aposta->tipo_jogo = $data['tipo_jogo'];
                $aposta->jogo = $data['jogo'];
                $aposta->jogo_id = $data['jogo_id'];
                $aposta->bilhete = $bilhete;
                $aposta->valor_aposta = floatval($data['valor_aposta']);
                $aposta->valor_premio = floatval($data['valor_premio']);
                $aposta->concurso = $data['concurso'];
                $aposta->save();
            }

            return response()->json(['success' => true], 200);

        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
}
