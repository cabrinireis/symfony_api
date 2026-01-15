<?php

namespace App\Controller;

use App\Service\StreamingOdsProcessorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class StreamingFileUploadController extends AbstractController
{
    private StreamingOdsProcessorService $streamingProcessor;

    public function __construct(StreamingOdsProcessorService $streamingProcessor)
    {
        $this->streamingProcessor = $streamingProcessor;
    }

    /**
     * Upload com streaming JSON progressivo
     */
    #[Route('/api/upload-ods-streaming', name: 'upload_ods_streaming', methods: ['POST'])]
    public function uploadOdsStreaming(Request $request): StreamedResponse
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new StreamedResponse(function() {
                header('Content-Type: application/json');
                echo '{"success": false, "error": "Nenhum arquivo enviado"}';
            });
        }

        // Validar arquivo
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'ods') {
            return new StreamedResponse(function() {
                header('Content-Type: application/json');
                echo '{"success": false, "error": "Apenas arquivos .ods são permitidos"}';
            });
        }

        return $this->streamingProcessor->processOdsFileStreaming($file);
    }

    /**
     * Upload com Server-Sent Events (SSE) para atualizações em tempo real
     */
    #[Route('/api/upload-ods-sse', name: 'upload_ods_sse', methods: ['POST'])]
    public function uploadOdsSSE(Request $request): StreamedResponse
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new StreamedResponse(function() {
                header('Content-Type: text/event-stream');
                echo "event: error\n";
                echo "data: " . json_encode(['message' => 'Nenhum arquivo enviado']) . "\n\n";
                flush();
            });
        }

        return $this->streamingProcessor->processOdsFileSSE($file);
    }

    /**
     * Upload processado em chunks (para arquivos muito grandes)
     */
    #[Route('/api/upload-ods-chunks', name: 'upload_ods_chunks', methods: ['POST'])]
    public function uploadOdsChunks(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Nenhum arquivo enviado'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $allData = [];
            $chunkCount = 0;
            
            // Processar arquivo em chunks
            foreach ($this->streamingProcessor->processOdsFileInChunks($file, 1000) as $chunk) {
                $allData = array_merge($allData, $chunk);
                $chunkCount++;
                
                // Opcional: enviar progresso para algum sistema de logging
                error_log("Processed chunk {$chunkCount} with " . count($chunk) . " rows");
            }

            return new JsonResponse([
                'success' => true,
                'data' => $allData,
                'total_rows' => count($allData),
                'chunks_processed' => $chunkCount,
                'processing_method' => 'chunks'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Endpoint para verificar status do streaming
     */
    #[Route('/api/streaming-status', name: 'streaming_status', methods: ['GET'])]
    public function streamingStatus(): JsonResponse
    {
        return new JsonResponse([
            'streaming_enabled' => true,
            'max_file_size' => '50M', // Aumentado para streaming
            'chunk_size' => 1000,
            'methods' => [
                'streaming_json' => '/api/upload-ods-streaming',
                'sse' => '/api/upload-ods-sse',
                'chunks' => '/api/upload-ods-chunks'
            ],
            'recommended_for_large_files' => 'Use SSE ou chunks para arquivos > 10MB'
        ]);
    }
}
