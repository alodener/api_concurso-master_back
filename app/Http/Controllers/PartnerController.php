<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGameInMultiplePartnersRequest;
use App\Models\Log;
use App\Models\Partner;
use Carbon\Carbon;
use PDF;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Return_;
use App\Models\People;
use App\Models\WinnersList;
use DateTime;

class PartnerController extends Controller
{
    function processarParceiros() {
        $bancasAtualizadas = []; // Inicializa o array para armazenar os IDs das bancas atualizadas

        // Obtém todos os parceiros
        $parceiros = DB::table('partners')->get();

        foreach ($parceiros as $parceiro) {
            // Obtém o ID e o nome do parceiro
            $idParceiro = $parceiro->id;
            $nameParceiro = $parceiro->name;

            // Verifica se já existe um registro para esse partner_id
            $registroExistente = DB::connection($parceiro->connection)
                ->table('system')
                ->where('nome_config', 'partner_id')
                ->where('value', $idParceiro)
                ->exists();

            // Se não existir um registro para esse partner_id, insere os registros
            if (!$registroExistente) {
                DB::connection($parceiro->connection)->table('system')->insert([
                    ['nome_config' => 'partner_id', 'value' => $idParceiro, 'created_at' => now(), 'updated_at' => now()],
                    ['nome_config' => 'partner_name', 'value' => $nameParceiro, 'created_at' => now(), 'updated_at' => now()]
                    // Você pode adicionar mais campos aqui, se necessário
                ]);

                // Adiciona o ID da banca ao array de bancas atualizadas
                $bancasAtualizadas[] = $idParceiro;
            }
        }

        return $bancasAtualizadas; // Retorna o array de bancas atualizadas
    }

    function deletarRegistrosInseridosHoje() {
        // Obtém a data atual
        $hoje = now()->toDateString(); // Obtém a data atual no formato 'YYYY-MM-DD'

        // Obtém todos os parceiros
        $parceiros = Partner::all();

        // Passa por todos os parceiros
        foreach ($parceiros as $parceiro) {
            // Deleta os registros da tabela System inseridos hoje para o parceiro atual
            DB::connection($parceiro->connection)->table('system')->whereDate('created_at', '=', $hoje)->delete();
        }
    }

    public function index() {
        try {
            $partners = Partner::all();

            $allPartnersIds = $partners->pluck('id')->toArray();

            $allPartners = [
                'name' => 'Todas as bancas',
                'id' => $allPartnersIds,
                'connection' => 'todas_as_bancas',
            ];

            $partners->prepend($allPartners);

            return response()->json($partners, 200);

        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function type_games($partnerId) {
        try {
            $data_partner = Partner::findOrFail($partnerId);

            // Ajuste para pesquisa por data
            $type_games = DB::connection($data_partner['connection'])
                ->table('type_games')
                ->select('name', 'id')
                // ->where(function ($query) {
                //     $query->where('name', 'SLG-KINO LOTO')
                //           ->orWhere('name', 'SLG-RE-KINO LOTO')
                //           ->orWhere('name', 'SLG - PREMIOS ESPECIALES')
                //           ->orWhere('name', 'SLG - CHISPALOTO')
                //           ->orWhere('name', 'SLG-CHAO JEFE LOTO')
                //           ->orWhere('name', 'SLG-MEGA LOTTO')
                //           ->orWhere('name', 'SLG- MEGA KINO')
                //           ->orWhere('name', 'SLG - STª LUCIA DOUBLE');
                // })
                ->get();

            return response()->json($type_games, 200);

        } catch (\Throwable $th) {
            return response()->json(['error' => 'Erro ao recuperar os jogos do tipo.'], 500);
        }
    }



    public function gerarPDF(Request $request)
    {

        // Obter os dados do corpo da solicitação
        $requestData = $request->all();

        // return $requestData;
        // Dados que serão passados para a visualização do PDF
        $date = Carbon::createFromFormat('Y-m-d', $requestData['date'])->format('d/m/Y');

        $data = [
            'title' => 'Relatório Financeiro: ' . $date, // Título com a data formatada
            'totalValorLiquido' => $requestData['totalValorLiquido'],
            'totalPagBonus' => $requestData['totalPagBonus'],
            'totalPagPremios' => $requestData['totalPagPremios'],
            'totalPix' => $requestData['totalPix'],
            'totalRecargaManual' => $requestData['totalRecargaManual'],
            'winners' => $requestData['winners'], // Lista de ganhadores
        ];


        // return $data;

        // Carregar a visualização do PDF com os dados
        $pdf = PDF::loadView('pdf.exemplo', $data);

        // Definir o nome do arquivo e o tipo de conteúdo

        // Fazer o download do PDF
        return $pdf->download('relatorio_financeiro.pdf');
    }

    public function storeListWinners(Request $request)
    {
        try {
            // Recupera todos os dados do request
            $requestData = $request->all();

            // Atribui zero aos valores de fakes e premio se forem null
            $fakes = $requestData['fakes'] !== null && is_numeric($requestData['fakes']) ? $requestData['fakes'] : 0;
            $premio = $requestData['premio'] !== null && is_numeric($requestData['premio']) ? $requestData['premio'] : 0;

            // Verifica se há itens na lista de ganhadores
            if (isset($requestData['winners2']) && !empty($requestData['winners2'])) {
                $groupedByBanca = [];

                foreach ($requestData['winners2'] as $winner) {
                    $id_banca = $winner['id_banca'];

                    if (!isset($groupedByBanca[$id_banca])) {
                        $groupedByBanca[$id_banca] = [];
                    }
                    $groupedByBanca[$id_banca][] = $winner;
                }

                foreach ( $groupedByBanca as $idBanca => $gBanca ) {
                    $existingWinnersList = WinnersList::where('banca_id', $idBanca)
                        ->where('sort_date', $request['sort_date'])
                        ->first();

                    if ($existingWinnersList) {
                        $oldJson = is_array($existingWinnersList->json) ? $existingWinnersList->json : json_decode($existingWinnersList->json, true) ?? [];
                        $newWinners = $gBanca;

                        $existingIds = array_column($oldJson, 'id');
                        $newEntries = [];

                        foreach ($newWinners as $winner) {
                            if (!in_array($winner['id'], $existingIds)) {
                                $newEntries[] = $winner;
                            }
                        }

                        $updatedJson = array_merge($oldJson, $newEntries);

                        $existingWinnersList->update([
                            'fake_winners' => $fakes,
                            'fake_premio' => $premio,
                            'json' => json_encode($updatedJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        ]);
                    } else {
                        $winnersList = WinnersList::insert([
                            'banca_id' => $idBanca,
                            'fake_winners' => $fakes,
                            'fake_premio' => $premio,
                            'sort_date' => $request['sort_date'],
                            'json' => json_encode($gBanca, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                }

                return response()->json($requestData['winners2'], 200);
            } else {
                return response()->json(['error' => 'Lista de ganhadores vazia.'], 400);
            }
        } catch (\Exception $e) {
            // Retorna uma resposta HTTP 500 Internal Server Error caso ocorra um erro
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function formatTableContentFromRequest(Request $request) {
        try {
            // Recupera os dados da requisição
            $requestData = $request->all();

            // Consulta no banco de dados para obter os dados da tabela 'winners_lists' com base na banca e na data do sorteio
            $winnersList = WinnersList::where('banca_id', $requestData['partner'])
                                        ->whereDate('sort_date', $requestData['sort_date'])
                                        ->first();

            if (!$winnersList) {
                return response()->json(['error' => 'Nenhum registro encontrado para a banca e data do sorteio especificados'], 404);
            }

            // Decodifica o JSON armazenado na coluna 'json' para obter os dados dos ganhadores
            $winners2 = json_decode($winnersList->json, true);

            // Inicia as variáveis para cálculo do total de prêmios e bilhetes
            $totalGeral = 0;
            $totalTickets = 0;
            $groupedByGame = [];

            // Calcula o total geral de prêmios e total de bilhetes, e agrupa os ganhadores pelo nome do jogo
            foreach ($winners2 as $winner) {
                $valorNumerico = is_string($winner['premio']) ? floatval(str_replace(['.', ','], ['', '.'], $winner['premio'])) : $winner['premio'];
                $totalGeral += $valorNumerico;
                $totalTickets += floatval($winner['num_tickets']);

                if (!isset($groupedByGame[$winner['game_name']])) {
                    $groupedByGame[$winner['game_name']] = [];
                }

                $groupedByGame[$winner['game_name']][] = $winner;
            }

            // Obtém o nome do parceiro selecionado a partir dos dados do registro de winnersList
            $partnerSelectedName = $winnersList->name;

            // Inicia a formatação do conteúdo da tabela
            $formattedContent = "🤑 {$partnerSelectedName} 🤑\n";
            $formattedContent .= "SORTEIOS DO DIA: {$winners2[0]['sort_date']}\n";
            $formattedContent .= "PREMIAÇÕES GERAIS: " . number_format($totalGeral, 2, ',', '.') . " 💰\n";
            $formattedContent .= "TOTAL DE BILHETES: $totalTickets\n";

            // Percorre os ganhadores agrupados por jogo e adiciona ao conteúdo formatado
            foreach ($groupedByGame as $gameName => $winners) {
                $formattedContent .= "\n🟡 $gameName\n";
                $totalPrizeByGame = 0;

                foreach ($winners as $winner) {
                    $formattedContent .= "✔️ {$winner['name']}, {$winner['num_tickets']} cupons\n";
                    $formattedContent .= "💰 Prêmio: {$winner['premio_formatted']}\n\n";
                    $valorNumerico = is_string($winner['premio']) ? floatval(str_replace(['.', ','], ['', '.'], $winner['premio'])) : $winner['premio'];
                    $totalPrizeByGame += $valorNumerico;
                }

                $formattedContent .= "Total de Prêmios 💰 " . number_format($totalPrizeByGame, 2, ',', '.') . " 💰\n";
            }

            // Retorna a resposta com o conteúdo formatado
            return response()->json(['formatted_content' => $formattedContent], 200);
        } catch (\Exception $e) {
            // Retorna uma resposta HTTP 500 Internal Server Error caso ocorra um erro
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getWinnersListByBancaAndDate(Request $request)
    {
        try {
            // Recupera o banca_id e a data da requisição
            $bancaId = $request->input('partner');
            $createdAt = $request->input('sort_date');

            // Busca os registros na tabela winners_lists com base no banca_id e created_at
            $winnersList = WinnersList::where('banca_id', $bancaId)
                ->whereDate('sort_date', $createdAt)
                ->select('json')
                ->get();

            // Inicializa um array para armazenar os dados formatados
            $formattedData = [];

           // Itera sobre os registros encontrados e decodifica o JSON em cada um deles
            foreach ($winnersList as $record) {
                $jsonData = is_array($record->json) ? $record->json : json_decode($record->json, true);

                // Adiciona os dados decodificados ao array formatado
                foreach ($jsonData as $item) {
                    // Converte o prêmio para inteiro, removendo os caracteres não numéricos
                    $premioInt = intval(str_replace(['R$ ', '.', ','], ['', '', '.'], $item['premio']));

                    // Substitui o prêmio original pelo valor inteiro convertido
                    $item['premio'] = $premioInt;

                    // Verifica se o campo 'premio' é um número ou uma string
                    if (is_numeric($premioInt)) {
                        // Formata o campo 'premio' como uma string formatada em moeda
                        $item['premio_formatted'] = 'R$ ' . number_format($premioInt, 2, ',', '.');
                    } else {
                        // Mantém o campo 'premio_formatted' como está
                        $item['premio_formatted'] = $item['premio'];
                    }

                    // Adiciona o item formatado ao array de dados formatados
                    $formattedData[] = $item;
                }
            }

            // Retorna os dados formatados como JSON
            return response()->json($formattedData, 200);
        } catch (\Exception $e) {
            // Retorna uma resposta HTTP 500 Internal Server Error em caso de erro
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function getWinners(Request $request)
    {
        // Recuperar os parâmetros da requisição
        $partnerIds = explode(',', $request->input('partner'));
        $sort_date = $request->input('sort_date');

        // Consultar o banco de dados para cada parceiro e mesclar os resultados
        $winners = [];
        foreach ($partnerIds as $partner) {
            // Recuperar o nome da banca
            $partnerName = Partner::where('id', $partner)->value('name');

            // Consultar os vencedores da banca
            $partnerWinners = WinnersList::where('banca_id', $partner)
                ->whereDate('sort_date', $sort_date)
                ->get();

            // Mesclar os resultados no array principal
            foreach ($partnerWinners as $winner) {
                // Formatando a data para incluir apenas a data (sem hora)
                $winner->sort_date = date('Y-m-d', strtotime($winner->sort_date));

                // Adicionar o nome da banca ao resultado
                $winner->partner_name = $partnerName;
                $winners[] = $winner;
            }
        }

        // Retornar os resultados
        return response()->json($winners);
    }

    public function getWinnersListByBancaAndHours(Request $request)
{
    try {
        // Recupera o banca_id e as horas da requisição
        $bancaId = $request->input('partner');
        $hours = $request->input('hours');

        // Calcula a data e hora inicial subtraindo as horas fornecidas da data e hora atual
        $date = now()->subHours($hours);

        // Busca os registros na tabela winners_lists com base no banca_id e created_at nas últimas horas
        $winnersList = WinnersList::where('banca_id', $bancaId)
            ->where('created_at', '>=', $date)
            ->select('json')
            ->get();

        // Inicializa um array para armazenar os dados formatados
        $formattedData = [];

        // Itera sobre os registros encontrados e decodifica o JSON em cada um deles
        foreach ($winnersList as $record) {
            $jsonData = json_decode($record->json, true);

            // Adiciona os dados decodificados ao array formatado
            foreach ($jsonData as $item) {
                // Converte o prêmio para inteiro, removendo os caracteres não numéricos
                $premioInt = intval(str_replace(['R$ ', '.', ','], ['', '', '.'], $item['premio']));

                // Substitui o prêmio original pelo valor inteiro convertido
                $item['premio'] = $premioInt;

                // Verifica se o campo 'premio' é um número ou uma string
                if (is_numeric($premioInt)) {
                    // Formata o campo 'premio' como uma string formatada em moeda
                    $item['premio_formatted'] = 'R$ ' . number_format($premioInt, 2, ',', '.');
                } else {
                    // Mantém o campo 'premio_formatted' como está
                    $item['premio_formatted'] = $item['premio'];
                }

                // Adiciona o item formatado ao array de dados formatados
                $formattedData[] = $item;
            }
        }

        // Retorna os dados formatados como JSON
        return response()->json($formattedData, 200);
    } catch (\Exception $e) {
        // Retorna uma resposta HTTP 500 Internal Server Error em caso de erro
        return response()->json(['error' => $e->getMessage()], 500);
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
                             $letras = ['A', 'B', 'C', 'D', 'E', 'F'];
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
            if (!isset($data['category'])) {
                return response()->json(['error' => 'Category is missing'], 400);
            }
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


                $winners_users_ids = [];
                foreach ($draws_id as $draw) {
                    $draw_data = DB::connection($data_partner['connection'])->table('draws')->where('id',$draw)->first();
                    $competitions = DB::connection($data_partner['connection'])->table('games')->where('checked', 1)->where('competition_id', $draw_data->competition_id)->get();
                    foreach ($competitions as $competition) {
                        $numbers_result = array_map('intval', explode(',', $competition->numbers));
                        $count_numbers_correct = count(array_intersect($numbers_result, $resultsArray));
                        if(count($resultsArray) == $count_numbers_correct) {
                            $winners[] = $competition->id;
                            $winners_users_ids[$competition->id] = [$competition->user_id,$competition->premio,$draw];
                        }
                    }
                    if(is_countable($winners) && count($winners) > 0) {
                        $winners_string = strval(implode(",", $winners));
                        DB::connection($data_partner['connection'])->table('draws')->where('id',$draw)->update(['games' => $winners_string]);
                    }


                }
                $partner_id = $data_partner['id'];

                $data2 = [
                    "winners" => $winners,
                    "winners_users_ids" => $winners_users_ids
                ];
                $winners = null;
                $this->autoAprovePrizeToPartner($partner_id,$data2);
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

    public function Financial(Request $request)
    {
        set_time_limit(1200);
        try {
            // Obtém os IDs enviados na requisição
            $ids = $request->query('ids');

            // Verifica se os IDs foram enviados e se são válidos
            if ($ids) {
                // Divide os IDs em um array
                $idsArray = array_unique(explode(',', $ids));
                // Obtém todas as partners do banco de dados com base nos IDs fornecidos
                $partners = Partner::whereIn('id', $idsArray)->whereNotIn('connection', ['banca1', 'banca2'])->get();
            } else {
                // Se nenhum ID for enviado, obtém todas as partners do banco de dados
                $partners = Partner::whereNotIn('connection', ['banca1', 'banca2'])->get();
            }

            $groupedBalances = [];


            // Itera sobre todas as partners
            foreach ($partners as $partner) {
                // Inicializa o array de saldo agrupado para este parceiro
                $partnerBalances = [
                    'banca' => $partner->name,
                    'dep_pix' => 0,
                    'recarga_manual' => 0,
                    'pag_premios' => 0,
                    'pag_bonus' => 0,
                    'valor_liquido' => 0,
                    'dep_pix2' => 0,
                    'recarga_manual2' => 0,
                    'pag_premios2' => 0,
                    'pag_bonus2' => 0,
                    'valor_liquido2' => 0,

                ];


                $totalPrizeAmount = $this->getResultInMultiplePartners2($request, $partner->id);

                // Armazena o valor total dos prêmios na chave 'pag_premios' do array de saldo agrupado
                $partnerBalances['pag_premios'] =  $totalPrizeAmount;

                // Obtém os dados da requisição
                $data = $request->all();
                // Define a conexão com base na partner atual
                $connection = $partner->connection;

                // Consulta para transact_balance
                $transactBalances = DB::connection($connection)
                    ->table('transact_balance')
                    ->select('type', 'wallet', DB::raw('SUM(value) as total_value'))
                    ->whereDate('created_at', '=', $data['number'])
                    ->groupBy('type', 'wallet') // Adicionando 'wallet' aqui na cláusula GROUP BY
                    ->get();

                // Itera sobre os resultados da consulta
                foreach ($transactBalances as $balance) {
                    // Extrai o tipo e o valor total do saldo
                    $type = $balance->type;
                    $total_value = $balance->total_value;
                    $wallet = $balance->wallet;


                    // Remove os acentos e caracteres especiais do tipo para uniformizar
                    $type = preg_replace('/[^a-zA-Z0-9]/', ' ', $type);

                    // Verifica e acumula os valores correspondentes
                    if (strpos($type, 'Recarga efetuada por meio da plataforma') !== false) {
                        $partnerBalances['dep_pix'] += $total_value;
                    } elseif (strpos($type, 'Add por Admin') !== false) {
                        $partnerBalances['recarga_manual'] += $total_value;
                    } elseif (strpos($type, 'Saldo recebido a partir de Saque Disponível.') !== false) {
                        $partnerBalances['pag_premios'] += $total_value;
                    } elseif (strpos($wallet, 'bonus') !== false) {
                        $partnerBalances['pag_bonus'] += $total_value;
                    }
                }

                $valor_liquido = $partnerBalances['dep_pix'] + $partnerBalances['recarga_manual'] - $partnerBalances['pag_premios'] - $partnerBalances['pag_bonus'];


                $partnerBalances['dep_pix2'] = number_format($partnerBalances['dep_pix'], 2, '.', '');
                $partnerBalances['recarga_manual2'] = number_format($partnerBalances['recarga_manual'], 2, '.', '');
                $partnerBalances['pag_premios2'] = number_format($partnerBalances['pag_premios'], 2, '.', '');
                $partnerBalances['pag_bonus2'] = number_format($partnerBalances['pag_bonus'], 2, '.', '');
                $partnerBalances['valor_liquido2'] = number_format($valor_liquido, 2, '.', '');

                $partnerBalances['dep_pix'] = 'R$ ' . number_format($partnerBalances['dep_pix'], 2, ',', '.');
                $partnerBalances['recarga_manual'] = 'R$ ' . number_format($partnerBalances['recarga_manual'], 2, ',', '.');
                $partnerBalances['pag_premios'] = 'R$ ' . number_format($partnerBalances['pag_premios'], 2, ',', '.');
                $partnerBalances['pag_bonus'] = 'R$ ' . number_format($partnerBalances['pag_bonus'], 2, ',', '.');
                $partnerBalances['valor_liquido'] = 'R$ ' . number_format($valor_liquido, 2, ',', '.');



                // Adiciona o saldo agrupado para este parceiro ao array principal
                $groupedBalances[] = $partnerBalances;

                usort($groupedBalances, function($a, $b) {
                    $valorLiquidoA = $a['valor_liquido'];
                    $valorLiquidoB = $b['valor_liquido'];

                    // Remove o prefixo 'R$ ' e converte para float
                    $valorLiquidoA = floatval(str_replace(['R$ ', ',', '.'], ['', '.', ''], $valorLiquidoA));
                    $valorLiquidoB = floatval(str_replace(['R$ ', ',', '.'], ['', '.', ''], $valorLiquidoB));

                    // Ordena de forma decrescente
                    if ($valorLiquidoA == $valorLiquidoB) {
                        return 0;
                    }
                    return ($valorLiquidoA > $valorLiquidoB) ? -1 : 1;
                });
            }

            // Retorna o array agrupado por parceiro
            return $groupedBalances;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }


    public function getByBancaAndDate(Request $request)
    {
        $request->validate([
            'banca_id' => 'required|integer',
            'sort_date' => 'required|date',
        ]);

        $bancaId = $request->input('banca_id');
        $sortDate = $request->input('sort_date');

        $winners = WinnersList::where('banca_id', $bancaId)
                              ->whereDate('sort_date', $sortDate)
                              ->get();

        return response()->json($winners);
    }

    public function getResultInMultiplePartners2(Request $request, $partner)
    {
        try {
            $data = $request->all();
            $totalPrizeAmount = 0; // Inicializa o valor total como zero
            $data_partner = Partner::findOrFail($partner);

            // Ajuste para pesquisa por data
            $concurses = DB::connection($data_partner['connection'])
                ->table('competitions')
                ->whereDate('sort_date', '=', $data['number'])
                ->pluck('id');

            $draws = DB::connection($data_partner['connection'])
                ->table('draws')
                ->whereIn('competition_id', $concurses)
                ->get();

            foreach ($draws as $draw) {
                if ($draw != null) {
                    $drawGames = DB::connection($data_partner['connection'])
                        ->table('games')
                        ->select('premio')
                        ->where('checked', 1)
                        ->whereIn('id', explode(',', $draw->games))
                        ->get();

                    foreach ($drawGames as $game) {
                        $totalPrizeAmount += $game->premio; // Acumula o valor do prêmio
                    }
                }
            }

            return $totalPrizeAmount; // Retorna o valor total somado
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }







    public function getResultsBichao(Request $request)
    {
        try {
            $data = $request->all();
            $data_partner = Partner::findOrFail($data['partner']);
            $connection = $data_partner->connection;

            // Consulta para buscar as informações na tabela bichao_games_vencedores
            $bichao_games_vencedores = DB::connection($connection)
                ->table('bichao_games_vencedores')
                ->select(
                    'bichao_games_vencedores.game_id',
                    DB::raw("CONCAT('R$ ', REPLACE(FORMAT(bichao_games_vencedores.valor_premio, 2), '.', ',')) as valor_premio"), // Formatação do valor_premio
                    'bichao_games.game_1',
                    'bichao_games_vencedores.status',
                    'bichao_horarios.banca',
                    DB::raw("CONCAT(clients.name, ' ', clients.last_name) as client_full_name"), // Concatenação do name e last_name
                    'bichao_modalidades.nome as modalidade_name'
                )
                ->leftJoin('bichao_games', 'bichao_games_vencedores.game_id', '=', 'bichao_games.id')
                ->leftJoin('clients', 'bichao_games.client_id', '=', 'clients.id')
                ->leftJoin('bichao_modalidades', 'bichao_games.modalidade_id', '=', 'bichao_modalidades.id')
                ->leftJoin('bichao_horarios', 'bichao_games.horario_id', '=', 'bichao_horarios.id')
                ->whereDate('bichao_games_vencedores.created_at', '=', $data['date'])
                ->where('bichao_games_vencedores.status', '=', 1)
                ->get();

            return $bichao_games_vencedores;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function getResultsBichao2(Request $request)
    {
        try {
            $data = $request->all();
            $data_partner = Partner::findOrFail($data['partner']);
            $connection = $data_partner->connection;

            // Consulta para buscar as informações na tabela bichao_games_vencedores
            $bichao_games_vencedores = DB::connection($connection)
                ->table('bichao_games_vencedores')
                ->select(
                    'bichao_games_vencedores.game_id',
                    DB::raw("CONCAT('R$ ', REPLACE(FORMAT(bichao_games_vencedores.valor_premio, 2), '.', ',')) as valor_premio"), // Formatação do valor_premio
                    'bichao_games.game_1',
                    'bichao_games_vencedores.status',
                    'bichao_horarios.banca',
                    DB::raw("CONCAT(clients.name, ' ', clients.last_name) as client_full_name"), // Concatenação do name e last_name
                    'bichao_modalidades.nome as modalidade_name'
                )
                ->leftJoin('bichao_games', 'bichao_games_vencedores.game_id', '=', 'bichao_games.id')
                ->leftJoin('clients', 'bichao_games.client_id', '=', 'clients.id')
                ->leftJoin('bichao_modalidades', 'bichao_games.modalidade_id', '=', 'bichao_modalidades.id')
                ->leftJoin('bichao_horarios', 'bichao_games.horario_id', '=', 'bichao_horarios.id')
                ->whereDate('bichao_games_vencedores.created_at', '=', $data['date'])
                // ->where('bichao_games_vencedores.status', '=', 1)
                ->get();

            return $bichao_games_vencedores;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function generateRandomGame($modalidade)
    {
        $modalidadesValidas = [
            'milhar', 'centena', 'dezena', 'grupo', 'milhar/centena',
            'terno de dezena', 'terno de grupos', 'duque de dezena',
            'duque de grupo', 'quadra de grupos', 'quina de grupos', 'unidade'
        ];

        // Converter a modalidade fornecida para minúsculas para garantir compatibilidade
        $modalidade = strtolower($modalidade);

        // Verificar se a modalidade fornecida está presente no array de modalidades válidas
        if (in_array($modalidade, $modalidadesValidas)) {
            $game = $this->generateRandomGameForModalidade($modalidade);
            return $game;
        } else {
            return response()->json(['error' => 'Modalidade não encontrada']);
        }
    }

    private function generateRandomGameForModalidade($modalidade)
    {
        switch ($modalidade) {
            case 'milhar':
                return rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);
            case 'centena':
                return rand(0, 9) . rand(0, 9) . rand(0, 9);
            case 'dezena':
                return rand(0, 9) . rand(0, 9);
            case 'grupo':
                return rand(1, 25);
            case 'milhar/centena':
                return rand(0, 9) . rand(0, 9) . rand(0, 9) . '/' . rand(0, 9) . rand(0, 9) . rand(0, 9);
            case 'terno de dezena':
                $dezenas = [];
                while (count($dezenas) < 3) {
                    $dezena = rand(0, 9) . rand(0, 9);
                    if (!in_array($dezena, $dezenas)) {
                        $dezenas[] = $dezena;
                    }
                }
                return implode(', ', $dezenas);
            case 'terno de grupos':
                $grupos = [];
                while (count($grupos) < 3) {
                    $grupo = rand(1, 25);
                    if (!in_array($grupo, $grupos)) {
                        $grupos[] = $grupo;
                    }
                }
                return implode(', ', $grupos);
            case 'duque de dezena':
                return rand(0, 9) . rand(0, 9) . '/' . rand(0, 9) . rand(0, 9);
            case 'duque de grupo':
                return rand(1, 25) . '/' . rand(1, 25);
            case 'quadra de grupos':
                $grupos = [];
                while (count($grupos) < 4) {
                    $grupo = rand(1, 25);
                    if (!in_array($grupo, $grupos)) {
                        $grupos[] = $grupo;
                    }
                }
                return implode(', ', $grupos);
            case 'quina de grupos':
                $grupos = [];
                while (count($grupos) < 5) {
                    $grupo = rand(1, 25);
                    if (!in_array($grupo, $grupos)) {
                        $grupos[] = $grupo;
                    }
                }
                return implode(', ', $grupos);
            case 'unidade':
                return rand(0, 9);
            default:
                return null; // Caso a modalidade não seja reconhecida
        }
    }


    public function distributePrizesBichao0(Request $request)
    {
        try {
            // Parâmetros extras
            $data = $request->all();

            $totalPrize = $data['premio'];
            $totalWinners = $data['ganhadores'];

            // Chama a função getResultsBichao para obter os resultados originais
            $originalResults = $this->getResultsBichao($request);

            // Array de modalidades e bancas
            $modalidades = ['Milhar', 'Centena', 'Dezena', 'Grupo', 'Milhar/Centena', 'Terno de Dezena', 'Terno de Grupos', 'Duque de Dezena', 'Duque de Grupo', 'Quadra de Grupos', 'Quina de Grupos', 'Unidade'];
            $bancas = ['PTM-RIO', 'PT-RIO', 'PTV-RIO', 'PTN-RIO', 'CORUJA-RIO', 'PT-SP', 'BANDEIRANTES', 'PTN-SP', 'LOOK', 'ALVORADA', 'MINAS-DIA', 'MINAS-NOITE', 'BA', 'LOTEP', 'LBR', 'LOTECE', 'FEDERAL'];

            // Array para armazenar os resultados combinados
            $combinedResults = [];

            // Seleciona uma quantidade aleatória de pessoas
            $randomPeople = People::inRandomOrder()->limit($totalWinners)->get();

            // Distribui o prêmio para cada ganhador
            foreach ($randomPeople as $person) {
                // Modalidade aleatória
                $modalidade = $modalidades[array_rand($modalidades)];

                // Banca aleatória
                $banca = $bancas[array_rand($bancas)];

                // Adiciona o ganhador e o prêmio ao array de resultados
                $combinedResults[] = [
                    'game_id' => mt_rand(10000, 99999), // Gera um número aleatório de 6 dígitos
                    'valor_premio' => 'R$ ' . number_format($totalPrize / $totalWinners, 2, ',', '.'),
                    'game_1' => $this->generateRandomGame($modalidade),
                    'status' => 2,
                    'banca' => $banca, // Banca aleatória
                    'client_full_name' => $person->first_name . ' ' . $person->last_name,
                    'modalidade_name' => $modalidade,
                ];
            }

            // Mescla os resultados originais com os novos resultados
            $mergedResults = array_merge($originalResults->toArray(), $combinedResults);

            return $mergedResults;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function distributePrizesBichao(Request $request)
    {
        try {
            // Parâmetros extras
            $data = $request->all();

            $totalPrize = floatval(str_replace(',', '.', str_replace('.', '', str_replace('R$ ', '', $data['premio']))));
            $totalWinners = $data['ganhadores'];

            // Chama a função getResultsBichao para obter os resultados originais
            $originalResults = $this->getResultsBichao($request);

            // Array de modalidades e bancas
            $modalidades = ['Milhar', 'Centena', 'Dezena', 'Grupo', 'Milhar/Centena', 'Terno de Dezena', 'Terno de Grupos', 'Duque de Dezena', 'Duque de Grupo', 'Quadra de Grupos', 'Quina de Grupos', 'Unidade'];
            $bancas = ['PTM-RIO', 'PT-RIO', 'PTV-RIO', 'PTN-RIO', 'CORUJA-RIO', 'PT-SP', 'BANDEIRANTES', 'PTN-SP', 'LOOK', 'ALVORADA', 'MINAS-DIA', 'MINAS-NOITE', 'BA', 'LOTEP', 'LBR', 'LOTECE', 'FEDERAL'];

            // Array para armazenar os resultados combinados
            $combinedResults = [];


            // Seleciona uma quantidade aleatória de pessoas
            $randomPeople = People::inRandomOrder()->limit($totalWinners)->get();

            // Distribui o prêmio para cada ganhador
            $remainingPrize = $totalPrize;
            foreach ($randomPeople as $key => $person) {
                // Se for o último ganhador, ele recebe o valor restante
                if ($key == $totalWinners - 1) {
                    $prize = $remainingPrize;
                } else {
                    $prize = mt_rand(1, ceil($remainingPrize / ($totalWinners - $key)));
                }

                $remainingPrize -= $prize; // Atualiza o valor do prêmio restante

                // Seleciona uma modalidade e banca aleatória
                $modalidade = $modalidades[array_rand($modalidades)];
                $banca = $bancas[array_rand($bancas)];

                // Adiciona o ganhador e o prêmio ao array de resultados
                $combinedResults[] = [
                    'game_id' => mt_rand(10000, 99999), // Gera um número aleatório de 6 dígitos
                    'valor_premio' => 'R$ ' . number_format($prize, 2, ',', '.'), // Formata o valor do prêmio
                    'game_1' => $this->generateRandomGame($modalidade), // Modalidade aleatória
                    'status' => 2,
                    'banca' => $banca, // Banca aleatória
                    'client_full_name' => $person->first_name . ' ' . $person->last_name,
                    'modalidade_name' => $modalidade,
                ];
            }

            // Mescla os resultados originais com os novos resultados
            $mergedResults = array_merge($originalResults->toArray(), $combinedResults);

            return $mergedResults;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    public function getTypeIdGames($partner, $gametype)
    {
        // Supondo que $this->type_games($partner) retorne um JsonResponse
        $response = $this->type_games($partner);
        $typeGames = json_decode($response->getContent(), true); // Converte o JSON para um array associativo

        $gameNames = [];
        switch ($gametype) {
            case 'loteria_brasileira':
                $gameNames = [
                    'SLG-KINO LOTO',
                    'SLG-RE-KINO LOTO',
                    'SLG - PREMIOS ESPECIALES',
                    'SLG-CHAO JEFE LOTO',
                    'SLG-MEGA LOTTO',
                    'SLG- MEGA KINO',
                    'SLG - CHISPALOTO',
                    'SLG - STª LUCIA DOUBLE',
                    'Super Quina',
                    'SLG-MEGA LOTTO',
                ];
                break;
            case 'loteria_chile':
                $gameNames = [
                    'SLG-KINO LOTO',
                    'SLG-RE-KINO LOTO',
                    'SLG - PREMIOS ESPECIALES',
                    'SLG-CHAO JEFE LOTO',
                    'SLG-MEGA LOTTO',
                    'SLG- MEGA KINO'
                ];
                break;
            case 'loteria_mexico':
                $gameNames = ['SLG - CHISPALOTO'];
                break;
            case 'loteria_santa_lucia':
                $gameNames = ['SLG - STª LUCIA DOUBLE'];
                break;
            case 'loteria_polonia':
                $gameNames = ['Super Quina'];
                break;
            case 'loteria_reino_unido':
                $gameNames = ['SLG-MEGA LOTTO'];
                break;
            default:
                return []; // Se o tipo de jogo não for encontrado, retorna um array vazio
        }

        // Filtra os jogos baseados nos nomes fornecidos
        $filteredGames = array_filter($typeGames, function($game) use ($gameNames) {
            return in_array($game['name'], $gameNames);
        });

        // Extrai os IDs dos jogos filtrados
        $gameIds = array_column($filteredGames, 'id');

        return $gameIds; // Retorna os IDs dos jogos filtrados
    }




    public function getResultInMultiplePartners(Request $request)
    {
        try {

            $data = $request->all();
            $winners = [];
            $winnersAll = [];

            $ids = array_filter(explode(',', preg_replace('/[^0-9,]/', '', $data['partner'])), 'strlen');

            // $ids = [2,3];

            $partner = Partner::whereIn('id', $ids)->get();

            foreach ($partner as $key => $data_partner) {
                $nome_banca = $data_partner['name'];
                $id_banca = $data_partner['id'];

                try {
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

                            $drawGamesQuery = DB::connection($data_partner['connection'])
                                ->table('games')
                                ->select([
                                    'games.id',
                                    // DB::raw("CONCAT(clients.name, ' ', clients.last_name) as `name`"),
                                    'clients.name',
                                    'games.premio',
                                    'games.status',
                                    'type_games.name as game_name',
                                    'type_games.category as categoria',
                                ])
                                ->join('clients', 'clients.id', '=', 'games.client_id')
                                ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                                ->where('games.checked', 1)
                                ->whereIn('games.id', $numbers_draw);

                            if (isset($data['groupgame'])) {
                                if($data['groupgame'] == "loteria_brasileira"){
                                    $gameIds = $this->getTypeIdGames($data['partner'],$data['groupgame']);
                                    $drawGamesQuery->whereNotIn('games.type_game_id', $gameIds);
                                }else{
                                $gameIds = $this->getTypeIdGames($data['partner'],$data['groupgame']);
                                $drawGamesQuery->whereIn('games.type_game_id', $gameIds);}
                            }

                            if (isset($data['modalidade']) && !empty($data['modalidade'])) {
                                $drawGamesQuery->where(function ($query) use ($data) {
                                    $query->where('type_games.country', $data['modalidade'])
                                          ->orWhere('type_games.id', $data['modalidade']);
                                });
                            }

                            $drawGames = $drawGamesQuery->get();

                            foreach ($drawGames as $game) {
                                $game->id_banca = $id_banca;
                                $game->banca = $nome_banca;
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
                    $winnersAll = array_merge_recursive($winnersAll, $winners);
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }

            return $winnersAll;
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
                            'banca' => $winnerGroup->first()->banca,
                            'categoria' => $winnerGroup->first()->categoria,
                            'id_banca' => $winnerGroup->first()->id_banca,
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

            if(!isset($data['partner'])) {
                throw new Exception("Nenhuma banca informada");
            }
            date_default_timezone_set('America/Sao_Paulo');
            $data_auto_aprovacao = date('Y-m-d');
            if( $data['date'] ) {
                $data_auto_aprovacao = $data['date'];
            }

            // Busca informações do parceiro
            $data_partner = Partner::findOrFail($data['partner']);
            $valorMaximo    = $data_partner['min_value_autoaprovation'];
            $partnerId      = $data_partner['id'];


            // Busca os IDs das competições para a data especificada
            $competitionIds = DB::connection($data_partner['connection'])
                ->table('competitions')
                ->whereDate('sort_date', '=', $data['date'])
                ->pluck('id');

            // Busca os sorteios e os respectivos jogos de uma só vez, evitando múltiplas consultas
            $drawsGames = DB::connection($data_partner['connection'])
                ->table('draws')
                ->whereIn('competition_id', $competitionIds)
                ->get();

            $numbersDraw = $drawsGames->flatMap(function ($draw) {
                return explode(',', $draw->games);
            })->unique()->all();

            // Busca todos os jogos relacionados aos sorteios de uma vez
            $gamesInfo = DB::connection($data_partner['connection'])
                ->table('games')
                ->select([
                    'games.id',
                    DB::raw("CONCAT(clients.name, ' ', COALESCE(clients.last_name, '')) as name"),
                    'games.premio',
                    'games.status',
                    'games.random_game',
                    'type_games.name as game_name',
                    'competitions.sort_date'
                ])
                ->join('clients', 'clients.id', '=', 'games.client_id')
                ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                ->join('competitions', 'competitions.id', '=', 'games.competition_id')
                ->whereIn('games.id', $numbersDraw)
                ->where('games.checked', 1)
                ->where(function($query) {
                    $query->where('games.status', 1)
                        ->orWhere('games.status', 2)
                        ->orWhere('games.status', 3)
                        ->orWhere('games.status', 4);
                })
                ->get();


            // Processa os jogos para adicionar informações adicionais
            $processedGames = $gamesInfo->map(function ($game) {
                $game->premio_formatted = $this->formatMoney($game->premio);
                $game->random_game = $game->random_game == 1 ? 'Sim' : 'Não';
                return $game;
            });

            // Agrupa os jogos processados por nome do jogo e nome do cliente, e soma os prêmios
            $winners = $processedGames
                ->groupBy(function ($item) {
                    // Chave de agrupamento combinada
                    return $item->game_name . '|' . $item->name;
                })
                ->map(function ($group) {
                    $first = $group->first();
                    $sumPremio = $group->sum('premio');
                    return [
                        'id' => $group->pluck('id')->all(),
                        'total_bilhetes' => count($group->pluck('id')->all()),
                        'name' => $first->name,
                        'premio' => $sumPremio,
                        'status' => $first->status,
                        'random_game' => $first->random_game,
                        'game_name' => $first->game_name,
                        'sort_date' => $first->sort_date,
                        'premio_formatted' => $this->formatMoney($sumPremio)
                    ];
                })->values()->all();

            return $winners;
        } catch (\Throwable $th) {
            throw new Exception($th);
        } catch (\Exception $th) {
            return response()->json([
                'error' => true,
                'msg' => $th->getMessage()
            ]);
        }
    }

    public function gameInformations(Request $request)
    {
        try {
            $data = $request->all();

            if(!isset($data['partner'])) {
                throw new Exception("Nenhuma banca informada");
            }

            // Busca informações do parceiro
            $data_partner = Partner::findOrFail($data['partner']);

            // Busca todos os jogos relacionados aos sorteios de uma vez
            $gamesInfo = DB::connection($data_partner['connection'])
                ->table('games')
                ->select([
                    'games.id',
                    DB::raw("CONCAT(clients.name, ' ', COALESCE(clients.last_name, '')) as name"),
                    'games.premio',
                    'games.status',
                    'games.random_game',
                    'type_games.name as game_name',
                    'competitions.sort_date',
                    DB::raw('"'.$data_partner['name'].'" as nome_banca')
                ])
                ->join('clients', 'clients.id', '=', 'games.client_id')
                ->join('type_games', 'type_games.id', '=', 'games.type_game_id')
                ->join('competitions', 'competitions.id', '=', 'games.competition_id')
                ->whereIn('games.id', json_decode($data['id']))
                ->where('games.checked', 1)
                ->where(function($query) {
                    $query->where('games.status', 1)
                        ->orWhere('games.status', 2)
                        ->orWhere('games.status', 3)
                        ->orWhere('games.status', 4);
                })
                ->get();

            // Processa os jogos para adicionar informações adicionais
            $processedGames = $gamesInfo->map(function ($game) {
                $game->premio_formatted = $this->formatMoney($game->premio);
                $game->random_game = $game->random_game == 1 ? 'Sim' : 'Não';
                return $game;
            });

            return $processedGames;

        } catch (\Throwable $th) {
            throw new Exception($th);
        } catch (\Exception $th) {
            return response()->json([
                'error' => true,
                'msg' => $th->getMessage()
            ]);
        }
    }

    private function formatMoney($value)
    {
        return $value >= 1000 ? 'R$ ' . number_format($value, 2, ',', '.') : 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function updateStatusBichao(Request $request) {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);
        $status = $data['status'];

        $total_premio = 0;
        $client_id = null;

        foreach ($data['ids'] as $id) {
            $data_game_winner = DB::connection($data_partner['connection'])->table('bichao_games_vencedores')->where('game_id', $id)->first();

            if($data['status'] == 2) {
                // Obtém o game_id da tabela de vencedores
                // Agora, busca os detalhes do jogo na tabela 'bichao_games' usando o game_id
                $data_game = DB::connection($data_partner['connection'])->table('bichao_games')->where('id', $data_game_winner->game_id)->first();

                if($data_game) {
                    $total_premio += floatval($data_game_winner->valor_premio);
                    $client_id = $data_game->client_id; // Assume que todos os jogos são do mesmo cliente
                }
            }

            $game = DB::connection($data_partner['connection'])->table('bichao_games_vencedores')->where('game_id', $id)->update(['status' => $status]);
        }

        if($total_premio > 0 && $client_id) {
            // Busca os detalhes do cliente na tabela 'clients' usando o client_id
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($client_id);
            if($client_data) {
                $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
                if($user_data) {
                    $old_value = floatval($user_data->available_withdraw);  // Recupera o valor antigo do saldo disponível
                $new_value = $total_premio + $old_value;
                $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                DB::connection($data_partner['connection'])->table('transact_balance')->insert([
                    'user_id_sender' => $user_data->id,
                    'user_id' => $user_data->id,
                    'value' => $total_premio,
                    'old_value' => $old_value,
                    'value_a' => $new_value,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'type' => 'Premiacao JB id: ' .  $game_id,
                    'wallet' => 'Premiacao'
                ]);
                } else {
                    // Atualiza o usuário padrão se o usuário específico não for encontrado
                    $user_data_default = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                    if($user_data_default) {
                        $old_value = floatval($user_data_default->available_withdraw);  // Recupera o valor antigo do saldo disponível
                    $new_value = $total_premio + $old_value;
                    $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data_default->id)->update(['available_withdraw' => $new_value]);
                    DB::connection($data_partner['connection'])->table('transact_balance')->insert([
                        'user_id_sender' => $user_data_default->id,
                        'user_id' => $user_data_default->id,
                        'value' => $total_premio,
                        'old_value' => $old_value,
                        'value_a' => $new_value,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'type' => 'Premiacao JB id: ' .  $game_id,
                        'wallet' => 'Premiacao'
                    ]);
                    }
                }
            }
        }

        return response()->json(["message" => "Alteração finalizada com sucesso!"], 200);
    }





    public function updateStatus(Request $request) {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);

        $total_premio = 0;
        $client_id = null;

        foreach ($data['id'] as $id) {
            $data_game = DB::connection($data_partner['connection'])->table('games')->where('id', $id)->first();

            if($data['status'] == 2 && $data_game) {
                $total_premio += floatval($data_game->premio);
                $client_id = $data_game->client_id; // Assume que todos os jogos são do mesmo cliente

                $data_draw = DB::connection($data_partner['connection'])->table('draws')->where('competition_id', $data_game->competition_id)->first();
                if(isset($data_draw)){
                    DB::connection($data_partner['connection'])->table('winning_ticket')->insertGetId([
                        'user_id' => $data_game->user_id,
                        'game_id' => $data_game->id,
                        'draw_id' => $data_draw->id,
                        'drawed_at' => Carbon::now('America/Sao_Paulo'),
                    ]);
                }
            }

            $game = DB::connection($data_partner['connection'])->table('games')->where('id', $id)->update(['status' => $data['status']]);
        }

        if($total_premio > 0 && $client_id) {
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($client_id);
            if($client_data) {
                $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
                if($user_data) {
                    $old_value = floatval($user_data->available_withdraw);  // Recupera o valor antigo do saldo disponível
                        $new_value = $total_premio + $old_value;
                        $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                            DB::connection($data_partner['connection'])->table('transact_balance')->insert([
                                'user_id_sender' => $user_data->id,
                                'user_id' => $user_data->id,
                                'value' => $total_premio,
                                'old_value' => $old_value,
                                'value_a' => $new_value,
                                'created_at' => now(),
                                'updated_at' => now(),
                                'type' => 'Premiacao de jogo loteria id: ' .  $request->partner,
                                'wallet' => 'Premiacao'
                            ]);
                } else {
                    // Atualiza o usuário padrão se o usuário específico não for encontrado
                    $user_data_default = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                    if($user_data_default) {
                        $old_value = floatval($user_data_default->available_withdraw);  // Recupera o valor antigo do saldo disponível
                        $new_value = $total_premio + $old_value;
                        $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data_default->id)->update(['available_withdraw' => $new_value]);
                            DB::connection($data_partner['connection'])->table('transact_balance')->insert([
                            'user_id_sender' => $user_data_default->id,
                            'user_id' => $user_data_default->id,
                            'value' => $total_premio,
                            'old_value' => $old_value,
                            'value_a' => $new_value,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'type' => 'Premiacao de jogo loteria id: ' .  $request->partner,
                            'wallet' => 'Premiacao'
                            ]);
                    }
                }
            }
        }

        return response()->json(["message" => "Alteração finalizada com sucesso!"], 200);
    }



    private function getAllAvailableGameNames()
    {
        $gameNames = [''];

        return $gameNames;
    }

    private function getGameName($partnerId, $idgame)
{
    // Recupera as informações do parceiro
    $data_partner = Partner::findOrFail($partnerId);

    // Consulta a tabela type_games na conexão específica do parceiro, filtrando pelo id do jogo
    $type_game_name = DB::connection($data_partner['connection'])
            ->table('type_games')
            ->where('id', $idgame) // Filtra pelos registros que correspondem ao idgame
            ->value('name'); // Retorna apenas o valor do campo 'name'

    return $type_game_name;
}



    public function distributePrizes(Request $request)
    {
        try {

            $data = $request->all();

            $partnerId = $request->partner;
            $idgame = $request->type_game;
            $totalAmount = $request->premio;
            $numberOfPeople = $request->ganhadores;
            $gameIds = $this->getTypeIdGames($data['partner'],$data['groupgame']);


            if ($numberOfPeople <= 0) {
                return response()->json(['message' => 'Número de pessoas deve ser maior que 0'], 422);
            }

            $distributionFactors = $this->generatePercentages($numberOfPeople);

            $winners = People::inRandomOrder()->limit($numberOfPeople)->get();

            $winnersList = [];

            $winners = $winners->sortByDesc(function ($winner) {
                return $winner->premio;
            });

            $resultInMultiplePartners = $this->getResultInMultiplePartners($request);
            $resultInMultiplePartners = array_values(array_filter($resultInMultiplePartners));


            $allGameNames = [];


            if (empty($resultInMultiplePartners)) {
                // Se não houver ganhadores previamente, obtenha todos os nomes de jogos disponíveis
                $allGameNames = $this->getAllAvailableGameNames(); // Implemente esta função conforme necessário
                $sortDate = Carbon::parse($result['sort_date'] ?? now())->setHour(16)->setMinute(0)->setSecond(0)->format('Y-m-d 16:00:00');
                $num_tickets = $result['num_tickets'] ?? null;
            } else {
                foreach ($resultInMultiplePartners as $result) {
                    $gameName = $result['game_name'] ?? null;
                    $allGameNames[] = $gameName;

                    $sortDate = Carbon::parse($result['sort_date'] ?? now())->setHour(16)->setMinute(0)->setSecond(0)->format('Y-m-d 16:00:00');
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
            }

            // Adicione ganhadores fictícios para cada nome de jogo único
            $uniqueGameNames = array_unique($allGameNames);
            $gmname = $this->getGameName($partnerId,$idgame); // Implemente esta função conforme necessário

            foreach ($uniqueGameNames as $gameName) {
                $fakeWinners = $this->generateFakeWinners($numberOfPeople, $totalAmount, $gameName, $sortDate);
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

    public function distributePrizes3(Request $request)
    {
        try {
            $totalAmount = $request->premio;
            $numberOfPeople = intval($request->fakes);
            $listaBase = $request->winners2;
            $listaBase2 = $request->winners3;


            if ($numberOfPeople <= 0) {
                return response()->json(['message' => 'Número de pessoas deve ser maior que 0'], 422);
            }


            $distributionFactors = $this->generatePercentages($numberOfPeople);

            $winners = People::inRandomOrder()->limit($numberOfPeople)->get();

            $winnersList = [];

            $winners = $winners->sortByDesc(function ($winner) {
                return $winner->premio;
            });

            $resultInMultiplePartners = $listaBase;
            $resultInMultiplePartners = array_values(array_filter($resultInMultiplePartners));

            $allGameNames = [];

            if (empty($resultInMultiplePartners)) {
                // Se não houver ganhadores previamente, obtenha todos os nomes de jogos disponíveis
                $allGameNames = $this->getAllAvailableGameNames(); // Implemente esta função conforme necessário
                $sortDate = Carbon::parse($result['sort_date'] ?? now())->setHour(16)->setMinute(0)->setSecond(0)->format('Y-m-d 16:00:00');
                $num_tickets = $request->num_tickets ?? null;
            } else {
                foreach ($resultInMultiplePartners as $result) {
                    $gameName = $result['game_name'] ?? null;
                    $banca = $result['banca'] ?? null;
                    $categoria = $result['categoria'] ?? null;
                    $id_banca = $result['id_banca'] ?? null;

                    $existe = !empty(array_filter($allGameNames, function ($game) use ($banca, $categoria) {
                        return $game['categoria'] === $categoria;
                        // return $game['banca'] === $banca && $game['categoria'] === $categoria;
                    }));

                    if (!$existe) {
                        $allGameNames[] = [
                            'name' => $gameName,
                            'banca' => $banca,
                            'categoria' => $categoria,
                            'id_banca' => $id_banca,
                        ];
                    }

                    $sortDate = Carbon::parse($result['sort_date'] ?? now())->setHour(16)->setMinute(0)->setSecond(0)->format('Y-m-d 16:00:00');
                    $num_tickets = $result['num_tickets'] ?? null;

                    foreach ($winners as $key => $winner) {
                        // Check if the current winner is associated with the current game_name
                        if ($winner->game_name === $gameName) {
                            $winnerFullName = ($winner->first_name ?? ''). ' ' . $winner->last_name;

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
                                'banca' => $banca,
                                'categoria' => $categoria,
                                'id_banca' => $id_banca,
                            ];
                        }
                    }
                }
            }

            // Adicione ganhadores fictícios para cada nome de jogo único
            // $uniqueGameNames = array_unique($allGameNames);

            foreach ($allGameNames as $gameName) {
                $fakeWinners = $this->generateFakeWinners($numberOfPeople, $totalAmount, $gameName['name'], $sortDate, $gameName['banca'], $gameName['categoria'], $gameName['id_banca']);
                $winnersList = array_merge($winnersList, $fakeWinners);
            }

            $mergedResults = null;
            $mergedResults = array_merge($listaBase2, $winnersList);
            $mergedResults = collect($mergedResults)->sortByDesc('premio')->values()->all();
            $mergedResults = $this->organizarPorCategoria($mergedResults);

            return response()->json($mergedResults, 200);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    private function generateFakeWinners($numberOfWinners, $totalAmount, $gameName, $sortDate, $banca = null, $categoria = null, $id_banca = null)
    {
        // Chamar a função para gerar os percentuais
        $percentages = $this->generatePercentages($numberOfWinners);

        $fakeWinnersList = [];
        $remainingPercent = 100;

        for ($i = 0; $i < $numberOfWinners; $i++) {
            $fakeWinner = People::inRandomOrder()->first();
            $fakeWinnerFullName = ($fakeWinner->first_name?? '') . ' ' . ($fakeWinner->last_name ?? '');

            // Usar o percentual da lista gerada
            $percentual = $percentages[$i];

            $winnerPrize = round($totalAmount * ($percentual / 100));

            // Verificar se o nome já está presente na mesma modalidade
            $existingNames = array_column($fakeWinnersList, 'name');

            while (in_array($fakeWinnerFullName, $existingNames)) {
                // Escolher um novo vencedor se o nome já estiver presente
                $fakeWinner = People::inRandomOrder()->first();
                $fakeWinnerFullName = ($fakeWinner->first_name ?? '') . ' ' . ($fakeWinner->last_name ?? '');
            }

            // Adicionar o nome à lista de nomes já presentes
            $existingNames[] = $fakeWinnerFullName;

            $winnerStatus = rand(1, 3);
            $winnerId = str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);

            $fakeWinnersList[] = [
                'id' => $winnerId,
                'name' => $fakeWinnerFullName,
                'premio' => $winnerPrize,
                'percentual' => $percentual,
                'status' => $winnerStatus,
                'game_name' => $gameName,
                'percentual' => $percentual,
                'sort_date' => $sortDate,
                'num_tickets' => random_int(1, 4),
                'premio_formatted' => $this->formatMoney($winnerPrize),
                'banca' => $banca,
                'categoria' => $categoria,
                'id_banca' => $id_banca,
            ];
        }

        // Verificar se a soma dos percentuais é realmente 100%
        $sumOfPercentages = array_sum($percentages);
        if ($sumOfPercentages != 100) {
            // Se não for 100%, ajustar o último percentual para compensar
            $fakeWinnersList[count($fakeWinnersList) - 1]['percentual'] += (100 - $sumOfPercentages);
        }

        return $fakeWinnersList;
    }


    public function generatePercentages($numberOfWinners)
    {
        $percentages = [];

        // Distribuir percentuais aleatórios para cada ganhador
        for ($i = 0; $i < $numberOfWinners; $i++) {
            $percentages[] = mt_rand(1, 100);
        }

        // Normalizar os percentuais para garantir que a soma seja igual a 100%
        $totalPercentage = array_sum($percentages);
        $normalizedPercentages = array_map(function ($percentage) use ($totalPercentage) {
            return round(($percentage / $totalPercentage) * 100, 2);
        }, $percentages);

        return $normalizedPercentages;
    }

    public function organizarPorCategoria($resultados)
    {
        // Ordenar os resultados por game_name
        usort($resultados, function ($a, $b) {
            return strcmp($a['game_name'], $b['game_name']);
        });

        return $resultados;
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
                try {


                $data_partner = Partner::findOrFail($partnerId);

                foreach ($data['competitions'] as $competitionId) {
                    try {
                    // Encontrar números na tabela de draws
                    $draw = DB::connection($data_partner->connection)
                        ->table('draws')
                        ->where('competition_id', $competitionId);

                    $games_old = $draw->pluck('games')->toArray();

                    // dd( $games_old);

                    $drawNumbers =  $draw
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
                                        $number = intval($number);

                                        // $query->whereRaw("FIND_IN_SET('$number', games.numbers) > 0");
                                        $query->whereRaw("FIND_IN_SET('$number', REPLACE(games.numbers, ' ', ',')) > 0");
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

                    if( $gameIds ) {
                        foreach ( $gameIds as $g ) {
                            // Se nao tiver na lista de ganhadores nova, será removido o saldo
                            if( !in_array($g, $games_old) ) {
                                $games = DB::connection($data_partner->connection)
                                    ->table('games')
                                    ->whereIn('id', [$g])
                                    ->where(function($query) {
                                        $query->where('status', 2)
                                            ->orWhere('status', 4);
                                    })
                                    ->get();

                                foreach ( $games as $g ) {
                                    $client_id = $g->client_id;
                                    $total_premio = $g->premio;
                                    $id = $g->id;


                                    $client_data = DB::connection($data_partner->connection)->table('clients')->find($client_id);
                                    if($client_data) {
                                        $user_data = DB::connection($data_partner->connection)->table('users')->where('email', $client_data->email)->first();
                                        if($user_data) {
                                            $old_value = floatval($user_data->available_withdraw);  // Recupera o valor antigo do saldo disponível

                                            $new_value = $old_value - $total_premio ;
                                            // $new_value = ($new_value < 0) ? 1 : $new_value;

                                            DB::connection($data_partner->connection)->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                                        } else {
                                            $user_data_default = DB::connection($data_partner->connection)->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                                            if($user_data_default) {
                                                $old_value = floatval($user_data_default->available_withdraw);

                                                $new_value = $old_value - $total_premio ;
                                                // $new_value = ($new_value < 0) ? 1 : $new_value;

                                                DB::connection($data_partner->connection)->table('users')->where('id', $user_data_default->id)->update(['available_withdraw' => $new_value]);
                                            }
                                        }
                                    }

                                    DB::connection($data_partner->connection)
                                        ->table('games')
                                        ->where('id', $id)
                                        ->update(['status' => 1]);
                                }
                            }
                        }
                    } else {

                        $games = DB::connection($data_partner->connection)
                            ->table('games')
                            ->whereIn('competition_id', [$competitionId])
                            ->where(function($query) {
                                $query->where('status', 2)
                                    ->orWhere('status', 4);
                            })
                            ->get();

                        foreach ( $games as $g ) {
                            $client_id = $g->client_id;
                            $total_premio = $g->premio;
                            $id = $g->id;


                            $client_data = DB::connection($data_partner->connection)->table('clients')->find($client_id);
                            if($client_data) {
                                $user_data = DB::connection($data_partner->connection)->table('users')->where('email', $client_data->email)->first();
                                if($user_data) {
                                    $old_value = floatval($user_data->available_withdraw);  // Recupera o valor antigo do saldo disponível

                                    $new_value = $old_value - $total_premio;
                                    // $new_value = ($new_value < 0) ? 0 : $new_value;

                                    DB::connection($data_partner->connection)->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                                } else {
                                    $user_data_default = DB::connection($data_partner->connection)->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                                    if($user_data_default) {
                                        $old_value = floatval($user_data_default->available_withdraw);

                                        $new_value = $old_value - $total_premio;
                                        // $new_value = ($new_value < 0) ? 0 : $new_value;

                                        DB::connection($data_partner->connection)->table('users')->where('id', $user_data_default->id)->update(['available_withdraw' => $new_value]);
                                    }
                                }
                            }

                            DB::connection($data_partner->connection)
                                ->table('games')
                                ->where('id', $id)
                                ->update(['status' => 1]);
                        }
                    }

                    $this->autoAprovePrizeToPartner($partnerId);
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }

            } catch (\Throwable $th) {
                //throw $th;
            }
            }


            return response()->json(['message' => 'Vencedores Atualizados.'], 200);
        } catch (\Throwable $th) {
            // Lida com a exceção aqui
            throw new Exception($th);
        }
    }


    public function getPartnersToPaytmentManagment(Request $request)
    {
        set_time_limit(1200);

        try {
            $data = $request->all();

            if($request->has('ids')) {
                $partnerIds = explode(',', $request->input('ids'));
                $partners = Partner::whereIn('id', $partnerIds)->get();
            } else {
                $partners = Partner::get();
            }

            foreach ( $partners as $i => $v ) {
                $banca = DB::connection($v['connection'])
                    ->table('games')
                    ->leftJoin('competitions', 'games.competition_id', '=', 'competitions.id')
                    ->where('games.status', '4'); // pagamentos automaticos

                if (isset($data['date']) && $data['date']) {
                    $banca = $banca->whereDate('competitions.sort_date', $data['date']);
                }

                // Corrigindo a sintaxe do COUNT e adicionando o SUM
                $banca = $banca->select(DB::raw('SUM(games.premio) as premio, COUNT(*) as total_pagamentos'))->first();

                $banca->premio = $banca->premio ?? 0;
                $banca->total_pagamentos = $banca->total_pagamentos ?? 0;

                if( $banca->premio == 0 && $banca->total_pagamentos == 0 ) {
                    $partners[$i]['media'] = '0,00';
                } else {
                    $partners[$i]['media'] = number_format(($banca->premio / $banca->total_pagamentos), 2, ',', '.') ?? '0,00';
                }

                $partners[$i]['min_value_autoaprovation'] = number_format($v['min_value_autoaprovation'], 2, ',', '.') ?? '0,00';
                $partners[$i]['pagamento_total'] = number_format($banca->premio, 2, ',', '.') ?? '0,00';
            }

            return response()->json($partners);
        } catch (\Throwable $th) {
            throw new Exception($th);
        }

    }

    public function saveValorToAutoAprovation(Request $request, $banca_id)
    {
        try {
            $request->validate([
                'valor' => 'required'
            ]);

            $valorNovo = $request->input('valor');
            $valorNovo = str_replace('.', '', $valorNovo);
            $valorNovo = str_replace(',', '.', $valorNovo);

            Partner::where('id', $banca_id)
                ->update([
                    'min_value_autoaprovation' => $valorNovo
                ]);

            return response()->json(['msg' => 'Valor alterado com sucesso']);
        } catch ( Exception $e ) {
            return response()->json(['msg' => 'Ocorreu um erro ao alterar o valor na banca solicitada', 'details' => $e->getMessage()]);
        }
    }

    public function autoAprove($partner, $game_id) {
        $data_partner = Partner::findOrFail($partner);

        $total_premio = 0;
        $client_id = null;

        $data_game = DB::connection($data_partner['connection'])->table('games')->where('id', $game_id)->first();
        $total_premio += floatval($data_game->premio);

        $client_id = $data_game->client_id; // Assume que todos os jogos são do mesmo cliente

        DB::connection($data_partner['connection'])->table('games')->where('id', $game_id)->update(['status' => 4]);

        if($total_premio > 0 && $client_id) {
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($client_id);
            if($client_data) {
                $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
                if($user_data) {
                    $old_value = floatval($user_data->available_withdraw);  // Recupera o valor antigo do saldo disponível
                    $new_value = $total_premio + $old_value;
                    $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                    DB::connection($data_partner['connection'])->table('transact_balance')->insert([
                            'user_id_sender' => $user_data->id,
                            'user_id' => $user_data->id,
                            'value' => $total_premio,
                            'old_value' => $old_value,
                            'value_a' => $new_value,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'type' => 'Premiacao de jogo loteria id: ' .  $game_id,
                            'wallet' => 'Premiacao'
                            ]);
                } else {
                    // Atualiza o usuário padrão se o usuário específico não for encontrado
                    $user_data_default = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                    if($user_data_default) {
                        $new_value = $total_premio + floatval($user_data_default->available_withdraw);
                        $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data_default->id)->update(['available_withdraw' => $new_value]);
                    }
                }
            }
        }

        return true;
    }

    public function gerarPDFAutoAprovacoes(Request $request)
    {
        $requestData = $request->all();

        $date = Carbon::createFromFormat('Y-m-d', $requestData['date'])->format('d/m/Y');

        $total = 0;
        foreach ( $requestData['partners'] as $a ) {
            $valor = str_replace('.', '', $a['pagamento_total']);
            $valor = str_replace(',', '.', $valor);

            $total += $valor;
        }
        $total = number_format($total, 2, ',', '.');

        $data = [
            'title' => $date, // Título com a data formatada
            'partners' => $requestData['partners'],
            'pagamento' => $total
        ];

        // Carregar a visualização do PDF com os dados
        $pdf = PDF::loadView('pdf.autoAprovacoes', $data);

        // Fazer o download do PDF
        return $pdf->download('relatorio_financeiro.pdf');
    }

    public function corrigirSaldos()
    {
        $partners = Partner::get();

        foreach ( $partners as $i => $v ) {
            $banca = DB::connection($v['connection'])
                ->table('games as g')
                ->select(
                    'g.client_id',
                    DB::raw('SUM(g.premio) as valor_indevido'),
                    'u.available_withdraw as valor_carteira_atual',
                    DB::raw('(u.available_withdraw - SUM(g.premio)) as carteira_novo_valor'),
                    'u.id as id_usuario',
                )
                ->leftJoin('clients as c', 'c.id', '=', 'g.client_id')
                ->leftJoin('users as u', 'u.email', '=', 'c.email')
                ->where('g.status', 4)
                ->groupBy('g.client_id')
                ->get();// pagamentos automaticos


            if( count($banca) > 0 ) {
                foreach ( $banca as $b ) {
                    $novoValor = $b->carteira_novo_valor;
                    $idUsuario = $b->id_usuario;

                    DB::connection($v['connection'])
                        ->table('users')
                        ->where('id', $idUsuario)
                        ->update([
                            'available_withdraw' => $novoValor
                        ]);
                }

                DB::connection($v['connection'])
                    ->table('games')
                    ->where('status', 4)
                    ->update([
                        'status' => 1
                    ]);
            }
        }
    }

    public function autoAprovePrizeToPartner($partner_id, $data = null) {
        date_default_timezone_set('America/Sao_Paulo');
        $data_auto_aprovacao = date('Y-m-d');

        // Busca informações do parceiro
        $data_partner = Partner::findOrFail($partner_id);
        $valorMaximo    = $data_partner['min_value_autoaprovation'];
        $partnerId      = $data_partner['id'];
        if(isset($data)){
            try {
                $winners = $data['winners'] ?? [];
                foreach ($winners as $key => $value) {
                    if($data['winners_users_ids'][$value][1]<= ($valorMaximo ?? 10)){
                        DB::connection($data_partner['connection'])->table('winning_ticket')->insertGetId([
                            'user_id' => $data['winners_users_ids'][$value][0],
                            'game_id' => $value,
                            'draw_id' => $data['winners_users_ids'][$value][2],
                            'drawed_at' => Carbon::now('America/Sao_Paulo'),
                        ]);
                    }

                }
            } catch (\Throwable $th) {
                // throw $th;
            }
        }

        // => Inicio Faz a auto aprovação do prêmio
        $draws = DB::connection($data_partner['connection'])
            ->table('draws')
            ->select(['draws.id', 'draws.games'])
            ->join('competitions', 'competitions.id', '=', 'draws.competition_id')
            ->where('draws.games', '!=', null)
            ->whereDate('competitions.sort_date', '>=', '2024-07-30')
            ->whereDate('competitions.sort_date', '=', $data_auto_aprovacao)
            ->get();

        $gamesIds = [];
        foreach ($draws as $draw) {
            $g_Ids = explode(',', $draw->games);
            if($g_Ids) {
                foreach ( $g_Ids as $g ) {
                    array_push($gamesIds, $g);
                }
            }
        }

        if($gamesIds) {
            $gamesToAutoAprove = DB::connection($data_partner['connection'])
                ->table('games')
                ->select([
                    DB::raw('SUM(games.premio) as total_premio'),
                    'games.id',
                    'games.client_id'
                ])
                ->whereIn('games.id', $gamesIds)
                ->where('games.status', 1)
                ->where('games.random_game', '=', '0')
                ->groupBy('games.id')
                // ->having('total_premio', '<=', $valorMaximo)
                ->get();


            if ($gamesToAutoAprove) {
                $groupedByClientId = [];

                // Organiza os jogos agrupados por client_id
                foreach ($gamesToAutoAprove as $game) {
                    if (!isset($groupedByClientId[$game->client_id])) {
                        $groupedByClientId[$game->client_id] = [
                            'total_premio' => 0,
                            'games' => []
                        ];
                    }

                    $groupedByClientId[$game->client_id]['total_premio'] += $game->total_premio;
                    $groupedByClientId[$game->client_id]['games'][] = $game;
                }

                // Verifica se realmente tem jogos agrupados
                if( count($groupedByClientId)> 0 ) {
                    foreach ( $groupedByClientId as $c ) {
                        $valorPremio = $c['total_premio'];
                        if( $valorPremio <= $valorMaximo ) {
                            $games = $c['games'];
                            if( count($games)> 0 ) {
                                foreach  ( $games  as $g ) {
                                    $this->autoAprove($partnerId, $g->id);
                                }
                            }
                        }
                    }
                }
            }
        }
        // => Fim da auto aprovação do prêmio
    }
}