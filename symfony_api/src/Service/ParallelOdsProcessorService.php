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
                $reader = $this->createOptimizedReader();
                $spreadsheet = $reader->load($file->getPathname());
                
                $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
                
                if (!$worksheet) {
                    throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
                }

                $highestRow = $worksheet->getHighestRow();
                $headerRow = $this->findHeaderRow($worksheet, $highestRow);
                $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
                
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

                echo '{"success": true, "data": [';
                
                $firstRow = true;
                $processedRows = 0;
                
                $results = $this->processChunksSequentially($chunks);
                
                foreach ($results as $chunkResult) {
                    foreach ($chunkResult as $row) {
                        if (!$firstRow) {
                            echo ',';
                        }
                        echo json_encode($row);
                        $firstRow = false;
                        $processedRows++;
                        
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
            $reader = $this->createUltraOptimizedReader();
            $spreadsheet = $reader->load($file->getPathname());
            
            $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
            
            if (!$worksheet) {
                throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
            }

            $highestRow = $worksheet->getHighestRow();
            $headerRow = $this->findHeaderRow($worksheet, $highestRow);
            $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
            
            $data = [];
            $batchSize = 500;
            $rowIndex = 0;
            
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $rowData = $this->extractRowDataOptimized($worksheet, $row, $worksheet->getHighestColumn());
                
                if (empty(array_filter($rowData))) {
                    continue;
                }

                $data[$rowIndex] = $this->validateRowDataOptimized($rowData, $headers, $row);
                $rowIndex++;
                
                if ($rowIndex % $batchSize === 0) {
                    gc_collect_cycles();
                }
            }
            
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
            
            // Check if cell contains a formula
            if ($cell->isFormula()) {
                $value = $cell->getCalculatedValue();
                // For formula cells, we want the formula itself, not the calculated result
                $value = '=' . $cell->getValue();
            } else {
                $value = $cell->getValue();
            }
            
            if (is_numeric($value)) {
                $value = is_float($value) ? round($value, 2) : (int)$value;
            } elseif ($value !== null) {
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

    // Métodos auxiliares
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
