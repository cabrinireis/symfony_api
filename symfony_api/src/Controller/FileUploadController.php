<?php

namespace App\Controller;

use App\Service\OdsProcessorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileUploadController extends AbstractController
{
    private OdsProcessorService $odsProcessor;
    private ValidatorInterface $validator;

    public function __construct(OdsProcessorService $odsProcessor, ValidatorInterface $validator)
    {
        $this->odsProcessor = $odsProcessor;
        $this->validator = $validator;
    }

    #[Route('/api/upload-ods', name: 'upload_ods', methods: ['POST'])]
    public function uploadOds(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');
            
            if (!$file) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Nenhum arquivo enviado'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validar arquivo antes de processar
            $violations = $this->validator->validate($file, [
                new File([
                    'maxSize' => '20M',
                    'mimeTypes' => [
                        'application/vnd.oasis.opendocument.spreadsheet',
                        'application/vnd.oasis.opendocument.spreadsheet-template'
                    ],
                    'mimeTypesMessage' => 'Por favor, envie um arquivo .ods válido'
                ])
            ]);

            if (count($violations) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $violations[0]->getMessage()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Processar arquivo
            $result = $this->odsProcessor->processOdsFile($file);

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erro ao processar arquivo: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/upload-status', name: 'upload_status', methods: ['GET'])]
    public function uploadStatus(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ready',
            'max_file_size' => '20M',
            'allowed_types' => ['.ods'],
            'target_sheet' => 'XX) Onglet technique - TOTAL'
        ]);
    }

    #[Route('/api/validate-file-info', name: 'validate_file_info', methods: ['POST'])]
    public function validateFileInfo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['filename'])) {
            return new JsonResponse([
                'valid' => false,
                'error' => 'Nome do arquivo não fornecido'
            ]);
        }

        $filename = $data['filename'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $isValid = $extension === 'ods';
        
        return new JsonResponse([
            'valid' => $isValid,
            'extension' => $extension,
            'message' => $isValid ? 'Arquivo válido' : 'Apenas arquivos .ods são permitidos'
        ]);
    }
}
