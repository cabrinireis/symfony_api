<?php

namespace App\Service;

use OpenSpout\Reader\ODS\Reader;
use OpenSpout\Common\Entity\Row;

class OdsReaderService
{
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
                    $cells = $row->toArray();
                    // Garante que todos os valores sejam convertidos para string
                    $cells = array_map(function ($cell) {
                        return $cell instanceof \DateTimeImmutable ? $cell->format('Y-m-d H:i:s') : (string)$cell;
                    }, $cells);
                    // Primeira linha = cabeçalhos
                    if ($isFirstRow) {
                        foreach ($cells as $index => $cellValue) {
                            $key = $this->generateKey($cellValue, $index);
                            $headerKeys[] = $key;
                            $headers[] = [
                                'title' => $cellValue ?? 'Column ' . ($index + 1),
                                'align' => 'start',
                                'sortable' => true,
                                'key' => $key,
                            ];
                            if (strtolower(trim($cellValue)) === 'numéro pif') {
                                $numPifColIndex = $index;
                            }
                        }
                        $isFirstRow = false;
                        continue;
                    }
                    // Case 1: Se 'Numéro PIF' == 0, ignora linha
                    if ($numPifColIndex !== null && isset($cells[$numPifColIndex]) && (string)$cells[$numPifColIndex] === '0') {
                        continue;
                    }
                    // Case 2: Se algum valor == '#N/D', adiciona erro e ignora linha
                    $hasError = false;
                    $errorColumns = [];
                    foreach ($cells as $colIndex => $value) {
                        if ((string)$value === '#N/D') {
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
                        $rowData[$key] = $cells[$index] ?? null;
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
}
