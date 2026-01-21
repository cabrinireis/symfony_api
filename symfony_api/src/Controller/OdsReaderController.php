<?php

namespace App\Controller;

use App\Service\OdsReaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OdsReaderController extends AbstractController
{
    private OdsReaderService $odsReaderService;

    private function createJsonResponse(array $data): JsonResponse
    {
        // Limita o tamanho dos dados para evitar truncamento (máximo 1000 linhas)
        if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 1000) {
            $data['truncated'] = true;
            $data['originalRowCount'] = count($data['data']);
            $data['data'] = array_slice($data['data'], 0, 1000);
            $data['message'] = 'Dados truncados para exibir as primeiras 1000 linhas. Use a API de paginação para obter todos os dados.';
        }

        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        
        return $response;
    }

    public function __construct(OdsReaderService $odsReaderService)
    {
        $this->odsReaderService = $odsReaderService;
    }

    #[Route('/api/ods/read', name: 'ods_read', methods: ['POST'])]
    public function readOdsFile(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['filePath']) || !isset($data['sheetName'])) {
                return new JsonResponse([
                    'error' => 'Parâmetros obrigatórios: filePath e sheetName'
                ], 400);
            }

            $filePath = $data['filePath'];
            $sheetName = $data['sheetName'];

            // Verifica se o arquivo existe
            if (!file_exists($filePath)) {
                return new JsonResponse([
                    'error' => 'Arquivo não encontrado: ' . $filePath
                ], 404);
            }

            // Lê os dados da aba específica
            $sheetData = $this->odsReaderService->readSpecificSheet($filePath, $sheetName);

            return $this->createJsonResponse([
                'success' => true,
                'data' => $sheetData,
                'rowCount' => count($sheetData),
                'sheetName' => $sheetName
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar arquivo ODS: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/upload', name: 'ods_upload', methods: ['POST'])]
    public function uploadAndReadOdsFile(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');
            
            // Parâmetros de paginação
            $page = (int) $request->request->get('page', 1);
            $limit = (int) $request->request->get('limit', 30);
            
            // Validação dos parâmetros de paginação
            $page = max(1, $page);
            $limit = max(1, min(100, $limit)); // Limita entre 1 e 100

            if (!$uploadedFile) {
                return new JsonResponse([
                    'error' => 'Nenhum arquivo ODS enviado'
                ], 400);
            }

            if (!$sheetName) {
                return new JsonResponse([
                    'error' => 'Nome da aba é obrigatório'
                ], 400);
            }

            // Validação do tipo de arquivo
            if ($uploadedFile->getMimeType() !== 'application/vnd.oasis.opendocument.spreadsheet') {
                return new JsonResponse([
                    'error' => 'Arquivo inválido. Envie um arquivo ODS válido'
                ], 400);
            }

            // Salva o arquivo temporariamente
            $tempPath = sys_get_temp_dir() . '/' . uniqid('ods_', true) . '.ods';
            $uploadedFile->move(sys_get_temp_dir(), basename($tempPath));

            // Lê os dados
            $sheetData = $this->odsReaderService->readSpecificSheet($tempPath, $sheetName);

            // Remove o arquivo temporário
            unlink($tempPath);

            // Aplica paginação nos dados
            $allData = $sheetData['data'] ?? [];
            $headers = $sheetData['headers'] ?? [];
            $totalRows = count($allData);
            $totalPages = (int) ceil($totalRows / $limit);
            $offset = ($page - 1) * $limit;
            
            // Extrai a página solicitada
            $paginatedData = array_slice($allData, $offset, $limit);

            return $this->createJsonResponse([
                'success' => true,
                'headers' => $headers,
                'data' => $paginatedData,
                'pagination' => [
                    'currentPage' => $page,
                    'perPage' => $limit,
                    'totalRows' => $totalRows,
                    'totalPages' => $totalPages,
                    'hasNextPage' => $page < $totalPages,
                    'hasPreviousPage' => $page > 1,
                ],
                'sheetName' => $sheetName,
                'originalFileName' => $uploadedFile->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar upload: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/sheets', name: 'ods_sheets', methods: ['POST'])]
    public function getAvailableSheets(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['filePath'])) {
                return new JsonResponse([
                    'error' => 'Parâmetro obrigatório: filePath'
                ], 400);
            }

            $filePath = $data['filePath'];

            if (!file_exists($filePath)) {
                return new JsonResponse([
                    'error' => 'Arquivo não encontrado: ' . $filePath
                ], 404);
            }

            // Usa o OpenSpout diretamente para obter as abas
            $reader = new \OpenSpout\Reader\ODS\Reader();
            $reader->open($filePath);

            $sheets = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[] = [
                    'name' => $sheet->getName(),
                    'index' => $sheet->getIndex()
                ];
            }

            $reader->close();

            return new JsonResponse([
                'success' => true,
                'sheets' => $sheets
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao ler abas do arquivo: ' . $e->getMessage()
            ], 500);
        }
    }
}