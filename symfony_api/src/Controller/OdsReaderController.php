<?php

namespace App\Controller;

use App\Service\OdsReaderService;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class OdsReaderController extends AbstractController
{
    private OdsReaderService $odsReaderService;
    private ValidationService $validationService;

    public function __construct(OdsReaderService $odsReaderService, ValidationService $validationService)
    {
        $this->odsReaderService = $odsReaderService;
        $this->validationService = $validationService;
    }

    private function createJsonResponse(array $data): JsonResponse
    {
        // Limita o tamanho dos dados para evitar truncamento (máximo 1000 linhas)
        if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 1000) {
            $data['truncated'] = true;
            $data['originalRowCount'] = count($data['data']);
            $data['data'] = array_slice($data['data'], 0, 5000);
            $data['message'] = 'Dados truncados para exibir as primeiras 1000 linhas. Use a API de paginação para obter todos os dados.';
        }

        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        
        return $response;
    }

    #[Route('/api/ods/upload', name: 'ods_upload', methods: ['POST'])]
    public function uploadAndReadOdsFile(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');

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

            $allData = $sheetData['data'] ?? [];
            $headers = $sheetData['headers'] ?? [];
            $errors = $sheetData['errors'] ?? [];
            $rowCount = count($allData);

            return $this->createJsonResponse([
                'success' => true,
                'data' => $allData,
                'headers' => $headers,
                'errors' => $errors,
                'rowCount' => $rowCount,
                'sheetName' => $sheetName,
                'originalFileName' => $uploadedFile->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar upload: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/validate', name: 'ods_validate', methods: ['POST'])]
    public function validateWithRules(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');
            $validationsJson = $request->request->get('validations');

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

            if (!$validationsJson) {
                return new JsonResponse([
                    'error' => 'Nenhuma validação fornecida'
                ], 400);
            }

            // Validação do tipo de arquivo
            if ($uploadedFile->getMimeType() !== 'application/vnd.oasis.opendocument.spreadsheet') {
                return new JsonResponse([
                    'error' => 'Arquivo inválido. Envie um arquivo ODS válido'
                ], 400);
            }

            // Decodificar validações JSON
            $validations = json_decode($validationsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'error' => 'JSON de validações inválido: ' . json_last_error_msg()
                ], 400);
            }

            // Salva o arquivo temporariamente
            $tempPath = sys_get_temp_dir() . '/' . uniqid('ods_', true) . '.ods';
            $uploadedFile->move(sys_get_temp_dir(), basename($tempPath));

            // Lê os dados
            $sheetData = $this->odsReaderService->readSpecificSheet($tempPath, $sheetName);
            $data = $sheetData['data'] ?? [];
            $headers = $sheetData['headers'] ?? [];

            // Validar dados contra as regras
            $validationResult = $this->validationService->validate($data, $headers, $validations);

            // Remove arquivo temporário
            @unlink($tempPath);

            return $this->createJsonResponse($validationResult);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar validação: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/debug-validation', name: 'ods_debug_validation', methods: ['POST'])]
    public function debugValidation(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');
            $validationsJson = $request->request->get('validations');

            if (!$uploadedFile || !$sheetName || !$validationsJson) {
                return new JsonResponse(['error' => 'Parâmetros faltando'], 400);
            }

            // Decodificar validações JSON
            $validations = json_decode($validationsJson, true);

            // Salvar arquivo temporariamente
            $tempPath = sys_get_temp_dir() . '/' . uniqid('ods_', true) . '.ods';
            $uploadedFile->move(sys_get_temp_dir(), basename($tempPath));

            // Lê os dados
            $sheetData = $this->odsReaderService->readSpecificSheet($tempPath, $sheetName);
            $data = $sheetData['data'] ?? [];
            $headers = $sheetData['headers'] ?? [];

            // Debug: mostra primeiros dados
            $debugInfo = [
                'totalRows' => count($data),
                'headers' => array_map(fn($h) => $h['title'], $headers),
                'firstRow' => $data[0] ?? null,
                'validations' => $validations,
                'validationCount' => count($validations),
            ];

            // Validar dados contra as regras
            $validationResult = $this->validationService->validate($data, $headers, $validations);

            // Remove arquivo temporário
            @unlink($tempPath);

            return $this->createJsonResponse([
                'success' => true,
                'debug' => $debugInfo,
                'validationResult' => $validationResult,
                 'debug' => $validationResult['debug'] ?? [] // Mostra no navegador
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/filter', name: 'ods_filter', methods: ['POST'])]
    public function filterAndReturnData(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');
            $validationsJson = $request->request->get('validations');

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

            if (!$validationsJson) {
                return new JsonResponse([
                    'error' => 'Nenhuma validação fornecida'
                ], 400);
            }

            // Validação do tipo de arquivo
            if ($uploadedFile->getMimeType() !== 'application/vnd.oasis.opendocument.spreadsheet') {
                return new JsonResponse([
                    'error' => 'Arquivo inválido. Envie um arquivo ODS válido'
                ], 400);
            }

            // Decodificar validações JSON
            $validations = json_decode($validationsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'error' => 'JSON de validações inválido: ' . json_last_error_msg()
                ], 400);
            }

            // Salva o arquivo temporariamente
            $tempPath = sys_get_temp_dir() . '/' . uniqid('ods_', true) . '.ods';
            $uploadedFile->move(sys_get_temp_dir(), basename($tempPath));

            // Lê os dados
            $sheetData = $this->odsReaderService->readSpecificSheet($tempPath, $sheetName);
            $allData = $sheetData['data'] ?? [];
            $headers = $sheetData['headers'] ?? [];

            // Filtrar apenas dados válidos
            $filteredData = $this->validationService->filterValidData($allData, $headers, $validations);

            // Remove arquivo temporário
            @unlink($tempPath);

            return $this->createJsonResponse([
                'success' => true,
                'data' => $filteredData,
                'headers' => $headers,
                'originalCount' => count($allData),
                'filteredCount' => count($filteredData),
                'message' => 'Dados filtrados com sucesso. ' . count($allData) - count($filteredData) . ' linhas removidas.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar filtro: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ods/download-filtered', name: 'ods_download_filtered', methods: ['POST'])]
    public function downloadFilteredOds(Request $request): Response
    {
        try {
            $uploadedFile = $request->files->get('odsFile');
            $sheetName = $request->request->get('sheetName');

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
            
            // Gera novo arquivo ODS com apenas dados válidos
            $outputFile = $this->odsReaderService->generateFilteredOds(
                $sheetData['data'],
                $sheetData['headers'],
                $sheetName
            );

            // Remove arquivo temporário
            @unlink($tempPath);

            // Retorna arquivo para download
            $response = new BinaryFileResponse($outputFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'dados_filtrados_' . date('Y-m-d_H-i-s') . '.ods'
            );
            $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.spreadsheet');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
            
            // Deleta arquivo após envio
            $response->deleteFileAfterSend(true);

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erro ao processar download: ' . $e->getMessage()
            ], 500);
        }
    }

}

