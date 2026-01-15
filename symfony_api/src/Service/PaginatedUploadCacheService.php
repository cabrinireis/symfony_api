<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PaginatedUploadCacheService
{
    private FilesystemAdapter $cache;
    private ParallelOdsProcessorService $processor;
    private int $pageSize;

    public function __construct(ParallelOdsProcessorService $processor, int $pageSize = 100)
    {
        $this->cache = new FilesystemAdapter('upload_cache', 3600, $_ENV['APP_VAR_DIR'] ?? '/var/cache');
        $this->processor = $processor;
        $this->pageSize = $pageSize;
    }

    /**
     * Processa arquivo e armazena em cache para paginação
     */
    public function processAndCache(UploadedFile $file): array
    {
        $sessionId = uniqid('upload_', true);
        $startTime = microtime(true);

        try {
            // Processar arquivo completo uma vez
            $result = $this->processor->processOdsFileUltraFast($file);
            
            if (!$result['success']) {
                return $result;
            }

            // Armazenar dados completos em cache
            $cacheData = [
                'session_id' => $sessionId,
                'total_rows' => $result['total_rows'],
                'total_pages' => ceil($result['total_rows'] / $this->pageSize),
                'page_size' => $this->pageSize,
                'data' => $result['data'],
                'headers' => $this->extractHeaders($result['data']),
                'created_at' => time(),
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'extension' => $file->getClientOriginalExtension()
                ]
            ];

            $this->cache->store($sessionId, $cacheData);

            // Retornar primeira página imediatamente
            $firstPage = $this->getPage($sessionId, 1);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'session_id' => $sessionId,
                'data' => $firstPage['data'],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => $cacheData['total_pages'],
                    'total_rows' => $cacheData['total_rows'],
                    'page_size' => $this->pageSize,
                    'has_next' => $cacheData['total_pages'] > 1,
                    'has_prev' => false
                ],
                'headers' => $cacheData['headers'],
                'processing_time_ms' => $processingTime,
                'cache_expires_at' => date('Y-m-d H:i:s', $cacheData['created_at'] + 3600)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * Obtém página específica do cache
     */
    public function getPage(string $sessionId, int $page): array
    {
        $cachedData = $this->cache->get($sessionId, function () {
            return null;
        });

        if (!$cachedData) {
            return [
                'success' => false,
                'error' => 'Sessão não encontrada ou expirada'
            ];
        }

        $totalPages = $cachedData['total_pages'];
        
        if ($page < 1 || $page > $totalPages) {
            return [
                'success' => false,
                'error' => "Página {$page} inválida. Total de páginas: {$totalPages}"
            ];
        }

        $offset = ($page - 1) * $this->pageSize;
        $pageData = array_slice($cachedData['data'], $offset, $this->pageSize);

        return [
            'success' => true,
            'session_id' => $sessionId,
            'data' => $pageData,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_rows' => $cachedData['total_rows'],
                'page_size' => $this->pageSize,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'headers' => $cachedData['headers'],
            'cache_info' => [
                'created_at' => $cachedData['created_at'],
                'expires_at' => $cachedData['created_at'] + 3600
            ]
        ];
    }

    /**
     * Limpa cache da sessão
     */
    public function clearSession(string $sessionId): bool
    {
        return $this->cache->delete($sessionId);
    }

    /**
     * Obtém informações da sessão sem dados
     */
    public function getSessionInfo(string $sessionId): array
    {
        $cachedData = $this->cache->get($sessionId, function () {
            return null;
        });

        if (!$cachedData) {
            return [
                'success' => false,
                'error' => 'Sessão não encontrada ou expirada'
            ];
        }

        return [
            'success' => true,
            'session_id' => $sessionId,
            'file_info' => $cachedData['file_info'],
            'pagination' => [
                'total_pages' => $cachedData['total_pages'],
                'total_rows' => $cachedData['total_rows'],
                'page_size' => $this->pageSize
            ],
            'cache_info' => [
                'created_at' => $cachedData['created_at'],
                'expires_at' => $cachedData['created_at'] + 3600,
                'remaining_seconds' => max(0, $cachedData['created_at'] + 3600 - time())
            ]
        ];
    }

    /**
     * Lista todas as sessões ativas
     */
    public function listActiveSessions(): array
    {
        // Implementação básica - poderia ser melhorada com Redis
        return [
            'success' => true,
            'message' => 'Use Redis para listagem completa de sessões',
            'cache_type' => 'Filesystem'
        ];
    }

    /**
     * Extrai cabeçalhos dos dados processados
     */
    private function extractHeaders(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $firstRow = $data[0];
        $headers = [];

        if (isset($firstRow['data'])) {
            // Dados estruturados com validação
            foreach ($firstRow['data'] as $column => $value) {
                $headers[] = [
                    'column' => $column,
                    'name' => $column,
                    'type' => $this->detectDataType($value)
                ];
            }
        }

        return $headers;
    }

    /**
     * Detecta tipo de dado para melhor exibição
     */
    private function detectDataType($value): string
    {
        if (is_numeric($value)) {
            return is_float($value + 0) ? 'decimal' : 'integer';
        }
        
        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return 'date';
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }
            return 'text';
        }
        
        return 'mixed';
    }
}