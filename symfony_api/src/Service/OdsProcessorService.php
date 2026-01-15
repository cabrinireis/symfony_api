<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class OdsProcessorService
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
     * Processa arquivo .ods e extrai dados da aba "XX) Onglet technique - TOTAL"
     */
    public function processOdsFile(UploadedFile $file): array
    {
        try {
            // Validar arquivo
            $this->validateFile($file);

            // Carregar planilha
            $spreadsheet = $this->loadSpreadsheet($file);
            
            // Encontrar aba específica
            $worksheet = $this->findWorksheet($spreadsheet, 'XX) Onglet technique - TOTAL');
            
            if (!$worksheet) {
                throw new \RuntimeException('Aba "XX) Onglet technique - TOTAL" não encontrada');
            }

            // Extrair e validar dados
            $data = $this->extractAndValidateData($worksheet);

            return [
                'success' => true,
                'data' => $data,
                'total_rows' => count($data),
                'errors' => [],
                'summary' => $this->generateSummary($data)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'errors' => [$e->getMessage()]
            ];
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        $constraints = [
            new Assert\File([
                'maxSize' => '20M',
                'mimeTypes' => [
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.oasis.opendocument.spreadsheet-template'
                ],
                'mimeTypesMessage' => 'Por favor, envie um arquivo .ods válido'
            ])
        ];

        $violations = $this->validator->validate($file, $constraints);
        
        if (count($violations) > 0) {
            throw new \InvalidArgumentException($violations[0]->getMessage());
        }
    }

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        try {
            return IOFactory::load($file->getPathname());
        } catch (\Exception $e) {
            throw new \RuntimeException('Erro ao carregar arquivo .ods: ' . $e->getMessage());
        }
    }

    private function findWorksheet(Spreadsheet $spreadsheet, string $sheetName): ?Worksheet
    {
        // Busca exata primeiro
        if ($spreadsheet->sheetNameExists($sheetName)) {
            return $spreadsheet->getSheetByName($sheetName);
        }

        // Busca parcial (case insensitive)
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (stripos($sheet->getTitle(), 'Onglet technique') !== false && 
                stripos($sheet->getTitle(), 'TOTAL') !== false) {
                return $sheet;
            }
        }

        return null;
    }

    private function extractAndValidateData(Worksheet $worksheet): array
    {
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Encontrar cabeçalho (linha que contém "XX) Onglet technique - TOTAL")
        $headerRow = $this->findHeaderRow($worksheet, $highestRow);
        
        if ($headerRow === null) {
            throw new \RuntimeException('Cabeçalho não encontrado na planilha');
        }

        // Extrair cabeçalhos
        $headers = $this->extractHeaders($worksheet, $headerRow, $highestColumn);
        
        // Processar linhas de dados
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $rowData = $this->extractRowData($worksheet, $row, $highestColumn);
            
            // Pular linhas vazias
            if (empty(array_filter($rowData))) {
                continue;
            }

            // Validar dados da linha
            $validatedRow = $this->validateRowData($rowData, $headers, $row);
            
            $data[] = $validatedRow;
        }

        return $data;
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
        
        // Se não encontrar nas primeiras linhas, assume linha 1
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
            
            // Converter datas, números, etc.
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

        // Validações específicas baseadas nos cabeçalhos
        foreach ($headers as $header) {
            $column = $header['column'];
            $value = $rowData[$column] ?? null;
            
            // Exemplo de validações (ajustar conforme necessário)
            if ($value === null || $value === '') {
                $validatedRow['validation']['warnings'][] = [
                    'field' => $header['name'],
                    'column' => $column,
                    'message' => 'Campo vazio'
                ];
                continue;
            }

            // Validar formato de dados específicos
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

    private function generateSummary(array $data): array
    {
        $totalRows = count($data);
        $validRows = 0;
        $rowsWithErrors = 0;
        $rowsWithWarnings = 0;

        foreach ($data as $row) {
            if ($row['validation']['is_valid']) {
                $validRows++;
            } else {
                $rowsWithErrors++;
            }
            
            if (!empty($row['validation']['warnings'])) {
                $rowsWithWarnings++;
            }
        }

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'rows_with_errors' => $rowsWithErrors,
            'rows_with_warnings' => $rowsWithWarnings,
            'success_rate' => $totalRows > 0 ? round(($validRows / $totalRows) * 100, 2) : 0
        ];
    }
}
