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
            $roles = ['super_admin', 'socio', 'admin'];

            if (in_array(Auth::user()->role, $roles)) {
                $data = $request->all();

                $validator = Validator::make($data, [
                    'banca' => 'required|string',
                    'modalidade' => 'required|integer',
                    'inicio' => 'required|date',
                    'fim' => 'required|date',
                    'bilhete_id' => 'nullable|string', // Validando bilhete_id como string opcional
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                $banca = $request->input('banca');
                $modalidade = $request->input('modalidade');
                $inicio = $request->input('inicio') . " 00:00";
                $fim = $request->input('fim') . " 23:59";
                $bilheteId = intval($request->input('bilhete_id')); // Convertendo para inteiro

                // CODIGO ANTIGO
                // $query = Apostas::whereBetween('created_at', [$inicio, $fim])
                //     ->where('tipo_jogo', $banca)
                //     ->where('jogo_id', $modalidade);
                // // Adicionando a condição para o bilhete_id, se ele foi fornecido na requisição
                // if ($bilheteId !== 0) { // Verifica se $bilheteId não é zero, que é o valor padrão de intval() se a conversão falhar
                //     $query->where('bilhete', $bilheteId);
                // }
                // $data = $query->distinct()->get(['id', 'nome_usuario', 'usuario_id', 'tipo_jogo', 'jogo', 'valor_aposta', 'valor_premio', 'created_at', 'bilhete', 'concurso']);
                // $dados = [];
                // $valorTotal = 0;
                // $totalBilhetes = 0;
                // $totalUsuarios = [];
                // $concursos = [];
                // foreach ($data as $info) {
                //     $valorTotal += floatval(str_replace(',', '.', $info->valor_aposta));
                //     $totalBilhetes += 1;
                //     if (!in_array($info->usuario_id, $totalUsuarios)) {
                //         $totalUsuarios[] = $info->usuario_id;
                //     }
                //     if (!in_array($info->concurso, $concursos)) {
                //         $concursos[] = $info->concurso;
                //     }
                //     $date = new DateTime($info->created_at);
                //     $dados['info'][] = [
                //         'id' => $info->id,
                //         'nome' => $info->nome_usuario,
                //         'tipo_jogo' => $info->tipo_jogo,
                //         'jogo' => $info->jogo,
                //         'valor' => number_format($info->valor_aposta, 2, ',', '.'),
                //         'premio' => number_format($info->valor_premio, 2, ',', '.'),
                //         'criacao' => $date->format('d/m/Y H:i:s'),
                //         'numeros' => $info->bilhete,
                //         'bilhete' => $info->jogo_id,
                //         'concurso' => $info->concurso,
                //         'usuario_id' => $info->usuario_id, // Adicionando usuario_id
                //     ];
                // }
                // $dados['concursos'] = implode(', ', $concursos);
                // $dados['totalUsuarios'] = count($totalUsuarios);
                // $dados['totalBilhetes'] = $totalBilhetes;
                // $dados['valorTotal'] = number_format($valorTotal, 2, ',', '.');

                // return response()->json(['success' => true, 'data' => $dados], 200);


                $banca = explode(',', $banca);
                if($banca) {
                    foreach ( $banca as $b ) {
                        $partner = Partner::findOrFail($b);

                        $sql = DB::connection($partner->connection)
                            ->table('draws')
                            ->where('draws.type_game_id', $modalidade)
                            ->where('competitions.sort_date', '>=', $inicio)
                            ->where('competitions.sort_date', '<=', $fim);

                        if($bilheteId) {
                            $sql->whereIn('games.id', $bilheteId);
                        }

                        $dados = $sql->leftJoin('competitions', 'draws.competition_id', '=', 'competitions.id')
                            ->leftJoin('games', 'competitions.id', '=', 'games.competition_id')
                            ->leftJoin('type_games', 'draws.type_game_id', '=', 'type_games.id')
                            ->leftJoin('users', 'games.user_id', '=', 'users.id')
                            ->orderBy('competitions.sort_date')
                            ->get([
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
                                'games.status'
                            ]);

                        $totalUsuarios = 0;
                        $totalPremios = 0;

                        $usuariosDistintos = [];

                        $a = $dados->each(function ($item) use (&$totalUsuarios, &$totalPremios, &$usuariosDistintos) {
                            $premio = (float)str_replace(',', '.', $item->valor_premio);
                            $totalPremios += $premio;
                            if (!in_array($item->name, $usuariosDistintos)) {
                                $usuariosDistintos[] = $item->name;
                            }
                        });

                        $totalUsuarios = count($usuariosDistintos);

                        $resultado = [
                            'total_usuarios' => $totalUsuarios,
                            'total_premios'  => number_format($totalPremios, 2, ',', '.'),
                            'total_bilhetes' => count($dados)
                        ];
                    }

                    return response()->json(['success' => true, 'data' => $dados, 'analytics' => $resultado], 200);
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
