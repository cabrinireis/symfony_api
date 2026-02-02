<?php

namespace App\Service;

use OpenSpout\Reader\ODS\Reader;
use OpenSpout\Writer\ODS\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class OdsReaderService
{
    /**
     * Detecta o tipo real do valor no ODS
     */
    private function detectCellType($cellValue): string
    {
        // Se for null/vazio
        if ($cellValue === null || $cellValue === '') {
            return 'null';
        }

        // Se for DateTime (LibreOffice armazena assim)
        if ($cellValue instanceof \DateTimeInterface) {
            return 'datetime';
        }

        // Se for booleano
        if (is_bool($cellValue)) {
            return 'boolean';
        }

        // Se for número
        if (is_int($cellValue) || is_float($cellValue)) {
            // Verifica se pode ser data serial do Excel/ODS
            // Datas válidas ficam entre ~0 e ~100000
            if (is_float($cellValue) && $cellValue > 0 && $cellValue < 100000) {
                // Pode ser data serial
                return 'number_or_date_serial';
            }
            return 'number';
        }

        // Se for string que parece data
        if (is_string($cellValue)) {
            if ($this->looksLikeDate($cellValue)) {
                return 'date_string';
            }
            return 'string';
        }

        return 'unknown';
    }

    /**
     * Verifica se uma string parece ser uma data
     */
    private function looksLikeDate(string $value): bool
    {
        $patterns = [
            '/^\d{1,2}\/\d{1,2}\/\d{4}/',           // DD/MM/YYYY
            '/^\d{4}-\d{1,2}-\d{1,2}/',             // YYYY-MM-DD
            '/^\d{1,2}-\d{1,2}-\d{4}/',             // DD-MM-YYYY
            '/^\d{1,2}\/\d{1,2}\/\d{2}/',           // DD/MM/YY
            '/^\d{4}\/\d{1,2}\/\d{1,2}/',           // YYYY/MM/DD
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formata o valor preservando o tipo original
     */
    private function formatCellValue($cellValue, string $detectedType): string
    {
        // ✅ DateTime nativo
        if ($detectedType === 'datetime' && $cellValue instanceof \DateTimeInterface) {
            return $cellValue->format('Y-m-d H:i:s');
        }

        // ✅ Data serial do Excel/ODS (número)
        if ($detectedType === 'number_or_date_serial' && is_float($cellValue)) {
            try {
                // ODS base: 1899-12-30 (dia 0)
                $baseDate = new \DateTime('1899-12-30');
                $days = (int)$cellValue;
                $baseDate->modify("+{$days} days");

                // Se tem parte decimal, são horas/minutos/segundos
                $fraction = $cellValue - $days;
                if ($fraction > 0) {
                    $seconds = round($fraction * 86400); // 24 * 60 * 60
                    $baseDate->modify("+{$seconds} seconds");
                }

                return $baseDate->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return (string)$cellValue;
            }
        }

        // ✅ Data em string
        if ($detectedType === 'date_string') {
            return (string)$cellValue;
        }

        // ✅ Número
        if ($detectedType === 'number') {
            return (string)$cellValue;
        }

        // ✅ Booleano
        if ($detectedType === 'boolean') {
            return $cellValue ? 'true' : 'false';
        }

        // ✅ Null/vazio
        if ($detectedType === 'null') {
            return '';
        }

        // Default
        return (string)$cellValue;
    }

    public function readSpecificSheet(string $filePath, string $targetSheetName): array
    {
        $reader = new Reader();
        $reader->open($filePath);

        $data = [];
        $headers = [];
        $headerKeys = [];
        $numPifColIndex = null;
        $errors = [];
        $rowNumber = 1; // Contador de linhas para identificar erros

        // Iterar sobre as abas do arquivo
        foreach ($reader->getSheetIterator() as $sheet) {
            // Verifica se é a aba que você procura
            if ($sheet->getName() === $targetSheetName) {
                $isFirstRow = true;
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $formattedCells = [];
                    
                    // Processa cada célula detectando tipo e formatando
                    foreach ($cells as $cell) {
                        $cellValue = $cell->getValue();
                        $detectedType = $this->detectCellType($cellValue);
                        $formattedValue = $this->formatCellValue($cellValue, $detectedType);
                        $formattedCells[] = $formattedValue;
                    }
                    
                    // Primeira linha = cabeçalhos
                    if ($isFirstRow) {
                        foreach ($formattedCells as $index => $cellValue) {
                            if ($cellValue) {
                                $key = $this->generateKey($cellValue, $index);
                                $headerKeys[] = $key;
                                $headers[] = [
                                    'title' => $cellValue,
                                    'align' => 'start',
                                    'sortable' => true,
                                    'key' => $key,
                                ];
                            }
                            if (strtolower(trim($cellValue)) === 'numéro pif') {
                                $numPifColIndex = $index;
                            }
                        }
                        $isFirstRow = false;
                        continue;
                    }
                    
                    // Case 1: Se 'Numéro PIF' == 0, ignora linha
                    if ($numPifColIndex !== null && isset($formattedCells[$numPifColIndex]) && $formattedCells[$numPifColIndex] === '0') {
                        continue;
                    }
                    
                    // Case 2: Se algum valor == '#N/D', adiciona erro e ignora linha
                    $hasError = false;
                    $errorColumns = [];
                    foreach ($formattedCells as $colIndex => $value) {
                        if (str_contains((string)$value, '#N/')) {
                            $hasError = true;
                            $errorColumns[] = [
                                'column' => $headers[$colIndex]['title'] ?? 'Column ' . ($colIndex + 1),
                                'columnIndex' => $colIndex,
                                'rowNumber' => $rowNumber,
                                'value' => $value
                            ];
                        }
                    }
                    
                    if ($hasError) {
                        $errors[] = [
                            'rowNumber' => $rowNumber,
                            'columns' => $errorColumns
                        ];
                        $rowNumber++;
                        continue;
                    }
                    
                    // Linhas de dados
                    $rowData = [];
                    foreach ($headerKeys as $index => $key) {
                        $rowData[$key] = $formattedCells[$index] ?? null;
                    }
                    $data[] = $rowData;
                }
                break;
            }
        }

        $reader->close();

        return [
            'headers' => $headers,
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Gera uma key válida a partir do título da coluna
     */
    private function generateKey(?string $title, int $index): string
    {
        if ($title === null || trim($title) === '') {
            return 'column_' . $index;
        }

        // Remove acentos, converte para minúsculas e substitui espaços por underscore
        $key = strtolower(trim($title));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');

        return $key ?: 'column_' . $index;
    }

    /**
     * Gera um novo arquivo ODS com dados filtrados
     */
    public function generateFilteredOds(array $data, array $headers, string $sheetName): string
    {
        $tempFile = sys_get_temp_dir() . '/' . uniqid('download_', true) . '.ods';
        
        $writer = new Writer();
        $writer->openToFile($tempFile);
        
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($sheetName);
        
        // Adiciona headers
        $headerCells = [];
        foreach ($headers as $header) {
            $headerCells[] = $header['title'];
        }
        $headerRow = Row::fromValues($headerCells);
        $writer->addRow($headerRow);
        
        // Adiciona dados filtrados
        foreach ($data as $rowData) {
            $rowValues = [];
            foreach ($headers as $header) {
                $cellValue = $rowData[$header['key']] ?? '';
                $rowValues[] = $cellValue;
            }
            $dataRow = Row::fromValues($rowValues);
            $writer->addRow($dataRow);
        }
        
        $writer->close();
        
        return $tempFile;
    }
}
