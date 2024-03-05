<?php

namespace App\Http\Controllers;

use App\Models\Apostas;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class ApostasFeitasController extends Controller
{

    public function filter(Request $request){
        try {

             $roles = ['super_admin', 'socio', 'admin'];

             if (in_array(Auth::user()->role, $roles)) {

                $data = $request->all();

                $validator = Validator::make($data, [
                    'banca' => 'required|string',
                    'modalidade' => 'required|integer',
                    'inicio' => 'required|date',
                    'fim' => 'required|date',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                $banca = $request->input('banca');
                $modalidade = $request->input('modalidade');
                $inicio = $request->input('inicio')." 00:00";
                $fim = $request->input('fim')." 23:59";

                $data = Apostas::whereBetween('created_at', [$inicio, $fim])
                 ->where('tipo_jogo', $banca)
                 ->where('jogo_id', $modalidade)
                 ->get();
                $dados = [];
                $dados['info'] = [];
                $valorTotal = 0;
                $totalBilhetes = 0;
                $totalUsuarios = 0;
                $concursos = [];

                foreach($data as $info){

                    $valorTotal += $info['valor_aposta'];
                    $totalBilhetes += 1;
                    $totalUsuarios += 1;

                    $date = new DateTime($info['created_at']);

                    if(!in_array($info['concurso'], $concursos)){
                        array_push($concursos, $info['concurso']);
                    }

                    array_push($dados['info'], [
                        'id' => $info['id'],
                        'nome' => $info['nome_usuario'],
                        'tipo_jogo' => $info['tipo_jogo'],
                        'jogo' => $info['jogo'],
                        'valor' => number_format($info['valor_aposta'], 2, ',', '.'),
                        'premio' => number_format($info['valor_premio'], 2, ',', '.'),
                        'criacao' => $date->format('d/m/Y H:i:s'),
                        'bilhete' => $info['bilhete'],
                        'concurso' => $info['concurso'],
                    ]);
                }

                $dados['concursos'] = implode(', ', $concursos);

                return response()->json(['success' => true, 'data' => $dados], 200);
            }

            return response()->json(['success' => false], 403);
        }catch (\Throwable $th) {
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
