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

        // Iterar sobre as abas do arquivo
        foreach ($reader->getSheetIterator() as $sheet) {
            // Verifica se é a aba que você procura
            if ($sheet->getName() === $targetSheetName) {
                
                // Itera sobre as linhas da aba específica
                foreach ($sheet->getRowIterator() as $row) {
                    // Transforma o objeto Row em um array de dados
                    $data[] = $row->toArray();
                }
                
                // Performance: Encontrou a aba e leu os dados? Para a execução.
                break;
            }
        }

        $reader->close();

        return $data;
    }
}