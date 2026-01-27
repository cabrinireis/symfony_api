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

    public function __construct(OdsReaderService $odsReaderService)
    {
        $this->odsReaderService = $odsReaderService;
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

}
