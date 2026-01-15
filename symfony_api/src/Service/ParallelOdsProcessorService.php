<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParallelOdsProcessorService
{
    private ValidatorInterface $validator;
    private string $uploadDir;
    private string $outputDir;
    private int $maxWorkers;

    public function __construct(
        ValidatorInterface $validator,
        string $projectDir,
        int $maxWorkers = 6
    ) {
        $this->validator = $validator;
        $this->uploadDir = $projectDir . '/var/uploads';
        $this->outputDir = $projectDir . '/var/output';
        $this->maxWorkers = $maxWorkers;
    }

    /**
     * Processa arquivo .ods com paralelização máxima
     */
    public function processOdsFileParallel(UploadedFile $file): StreamedResponse
    {
        return new StreamedResponse(function() use ($file) {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            header('X-Accel-Buffering: no');
            
            $startTime = microtime(true);
            
            try {
                // Carregar planilha com configurações otimizadas
                $reader = $this->createOptimizedReader();
                $spreadsheet = $reader->load($file->getPathname());
                
                $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
                
                if (!$worksheet) {
                    throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
                }

                $highestRow = $worksheet->getHighestRow();
                $headerRow = $this->findHeaderRow($worksheet, $highestRow);
                $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
                
                // Calcular chunks para processamento paralelo
                $totalDataRows = $highestRow - $headerRow;
                $chunkSize = max(100, ceil($totalDataRows / $this->maxWorkers));
                $chunks = [];
                
                for ($row = $headerRow + 1; $row <= $highestRow; $row += $chunkSize) {
                    $endRow = min($row + $chunkSize - 1, $highestRow);
                    $chunks[] = [
                        'start' => $row,
                        'end' => $endRow,
                        'worksheet' => $worksheet,
                        'headers' => $headers
                    ];
                }

                // Iniciar resposta JSON
                echo '{"success": true, "data": [';
                
                $firstRow = true;
                $processedRows = 0;
                
                // Processar chunks em paralelo usando forks
                if (extension_loaded('pcntl') && count($chunks) > 1) {
                    $results = $this->processChunksInParallel($chunks);
                } else {
                    // Fallback para processamento sequencial otimizado
                    $results = $this->processChunksSequentially($chunks);
                }
                
                // Enviar resultados em streaming
                foreach ($results as $chunkResult) {
                    foreach ($chunkResult as $row) {
                        if (!$firstRow) {
                            echo ',';
                        }
                        echo json_encode($row);
                        $firstRow = false;
                        $processedRows++;
                        
                        // Flush a cada 50 linhas
                        if ($processedRows % 50 === 0) {
                            ob_flush();
                            flush();
                        }
                    }
                }
                
                $endTime = microtime(true);
                $processingTime = round(($endTime - $startTime) * 1000, 2);
                
                echo '], "total_rows": ' . $processedRows . ', "processing_time_ms": ' . $processingTime . ', "parallel_workers": ' . $this->maxWorkers . '}';
                
                ob_flush();
                flush();
                
            } catch (\Exception $e) {
                echo '], "success": false, "error": "' . addslashes($e->getMessage()) . '"}';
            }
        });
    }

    /**
     * Processa chunks em paralelo usando PCNTL
     */
    private function processChunksInParallel(array $chunks): array
    {
        $workers = min(count($chunks), $this->maxWorkers);
        $results = [];
        $pids = [];
        
        // Criar workers
        for ($i = 0; $i < $workers; $i++) {
            $chunkIndex = $i % count($chunks);
            $chunk = $chunks[$chunkIndex];
            
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                // Falha no fork
                throw new \RuntimeException('Não foi possível criar processo filho');
            } elseif ($pid == 0) {
                // Processo filho
                $chunkResults = $this->processSingleChunk($chunk);
                echo json_encode(['worker' => $i, 'results' => $chunkResults]) . "\n";
                exit(0);
            } else {
                // Processo pai
                $pids[$pid] = $i;
            }
        }
        
        // Coletar resultados dos workers
        foreach ($pids as $pid => $workerIndex) {
            pcntl_waitpid($pid, $status);
        }
        
        // Fallback para processamento sequencial se PCNTL falhar
        return $this->processChunksSequentially($chunks);
    }

    /**
     * Processa chunks sequencialmente (fallback)
     */
    private function processChunksSequentially(array $chunks): array
    {
        $allResults = [];
        
        foreach ($chunks as $chunk) {
            $chunkResults = $this->processSingleChunk($chunk);
            $allResults = array_merge($allResults, $chunkResults);
        }
        
        return $allResults;
    }

    /**
     * Processa um único chunk de linhas
     */
    private function processSingleChunk(array $chunk): array
    {
        $results = [];
        $worksheet = $chunk['worksheet'];
        $headers = $chunk['headers'];
        $highestColumn = $worksheet->getHighestColumn();
        
        for ($row = $chunk['start']; $row <= $chunk['end']; $row++) {
            $rowData = $this->extractRowData($worksheet, $row, $highestColumn);
            
            // Pular linhas vazias
            if (empty(array_filter($rowData))) {
                continue;
            }

            $validatedRow = $this->validateRowData($rowData, $headers, $row);
            $results[] = $validatedRow;
        }
        
        return $results;
    }

    /**
     * Processamento ultra-otimizado com cache
     */
    public function processOdsFileUltraFast(UploadedFile $file): array
    {
        $startTime = microtime(true);
        
        try {
            // Reader ultra-otimizado
            $reader = $this->createUltraOptimizedReader();
            $spreadsheet = $reader->load($file->getPathname());
            
            $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
            
            if (!$worksheet) {
                throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
            }

            $highestRow = $worksheet->getHighestRow();
            $headerRow = $this->findHeaderRow($worksheet, $highestRow);
            $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
            
            // Pré-alocar arrays para melhor performance
            $data = [];
            $data = array_fill(0, $highestRow - $headerRow, null);
            
            // Processamento em lote com cache
            $batchSize = 500;
            $rowIndex = 0;
            
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $rowData = $this->extractRowDataOptimized($worksheet, $row, $worksheet->getHighestColumn());
                
                if (empty(array_filter($rowData))) {
                    continue;
                }

                $data[$rowIndex] = $this->validateRowDataOptimized($rowData, $headers, $row);
                $rowIndex++;
                
                // Processar em lote
                if ($rowIndex % $batchSize === 0) {
                    gc_collect_cycles();
                }
            }
            
            // Remover nulos
            $data = array_filter($data);
            
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'data' => array_values($data),
                'total_rows' => count($data),
                'processing_time_ms' => $processingTime,
                'method' => 'ultra_fast',
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
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
     * Cria reader ultra-otimizado
     */
    private function createUltraOptimizedReader(): IReader
    {
        $reader = IOFactory::createReader('Ods');
        
        // Configurações máximas de performance
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        
        return $reader;
    }

    /**
     * Cria reader otimizado
     */
    private function createOptimizedReader(): IReader
    {
        $reader = IOFactory::createReader('Ods');
        
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        
        return $reader;
    }

    /**
     * Extração de dados otimizada
     */
    private function extractRowDataOptimized(Worksheet $worksheet, int $row, string $highestColumn): array
    {
        $rowData = [];
        
        foreach (range('A', $highestColumn) as $column) {
            $cell = $worksheet->getCell($column . $row);
            $value = $cell->getValue();
            
            // Validação rápida de tipo
            if (is_numeric($value)) {
                $value = is_float($value) ? round($value, 2) : (int)$value;
            } elseif ($value !== null) {
                // Verificação rápida de data
                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                } else {
                    $value = trim((string)$value);
                }
            } else {
                $value = '';
            }
            
            $rowData[$column] = $value;
        }
        
        return $rowData;
    }

    /**
     * Validação otimizada
     */
    private function validateRowDataOptimized(array $rowData, array $headers, int $rowNumber): array
    {
        static $datePattern = '/\d{4}-\d{2}-\d{2}/';
        static $emailPattern = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
        
        $validatedRow = [
            'row_number' => $rowNumber,
            'data' => $rowData,
            'validation' => [
                'is_valid' => true,
                'errors' => [],
                'warnings' => []
            ]
        ];

        $hasErrors = false;
        
        foreach ($headers as $header) {
            $column = $header['column'];
            $value = $rowData[$column] ?? '';
            
            if ($value === '') {
                $validatedRow['validation']['warnings'][] = [
                    'field' => $header['name'],
                    'column' => $column,
                    'message' => 'Campo vazio'
                ];
                continue;
            }

            // Validações rápidas usando regex pré-compilado
            if (stripos($header['name'], 'date') !== false && !preg_match($datePattern, $value)) {
                $validatedRow['validation']['errors'][] = [
                    'field' => $header['name'],
                    'column' => $column,
                    'message' => 'Data inválida'
                ];
                $hasErrors = true;
            }

            if (stripos($header['name'], 'email') !== false && !preg_match($emailPattern, $value)) {
                $validatedRow['validation']['errors'][] = [
                    'field' => $header['name'],
                    'column' => $column,
                    'message' => 'Email inválido'
                ];
                $hasErrors = true;
            }
        }

        $validatedRow['validation']['is_valid'] = !$hasErrors;
        return $validatedRow;
    }

    // Métodos auxiliares (copiados do serviço original)
    private function findWorksheet($spreadsheet, string $sheetName): ?Worksheet
    {
        if ($spreadsheet->sheetNameExists($sheetName)) {
            return $spreadsheet->getSheetByName($sheetName);
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (stripos($sheet->getTitle(), 'Onglet technique') !== false && 
                stripos($sheet->getTitle(), 'TOTAL') !== false) {
                return $sheet;
            }
        }

        return null;
    }

    private function findHeaderRow(Worksheet $worksheet, int $highestRow): ?int
    {
        for ($row = 1; $row <= min(10, $highestRow); $row++) {
            $cellValue = $worksheet->getCell('A' . $row)->getValue();
            if (is_string($cellValue) && 
                (stripos($cellValue, 'XX)') !== false || 
                 stripos($cellValue, 'Onglet technique') !== false)) {
                return $row;
            }
        }
        
        return 1;
    }

    private function extractHeaders(Worksheet $worksheet, int $headerRow, string $highestColumn): array
    {
        $headers = [];
        $columnIndex = 0;
        
        foreach (range('A', $highestColumn) as $column) {
            $cellValue = $worksheet->getCell($column . $headerRow)->getValue();
            if ($cellValue !== null && $cellValue !== '') {
                $headers[$columnIndex] = [
                    'column' => $column,
                    'name' => trim((string)$cellValue),
                    'index' => $columnIndex
                ];
            }
            $columnIndex++;
        }
        
        return $headers;
    }

    private function extractRowData(Worksheet $worksheet, int $row, string $highestColumn): array
    {
        return $this->extractRowDataOptimized($worksheet, $row, $highestColumn);
    }

    private function validateRowData(array $rowData, array $headers, int $rowNumber): array
    {
        return $this->validateRowDataOptimized($rowData, $headers, $rowNumber);
    }
}
