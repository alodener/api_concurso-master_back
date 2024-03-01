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

    public function show(Request $request){
        try {

            $roles = ['super_admin', 'socio', 'admin'];

            if (in_array(Auth::user()->role, $roles)) {

                $request->validate([
                    // 'banca' => 'required|exists:categories,id',
                    // 'modalide' => 'required|date',
                    'inicio' => 'required|date',
                    'fim' => 'required|date',
                ]);

                $banca = $request->input('banca_id');
                $inicio = $request->input('inicio');
                $fim = $request->input('fim');

                $data = Apostas::whereBetween('created_at', [$inicio, $fim])
                 ->where('banda_id', $banca)
                 ->get();
                $dados = [];
                $valorTotal = 0;
                $totalBilhetes = 0;
                $totalUsuarios = 0;

                foreach($data as $info){

                    $valorTotal += $info['valor_aposta'];
                    $totalBilhetes += 1;
                    $totalUsuarios += 1;

                    $date = new DateTime($info['created_at']);

                    array_push($dados, [
                        'id' => $info['id'],
                        'nome' => $info['nome_usuario'],
                        'tipo_jogo' => $info['tipo_jogo'],
                        'jogo' => $info['jogo'],
                        'valor' => number_format($info['valor_aposta'], 2, ',', '.'),
                        'criacao' => $date->format('d/m/Y H:i:s'),
                        'bilhetes' => $info['bilhetes'],
                    ]);
                }

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
                'usuario_id' => ['required', 'numeric', 'regex:/^\d+$/'],
                'tipo_jogo' => 'required|string|max:255',
                'tipo_jogo_id' => ['required', 'numeric', 'regex:/^\d+$/'],
                'jogo' => 'required|string|max:255',
                'jogo_id' => ['required', 'numeric', 'regex:/^\d+$/'],
                'bilhetes' => 'required|string|max:255',
                'valor_aposta' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/']
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
            
            $aposta = new Apostas();
            $aposta->nome_usuario = $data['nome_usuario'];
            $aposta->usuario_id = $data['usuario_id'];
            $aposta->tipo_jogo = $data['tipo_jogo'];
            $aposta->tipo_jogo_id = $data['tipo_jogo_id'];
            $aposta->jogo = $data['jogo'];
            $aposta->jogo_id = $data['jogo_id'];
            $aposta->bilhetes = $data['bilhetes'];
            $aposta->valor_aposta = floatval($data['valor_aposta']);
            $aposta->criacao_aposta = new DateTime();
            $aposta->save();

            return response()->json(['success' => true], 200);

            return $aposta;
            
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
}
