<?php

namespace App\Service;

use App\Service\Validator\ValidatorFactory;

/**
 * Service responsible for validating data against custom rules
 * 
 * Responsibility split:
 * - OdsReaderService: Reads the ODS file
 * - ValidationService: Validates data against rules
 * - ValidatorFactory: Creates specific validators
 * - Specific validators: Implement validation logic for each type
 */
class ValidationService
{
    /**
     * Validates data against custom rules
     * 
     * @param array $data Data to validate (array of arrays)
     * @param array $headers File headers (array with 'title' and 'key')
     * @param array $validations Validation rules
     * 
     * @return array Validation result with found errors
     */
    public function validate(array $data, array $headers, array $validations): array
    {
        $validationErrors = [];
        $validatedRowCount = 0;
           $debugInfo = []; // ADD THIS

        // Build map: column name -> key
        $columnIndexMap = $this->createColumnIndexMap($headers);

        // For each data row
        foreach ($data as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +1 because index starts at 0, +1 because row 1 is header

            // For each configured validation
            foreach ($validations as $validation) {
                $columnName = $validation['column'] ?? null;
                $columnKey = $columnIndexMap[$columnName] ?? null;

                if (!$columnKey) {
                    continue;
                }

                 // Extract cell value
                $cellValue = $row[$columnKey] ?? '';
                  // DEBUG: Add this
            $debugInfo[] = [
                'column' => $columnName,
                'value' => $cellValue,
                'type' => gettype($cellValue),
                'validationType' => $validation['type'] ?? null,
                'is_numeric' => is_numeric($cellValue),
                'is_string' => is_string($cellValue)
            ];
                // Execute validation
                $error = $this->validateCell(
                    $cellValue,
                    $validation,
                    $rowNumber,
                    $columnName
                );

                if ($error) {
                    $validationErrors[] = $error;
                }
            }

            $validatedRowCount++;
        }

        return [
            'success' => empty($validationErrors),
            'message' => empty($validationErrors)
                ? 'Todas as validações foram bem-sucedidas'
                : 'Foram encontrados erros de validação',
            'validatedRowsCount' => $validatedRowCount,
            'validationErrors' => $validationErrors,
            'errorCount' => count($validationErrors),
            'debug' => $debugInfo // ADD THIS
        ];
    }

    /**
     * Filters valid data keeping only rows without validation errors
     * 
     * @param array $data Original data
     * @param array $headers File headers
     * @param array $validations Validation rules
     * 
     * @return array Filtered data (valid rows only)
     */
    public function filterValidData(array $data, array $headers, array $validations): array
    {
        $validData = [];
        $columnIndexMap = $this->createColumnIndexMap($headers);

        foreach ($data as $row) {
            $isRowValid = true;

            // Check if row has any error
            foreach ($validations as $validation) {
                $columnName = $validation['column'] ?? null;
                $columnKey = $columnIndexMap[$columnName] ?? null;

                if (!$columnKey) {
                    continue;
                }

                $cellValue = $row[$columnKey] ?? '';

                // If any cell fails, mark row as invalid
                if ($this->validateCell($cellValue, $validation, 0, $columnName)) {
                    $isRowValid = false;
                    break;
                }
            }

            // If row is valid, add it to filtered data
            if ($isRowValid) {
                $validData[] = $row;
            }
        }

        return $validData;
    }

    /**
        * Validates a single cell against its rules
     * 
        * @param mixed $value Cell value
        * @param array $validation Validation rule
        * @param int $rowNumber Row number
        * @param string $columnName Column name
     * 
        * @return null|array Null if valid, error array if invalid
     */
    private function validateCell($value, array $validation, int $rowNumber, string $columnName): ?array
    {
        $type = $validation['type'] ?? null;
        $rules = $validation['rules'] ?? [];

        if (!$type) {
            return null;
        }

        // Create the appropriate validator
        try {
            $validator = ValidatorFactory::create($type);
        } catch (\InvalidArgumentException $e) {
            return [
                'row' => $rowNumber,
                'column' => $columnName,
                'value' => (string)$value,
                'type' => $type,
                'message' => 'Tipo de validação inválido'
            ];
        }

        // Execute validation
        $errorMessage = $validator->validate($value, $rules);

        if ($errorMessage) {
            return [
                'row' => $rowNumber,
                'column' => $columnName,
                'value' => (string)$value,
                'type' => $type,
                'message' => $errorMessage
            ];
        }

        return null;
    }

    /**
        * Creates a map of column name -> key
     * 
        * @param array $headers File headers
     * 
        * @return array Map [columnName => columnKey]
     */
    private function createColumnIndexMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $header) {
            $title = $header['title'] ?? null;
            $key = $header['key'] ?? null;

            if ($title && $key) {
                $map[$title] = $key;
            }
        }
        return $map;
    }
}
