<?php

namespace App\Service;

use OpenSpout\Reader\ODS\Reader;
use OpenSpout\Writer\ODS\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class OdsReaderService
{
    /**
     * Detects the real value type in ODS
     */
    private function detectCellType($cellValue): string
    {
        // Null/empty value
        if ($cellValue === null || $cellValue === '') {
            return 'null';
        }

        // Native DateTime value (LibreOffice stores values like this)
        if ($cellValue instanceof \DateTimeInterface) {
            return 'datetime';
        }

        // Boolean value
        if (is_bool($cellValue)) {
            return 'boolean';
        }

        // Numeric value
        if (is_int($cellValue) || is_float($cellValue)) {
            // Check whether it could be an Excel/ODS serial date
            // Valid serial date values are roughly between 0 and 100000
            if (is_float($cellValue) && $cellValue > 0 && $cellValue < 100000) {
                // Could be a serial date
                return 'number_or_date_serial';
            }
            return 'number';
        }

        // String that looks like a date
        if (is_string($cellValue)) {
            if ($this->looksLikeDate($cellValue)) {
                return 'date_string';
            }
            return 'string';
        }

        return 'unknown';
    }

    /**
     * Checks whether a string looks like a date
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
     * Formats value while preserving original type
     */
    private function formatCellValue($cellValue, string $detectedType): string
    {
        // ✅ Native DateTime
        if ($detectedType === 'datetime' && $cellValue instanceof \DateTimeInterface) {
            return $cellValue->format('Y-m-d H:i:s');
        }

        // ✅ Excel/ODS serial date (numeric)
        if ($detectedType === 'number_or_date_serial' && is_float($cellValue)) {
            try {
                // ODS base date: 1899-12-30 (day 0)
                $baseDate = new \DateTime('1899-12-30');
                $days = (int)$cellValue;
                $baseDate->modify("+{$days} days");

                // Decimal part represents hours/minutes/seconds
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

        // ✅ Date-like string
        if ($detectedType === 'date_string') {
            return (string)$cellValue;
        }

        // ✅ Number
        if ($detectedType === 'number') {
            return (string)$cellValue;
        }

        // ✅ Boolean
        if ($detectedType === 'boolean') {
            return $cellValue ? 'true' : 'false';
        }

        // ✅ Null/empty
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
        $rowNumber = 1; // Row counter used to identify errors

        // Iterate over workbook sheets
        foreach ($reader->getSheetIterator() as $sheet) {
            // Check if this is the target sheet
            if ($sheet->getName() === $targetSheetName) {
                $isFirstRow = true;
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $formattedCells = [];
                    
                    // Process each cell: detect type and format value
                    foreach ($cells as $cell) {
                        $cellValue = $cell->getValue();
                        $detectedType = $this->detectCellType($cellValue);
                        $formattedValue = $this->formatCellValue($cellValue, $detectedType);
                        $formattedCells[] = $formattedValue;
                    }
                    
                    // First row = headers
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
                    
                    // Case 1: if 'Numéro PIF' == 0, skip row
                    if ($numPifColIndex !== null && isset($formattedCells[$numPifColIndex]) && $formattedCells[$numPifColIndex] === '0') {
                        continue;
                    }
                    
                    // Case 2: if any value is '#N/...', register error and skip row
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
                    
                    // Data rows
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
        * Generates a valid key from the column title
     */
    private function generateKey(?string $title, int $index): string
    {
        if ($title === null || trim($title) === '') {
            return 'column_' . $index;
        }

        // Remove accents, lowercase, and replace spaces with underscore
        $key = strtolower(trim($title));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');

        return $key ?: 'column_' . $index;
    }

    /**
        * Generates a new ODS file with filtered rows
     */
    public function generateFilteredOds(array $data, array $headers, string $sheetName): string
    {
        $tempFile = sys_get_temp_dir() . '/' . uniqid('download_', true) . '.ods';
        
        $writer = new Writer();
        $writer->openToFile($tempFile);
        
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($sheetName);
        
        // Add headers
        $headerCells = [];
        foreach ($headers as $header) {
            $headerCells[] = $header['title'];
        }
        $headerRow = Row::fromValues($headerCells);
        $writer->addRow($headerRow);
        
        // Add filtered data
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
