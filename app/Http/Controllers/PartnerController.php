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



class PartnerController extends Controller
{

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
    
            // Verifica se há itens na lista de ganhadores
            if (isset($requestData['winners2']) && !empty($requestData['winners2'])) {
                // Converte a lista de ganhadores para JSON
                $winnersJson = json_encode($requestData['winners2']);
    
                // Verifica se já existe um registro para essa banca e data de sorteio
                $existingWinnersList = WinnersList::where('banca_id', $requestData['banca_id'])
                    ->where('sort_date', $request['sort_date'])
                    ->first();
    
                if ($existingWinnersList) {
                    // Atualiza o registro existente com os novos dados
                    $existingWinnersList->update([
                        'fake_winners' => $requestData['fakes'] ?? 0,
                        'fake_premio' => $requestData['premio'] ?? 0,
                        'json' => $winnersJson
                    ]);
    
                    // Retorna uma resposta HTTP 200 OK com os dados atualizados
                    return response()->json($existingWinnersList, 200);
                } else {
                    // Cria um novo registro utilizando o model WinnersList
                    $winnersList = WinnersList::create([
                        'banca_id' => $requestData['banca_id'],
                        'fake_winners' => $requestData['fakes'] ?? 0,
                        'fake_premio' => $requestData['premio'] ?? 0,
                        'sort_date' => $request['sort_date'],
                        'json' => $winnersJson
                    ]);
    
                    // Retorna uma resposta HTTP 200 OK com os dados do novo registro
                    return response()->json($winnersList, 200);
                }
            } else {
                // Retorna uma resposta HTTP 400 Bad Request se não houver itens na lista de ganhadores
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
                $jsonData = json_decode($record->json, true);
    
                // Adiciona os dados decodificados ao array formatado
                foreach ($jsonData as $item) {
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
        $sort_date = $request->input('number');
    
        // Consultar o banco de dados para cada parceiro e mesclar os resultados
        $winners = [];
        foreach ($partnerIds as $partner) {
            $partnerWinners = WinnersList::where('banca_id', $partner)
                ->whereDate('sort_date', $sort_date)
                ->get();
    
            // Mesclar os resultados no array principal
            $winners = array_merge($winners, $partnerWinners->toArray());
        }
    
        // Retornar os resultados
        return response()->json($winners);
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
            $totalPix = 0;
            $totalRecargaManual = 0;
            $totalPagPremios = 0;
            $totalPagBonus = 0;
            $totalValorLiquido = 0;

    
            // Itera sobre todas as partners
            foreach ($partners as $partner) {
                // Inicializa o array de saldo agrupado para este parceiro
                $partnerBalances = [
                    'banca' => $partner->name,
                    'dep_pix' => 0,
                    'recarga_manual' => 0,
                    'pag_premios' => 0,
                    'pag_bonus' => 0,
                    'Outros' => 0,
                    'valor_liquido' => 0, 
                    'totalPix' => 0,
                    'totalRecargaManual' => 0,
                    'totalPagPremios' => 0,
                    'totalPagBonus' => 0,
                    'totalValorLiquido' => 0,

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
                    ->select('type', DB::raw('SUM(value) as total_value'))
                    ->whereDate('created_at', '=', $data['number'])
                    ->groupBy('type')
                    ->get();
    
                // Itera sobre os resultados da consulta
                foreach ($transactBalances as $balance) {
                    // Extrai o tipo e o valor total do saldo
                    $type = $balance->type;
                    $total_value = $balance->total_value;
    
                    // Remove os acentos e caracteres especiais do tipo para uniformizar
                    $type = preg_replace('/[^a-zA-Z0-9]/', ' ', $type);
    
                    // Verifica e acumula os valores correspondentes
                    if (strpos($type, 'Recarga efetuada por meio da plataforma') !== false) {
                        $partnerBalances['dep_pix'] += $total_value;
                    } elseif (strpos($type, 'Add por Admin') !== false) {
                        $partnerBalances['recarga_manual'] += $total_value;
                    } elseif (strpos($type, 'Saldo recebido a partir de Saque Disponível.') !== false) {
                        $partnerBalances['pag_premios'] += $total_value;
                    } elseif (strpos($type, 'Saldo recebido a partir de Bônus.') !== false) {
                        $partnerBalances['pag_bonus'] += $total_value;
                    } else {
                        // Se não corresponder a nenhuma categoria específica, adicione o valor total a 'Outros'
                        $partnerBalances['Outros'] += $total_value;
                    }
                }
    
                // Calcula o valor líquido
                $valor_liquido = $partnerBalances['dep_pix'] + $partnerBalances['recarga_manual'] - $partnerBalances['pag_premios'] - $partnerBalances['pag_bonus'];
                
                // Adiciona os totais e formata os demais campos financeiros
                $totalPix = $totalPix + $partnerBalances['dep_pix'];
                $partnerBalances['dep_pix'] = 'R$ ' . number_format($partnerBalances['dep_pix'], 2, ',', '.');
                $partnerBalances['totalPix'] = 'R$ ' . number_format($totalPix, 2, ',', '.');

                $totalRecargaManual = $totalRecargaManual + $partnerBalances['recarga_manual'];
                $partnerBalances['recarga_manual'] = 'R$ ' . number_format($partnerBalances['recarga_manual'], 2, ',', '.');
                $partnerBalances['totalRecargaManual'] = 'R$ ' . number_format($totalRecargaManual, 2, ',', '.');

                $totalPagPremios = $totalPagPremios + $partnerBalances['pag_premios'];
                $partnerBalances['pag_premios'] = 'R$ ' . number_format($partnerBalances['pag_premios'], 2, ',', '.');
                $partnerBalances['totalPagPremios'] = 'R$ ' . number_format($totalPagPremios, 2, ',', '.');

                $totalPagBonus = $totalPagBonus + $partnerBalances['pag_bonus'];
                $partnerBalances['pag_bonus'] = 'R$ ' . number_format($partnerBalances['pag_bonus'], 2, ',', '.');
                $partnerBalances['totalPagBonus'] = 'R$ ' . number_format($totalPagBonus, 2, ',', '.');

                // Formata o valor líquido
                $partnerBalances['valor_liquido'] = 'R$ ' . number_format($valor_liquido, 2, ',', '.');

                // Calcula o valor líquido total
                $totalValorLiquido = $totalPix + $totalRecargaManual - $totalPagPremios - $totalPagBonus;
                $partnerBalances['totalValorLiquido'] = 'R$ ' . number_format($totalValorLiquido, 2, ',', '.');



                // // Formata os demais campos financeiros para duas casas decimais e com a máscara BRL
                // $partnerBalances['dep_pix'] = 'R$ ' . number_format($partnerBalances['dep_pix'], 2, ',', '.');
                // $partnerBalances['recarga_manual'] = 'R$ ' . number_format($partnerBalances['recarga_manual'], 2, ',', '.');
                // $partnerBalances['pag_bonus'] = 'R$ ' . number_format($partnerBalances['pag_bonus'], 2, ',', '.');
                // $partnerBalances['pag_premios'] = 'R$ ' . number_format($partnerBalances['pag_premios'], 2, ',', '.');
                // $partnerBalances['Outros'] = 'R$ ' . number_format($partnerBalances['Outros'], 2, ',', '.');
                // $partnerBalances['valor_liquido'] = 'R$ ' . number_format($valor_liquido, 2, ',', '.');

                

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
                ->whereDate('bichao_games_vencedores.updated_at', '=', $data['date'])
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
                        ->select([
                            'games.id',
                            DB::raw("CONCAT(clients.name, ' ', clients.last_name) as `name`"),
                            'games.premio',
                            'games.status',
                            'type_games.name as game_name'
                        ])
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
    
            // Busca informações do parceiro
            $data_partner = Partner::findOrFail($data['partner']);
    
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
                    DB::raw("CONCAT(clients.name, ' ', clients.last_name) as name"), 
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
                ->where('games.status', 1)
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
                        'id' => $group->pluck('id')->all(), // Todos os IDs como array
                        'name' => $first->name,
                        'premio' => $sumPremio,
                        'status' => $first->status,
                        'random_game' => $first->random_game,
                        'game_name' => $first->game_name,
                        'sort_date' => $first->sort_date,
                        'premio_formatted' => $this->formatMoney($sumPremio) // Formata a soma dos prêmios
                    ];
                })->values()->all();
    
            return $winners;
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
    
    
    private function formatMoney($value)
    {
        return $value >= 1000 ? 'R$ ' . number_format($value, 2, ',', '.') : 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function updateStatusBichao(Request $request) {
        $data = $request->all();
        $data_partner = Partner::findOrFail($data['partner']);
        
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
            
            // Atualiza o status do jogo vencedor
            // $game = DB::connection($data_partner['connection'])->table('bichao_games_vencedores')->where('game_id', $id)->update(['status' => 2]);
        }
        
        if($total_premio > 0 && $client_id) {
            // Busca os detalhes do cliente na tabela 'clients' usando o client_id
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($client_id);
            if($client_data) {
                $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
                if($user_data) {
                    // Atualiza o saldo disponível do usuário somando o total dos prêmios
                    $new_value = $total_premio + floatval($user_data->available_withdraw);
                    $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
                } else {
                    // Atualiza o usuário padrão se o usuário específico não for encontrado
                    $user_data_default = DB::connection($data_partner['connection'])->table('users')->where('email', 'mercadopago@mercadopago.com')->first();
                    if($user_data_default) {
                        // Atualiza o saldo disponível do usuário padrão somando o total dos prêmios
                        $new_value = $total_premio + floatval($user_data_default->available_withdraw);
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
            }
            
            $game = DB::connection($data_partner['connection'])->table('games')->where('id', $id)->update(['status' => $data['status']]);
        }
        
        if($total_premio > 0 && $client_id) {
            $client_data = DB::connection($data_partner['connection'])->table('clients')->find($client_id);
            if($client_data) {
                $user_data = DB::connection($data_partner['connection'])->table('users')->where('email', $client_data->email)->first();
                if($user_data) {
                    $new_value = $total_premio + floatval($user_data->available_withdraw);
                    $user_update = DB::connection($data_partner['connection'])->table('users')->where('id', $user_data->id)->update(['available_withdraw' => $new_value]);
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
        
        return response()->json(["message" => "Alteração finalizada com sucesso!"], 200);
    }
    


    private function getAllAvailableGameNames()
    {
        $gameNames = [''];

        return $gameNames;
    }


    public function distributePrizes(Request $request)
    {
        try {
            $totalAmount = $request->premio;
            $numberOfPeople = $request->ganhadores;
    
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
                $sortDate = Carbon::parse($result['sort_date'] ?? now())->format('d/m/Y');
                $num_tickets = $result['num_tickets'] ?? null;
            } else {
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
            }
    
            // Adicione ganhadores fictícios para cada nome de jogo único
            $uniqueGameNames = array_unique($allGameNames);
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
    
    private function generateFakeWinners($numberOfWinners, $totalAmount, $gameName, $sortDate)
    {
        // Chamar a função para gerar os percentuais
        $percentages = $this->generatePercentages($numberOfWinners);
    
        $fakeWinnersList = [];
        $remainingPercent = 100;
    
        for ($i = 0; $i < $numberOfWinners; $i++) {
            $fakeWinner = People::inRandomOrder()->first();
            $fakeWinnerFullName = $fakeWinner->first_name . ' ' . $fakeWinner->last_name;
    
            // Usar o percentual da lista gerada
            $percentual = $percentages[$i];
    
            $winnerPrize = round($totalAmount * ($percentual / 100));
    
            // Verificar se o nome já está presente na mesma modalidade
            $existingNames = array_column($fakeWinnersList, 'name');
    
            while (in_array($fakeWinnerFullName, $existingNames)) {
                // Escolher um novo vencedor se o nome já estiver presente
                $fakeWinner = People::inRandomOrder()->first();
                $fakeWinnerFullName = $fakeWinner->first_name . ' ' . $fakeWinner->last_name;
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
