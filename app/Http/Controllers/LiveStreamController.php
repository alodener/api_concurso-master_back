<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class LiveStreamController extends Controller
{
    /**
     * Obtém informações sobre a transmissão ao vivo de um canal do YouTube.
     * Usa webscraping e, se falhar após 4 tentativas, recorre à API oficial do YouTube.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function channelIdLive(Request $request)
    {
        try {
            $request->validate([
                'channelId' => 'required|string',
                'typeGameId' => 'required'
            ]);
            
            $channelId = $request->input('channelId');
            $typeGameId = $request->input('typeGameId');
            
            Log::info("Verificando live para o canal {$channelId} sem API do YouTube");
            
            // Tenta o webscraping até 4 vezes antes de recorrer à API oficial
            $maxAttempts = 4;
            $attemptCount = 0;
            $lastException = null;
            
            while ($attemptCount < $maxAttempts) {
                try {
                    $attemptCount++;
                    Log::info("Tentativa {$attemptCount} de webscraping para o canal {$channelId}");
                    
                    // URL do canal
                    $channelUrl = "https://www.youtube.com/channel/{$channelId}";
                    
                    // Faz a requisição HTTP
                    $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                        'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    ])->get($channelUrl);
                    
                    if (!$response->successful()) {
                        throw new \Exception('Erro ao acessar o canal do YouTube');
                    }
                    
                    $html = $response->body();
                    
                    // Verificar se há transmissão ao vivo
                    $isLive = false;
                    $videoId = null;
                    
                    if (strpos($html, '"isLive":true') !== false) {
                        $isLive = true;
                        
                        // Extrai o ID do vídeo e limpa caracteres extras
                        preg_match('/"videoId":"([^"]+)"/', $html, $matches);
                        if (isset($matches[1])) {
                            // Limpa o ID do vídeo para obter apenas o ID base
                            $videoId = preg_replace('/[\\\\&=?%.]+.*$/', '', $matches[1]);
                        }
                    }
                    
                    // Verifica também na página de vídeos ao vivo do canal
                    if (!$isLive) {
                        $liveUrl = "https://www.youtube.com/channel/{$channelId}/live";
                        $liveResponse = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                        ])->get($liveUrl);
                        
                        if ($liveResponse->successful()) {
                            $liveHtml = $liveResponse->body();
                            
                            if (strpos($liveHtml, 'watching now') !== false || strpos($liveHtml, 'assistindo agora') !== false) {
                                $isLive = true;
                                
                                // Extrai o ID do vídeo da URL e limpa caracteres extras
                                if (preg_match('/watch\?v=([^&"\\\\\s]+)/', $liveHtml, $urlMatches)) {
                                    $videoId = $urlMatches[1];
                                }
                            }
                        }
                    }
                    
                    if ($isLive && $videoId) {
                        // Garante que o ID do vídeo esteja limpo
                        $videoId = preg_replace('/[\\\\&=?%.]+.*$/', '', $videoId);
                        $embed = "https://www.youtube.com/embed/{$videoId}?autoplay=1";
                        
                        Log::info("Canal {$channelId} está com transmissão ao vivo. ID do vídeo: {$videoId}");
                        
                        // Atualizar o URL em todos os parceiros
                        $this->updateVideoUrlInAllPartners($channelId, $embed);
                        
                        return response()->json([
                            'success' => true,
                            'embed' => $embed,
                            'isOnline' => true,
                            'message' => 'Live Online',
                            'videoId' => $videoId,
                            'typeGameId' => $typeGameId
                        ]);
                    }
                    
                    Log::info("Canal {$channelId} não está com transmissão ao vivo via webscraping.");
                    
                    // Se o webscraping não encontrou transmissão, retorna resposta negativa
                    return response()->json([
                        'success' => false,
                        'embed' => null,
                        'isOnline' => false,
                        'message' => 'Nenhuma transmissão ao vivo encontrada',
                        'typeGameId' => $typeGameId
                    ]);
                    
                } catch (\Exception $e) {
                    $lastException = $e;
                    Log::warning("Tentativa {$attemptCount} de webscraping falhou: " . $e->getMessage());
                    
                    // Aguarda 1 segundo antes de tentar novamente
                    sleep(1);
                }
            }
            
            // Após 4 tentativas falhas, recorre à API oficial
            Log::info("Verificando se a live do canal {$channelId} está ativa usando API oficial do YouTube após {$maxAttempts} tentativas falhas de webscraping.");
            
            $apiKey = config('services.youtube.api_key');
            $client = new Client();
            $response = $client->get('https://www.googleapis.com/youtube/v3/search', [
                'query' => [
                    'part' => 'snippet',
                    'channelId' => $channelId,
                    'eventType' => 'live',
                    'type' => 'video',
                    'key' => $apiKey,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            $items = $data['items'] ?? [];
            
            if (empty($items)) {
                Log::info("Nenhuma live encontrada via API oficial para o canal: {$channelId}");
                
                return response()->json([
                    'success' => false,
                    'embed' => null,
                    'isOnline' => false,
                    'message' => 'Nenhuma transmissão ao vivo encontrada',
                    'typeGameId' => $typeGameId
                ]);
            } else {
                $videoId = $items[0]['id']['videoId'];
                $embed = "https://www.youtube.com/embed/{$videoId}?autoplay=1";
                
                Log::info("Canal {$channelId} está com transmissão ao vivo via API oficial. ID do vídeo: {$videoId}");
                
                // Atualizar o URL em todos os parceiros
                $this->updateVideoUrlInAllPartners($channelId, $embed);
                
                return response()->json([
                    'success' => true,
                    'embed' => $embed,
                    'isOnline' => true,
                    'message' => 'Live Online (via API)',
                    'videoId' => $videoId,
                    'typeGameId' => $typeGameId
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Erro ao verificar livestream: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar transmissão: ' . $e->getMessage(),
                'isOnline' => false,
                'typeGameId' => $request->input('typeGameId', null)
            ], 500);
        }
    }
    
    /**
     * Atualiza o URL do vídeo em todos os parceiros na tabela type_games
     * 
     * @param string $channelId
     * @param string $embedUrl
     * @return void
     */
    private function updateVideoUrlInAllPartners($channelId, $embedUrl)
    {
        try {
            // Obtém todos os parceiros
            $partners = DB::table('partners')->get();
            $updatedPartners = [];
            
            foreach ($partners as $partner) {
                $partnerId = $partner->id;
                $partnerName = $partner->name;
                $connection = $partner->connection;
                
                try {
                    // Verifica se a conexão existe
                    if (DB::connection($connection)->getDatabaseName()) {
                        // Atualiza todos os jogos que tenham o mesmo channelID
                        $affected = DB::connection($connection)
                            ->table('type_games')
                            ->where('channelID', $channelId)
                            ->update(['Url' => $embedUrl]);
                            
                        if ($affected > 0) {
                            $updatedPartners[] = $partnerName;
                            Log::info("Atualizado URL da live para {$affected} jogos no parceiro {$partnerName}");
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Erro ao atualizar URL no parceiro {$partnerName}: " . $e->getMessage());
                }
            }
            
            if (count($updatedPartners) > 0) {
                Log::info("URL da live atualizado para os parceiros: " . implode(', ', $updatedPartners));
            } else {
                Log::warning("Nenhum parceiro atualizado para o channelID: {$channelId}");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao atualizar URL em todos os parceiros: " . $e->getMessage());
        }
    }
    
    /**
     * Função auxiliar para resetar o status de uma transmissão.
     * 
     * @param string $channelId
     * @return void
     */
    private function resetStatus($channelId)
    {
        try {
            // Obtém todos os parceiros
            $partners = DB::table('partners')->get();
            $updatedPartners = [];
            
            foreach ($partners as $partner) {
                $partnerId = $partner->id;
                $partnerName = $partner->name;
                $connection = $partner->connection;
                
                try {
                    // Verifica se a conexão existe
                    if (DB::connection($connection)->getDatabaseName()) {
                        // Limpa o URL para todos os jogos com o mesmo channelID
                        $affected = DB::connection($connection)
                            ->table('type_games')
                            ->where('channelID', $channelId)
                            ->update(['Url' => null]);
                            
                        if ($affected > 0) {
                            $updatedPartners[] = $partnerName;
                            Log::info("Reset do URL da live para {$affected} jogos no parceiro {$partnerName}");
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Erro ao resetar URL no parceiro {$partnerName}: " . $e->getMessage());
                }
            }
            
            if (count($updatedPartners) > 0) {
                Log::info("URL da live resetado para os parceiros: " . implode(', ', $updatedPartners));
            } else {
                Log::warning("Nenhum parceiro resetado para o channelID: {$channelId}");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao resetar URL em todos os parceiros: " . $e->getMessage());
        }
    }
}