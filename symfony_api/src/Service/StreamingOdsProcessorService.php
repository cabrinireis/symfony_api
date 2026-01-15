<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StreamingOdsProcessorService
{
    private ValidatorInterface $validator;
    private string $uploadDir;
    private string $outputDir;

    public function __construct(
        ValidatorInterface $validator,
        string $projectDir
    ) {
        $this->validator = $validator;
        $this->uploadDir = $projectDir . '/var/uploads';
        $this->outputDir = $projectDir . '/var/output';
    }

    /**
     * Processa arquivo .ods com streaming para arquivos grandes
     */
    public function processOdsFileStreaming(UploadedFile $file): StreamedResponse
    {
        return new StreamedResponse(function() use ($file) {
            // Configurar headers para streaming
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            header('X-Accel-Buffering: no'); // Desabilitar buffering do nginx
            
            // Iniciar resposta JSON
            echo '{"success": true, "data": [';
            
            $firstRow = true;
            $processedRows = 0;
            $batchSize = 100; // Processar em lotes
            
            try {
                // Carregar planilha com configurações de memória otimizadas
                $reader = $this->createOptimizedReader();
                $spreadsheet = $reader->load($file->getPathname());
                
                // Encontrar aba específica
                $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
                
                if (!$worksheet) {
                    throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
                }

                $highestRow = $worksheet->getHighestRow();
                $headerRow = $this->findHeaderRow($worksheet, $highestRow);
                $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
                
                // Processar linhas em streaming
                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    $rowData = $this->extractRowData($worksheet, $row, $worksheet->getHighestColumn());
                    
                    // Pular linhas vazias
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    $validatedRow = $this->validateRowData($rowData, $headers, $row);
                    
                    // Enviar linha em formato JSON
                    if (!$firstRow) {
                        echo ',';
                    }
                    echo json_encode($validatedRow);
                    
                    $firstRow = false;
                    $processedRows++;
                    
                    // Flush a cada N linhas para liberar memória
                    if ($processedRows % $batchSize === 0) {
                        ob_flush();
                        flush();
                        
                        // Limpar variáveis para liberar memória
                        unset($rowData, $validatedRow);
                        gc_collect_cycles();
                    }
                }
                
                // Fechar JSON
                echo '], "total_rows": ' . $processedRows . ', "streaming": true}';
                
                // Flush final
                ob_flush();
                flush();
                
            } catch (\Exception $e) {
                // Enviar erro em streaming
                echo '], "success": false, "error": "' . addslashes($e->getMessage()) . '"}';
            }
        });
    }

    /**
     * Cria reader otimizado para streaming
     */
    private function createOptimizedReader(): IReader
    {
        $reader = IOFactory::createReader('Ods');
        
        // Configurações para economizar memória
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true); // Não carregar formatação
        }
        
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            // Carregar apenas a planilha específica se possível
            // $reader->setLoadSheetsOnly(['XX) Onglet technique - TOTAL']);
        }
        
        return $reader;
    }

    /**
     * Processamento em chunks para arquivos muito grandes
     */
    public function processOdsFileInChunks(UploadedFile $file, int $chunkSize = 1000): \Generator
    {
        $reader = $this->createOptimizedReader();
        $spreadsheet = $reader->load($file->getPathname());
        
        $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
        
        if (!$worksheet) {
            throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
        }

        $highestRow = $worksheet->getHighestRow();
        $headerRow = $this->findHeaderRow($worksheet, $highestRow);
        $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
        
        $chunk = [];
        
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $rowData = $this->extractRowData($worksheet, $row, $worksheet->getHighestColumn());
            
            if (empty(array_filter($rowData))) {
                continue;
            }

            $validatedRow = $this->validateRowData($rowData, $headers, $row);
            $chunk[] = $validatedRow;
            
            // Yield chunk quando atingir tamanho máximo
            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
                gc_collect_cycles(); // Forçar garbage collection
            }
        }
        
        // Yield último chunk se não estiver vazio
        if (!empty($chunk)) {
            yield $chunk;
        }
    }

    /**
     * Endpoint SSE (Server-Sent Events) para streaming em tempo real
     */
    public function processOdsFileSSE(UploadedFile $file): StreamedResponse
    {
        return new StreamedResponse(function() use ($file) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            try {
                // Enviar evento de início
                $this->sendSSEEvent('start', ['message' => 'Iniciando processamento...']);
                
                $reader = $this->createOptimizedReader();
                $spreadsheet = $reader->load($file->getPathname());
                
                $this->sendSSEEvent('progress', ['message' => 'Arquivo carregado, processando dados...']);
                
                $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
                
                if (!$worksheet) {
                    throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
                }

                $highestRow = $worksheet->getHighestRow();
                $headerRow = $this->findHeaderRow($worksheet, $highestRow);
                $headers = $this->extractHeaders($worksheet, $headerRow, $worksheet->getHighestColumn());
                
                $processedRows = 0;
                $totalRows = $highestRow - $headerRow;
                
                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    $rowData = $this->extractRowData($worksheet, $row, $worksheet->getHighestColumn());
                    
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    $validatedRow = $this->validateRowData($rowData, $headers, $row);
                    
                    // Enviar linha processada
                    $this->sendSSEEvent('row', [
                        'data' => $validatedRow,
                        'progress' => [
                            'current' => $processedRows,
                            'total' => $totalRows,
                            'percentage' => round(($processedRows / $totalRows) * 100, 2)
                        ]
                    ]);
                    
                    $processedRows++;
                    
                    // Enviar progresso a cada 10 linhas
                    if ($processedRows % 10 === 0) {
                        $this->sendSSEEvent('progress', [
                            'message' => "Processadas {$processedRows} de {$totalRows} linhas...",
                            'current' => $processedRows,
                            'total' => $totalRows,
                            'percentage' => round(($processedRows / $totalRows) * 100, 2)
                        ]);
                    }
                }
                
                // Enviar evento de conclusão
                $this->sendSSEEvent('complete', [
                    'message' => 'Processamento concluído!',
                    'total_rows' => $processedRows
                ]);
                
            } catch (\Exception $e) {
                $this->sendSSEEvent('error', ['message' => $e->getMessage()]);
            }
        });
    }

    private function sendSSEEvent(string $type, array $data): void
    {
        echo "event: {$type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
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
        $rowData = [];
        
        foreach (range('A', $highestColumn) as $column) {
            $cell = $worksheet->getCell($column . $row);
            $value = $cell->getValue();
            
            if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } elseif (is_numeric($value)) {
                $value = is_float($value) ? round($value, 2) : (int)$value;
            } else {
                $value = trim((string)$value);
            }
            
            $rowData[$column] = $value;
        }
        
        return $rowData;
    }

    private function validateRowData(array $rowData, array $headers, int $rowNumber): array
    {
        $validatedRow = [
            'row_number' => $rowNumber,
            'data' => $rowData,
            'validation' => [
                'is_valid' => true,
                'errors' => [],
                'warnings' => []
            ]
        ];

        foreach ($headers as $header) {
            $column = $header['column'];
            $value = $rowData[$column] ?? null;
            
            if ($value === null || $value === '') {
                $validatedRow['validation']['warnings'][] = [
                    'field' => $header['name'],
                    'column' => $column,
                    'message' => 'Campo vazio'
                ];
                continue;
            }

            if (stripos($header['name'], 'date') !== false) {
                if (!$this->isValidDate($value)) {
                    $validatedRow['validation']['errors'][] = [
                        'field' => $header['name'],
                        'column' => $column,
                        'message' => 'Data inválida'
                    ];
                    $validatedRow['validation']['is_valid'] = false;
                }
            }

            if (stripos($header['name'], 'email') !== false) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validatedRow['validation']['errors'][] = [
                        'field' => $header['name'],
                        'column' => $column,
                        'message' => 'Email inválido'
                    ];
                    $validatedRow['validation']['is_valid'] = false;
                }
            }
        }

        return $validatedRow;
    }

    private function isValidDate($value): bool
    {
        if (is_string($value)) {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
        }
        return false;
    }
}
