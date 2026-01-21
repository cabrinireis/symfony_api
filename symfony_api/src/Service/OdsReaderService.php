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

        // Iterar sobre as abas do arquivo
        foreach ($reader->getSheetIterator() as $sheet) {
            // Verifica se é a aba que você procura
            if ($sheet->getName() === $targetSheetName) {
                
                $isFirstRow = true;
                
                // Itera sobre as linhas da aba específica
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();
                    
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
                        }
                        $isFirstRow = false;
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
