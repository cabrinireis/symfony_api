<?php

namespace App\Service;

use App\Service\Validator\ValidatorFactory;

/**
 * Serviço responsável por validar dados contra regras customizadas
 * 
 * Separação de responsabilidades:
 * - OdsReaderService: Lê o arquivo ODS
 * - ValidationService: Valida os dados contra as regras
 * - ValidatorFactory: Cria validadores específicos
 * - Validadores específicos: Implementam a lógica de validação para cada tipo
 */
class ValidationService
{
    /**
     * Valida dados contra regras customizadas
     * 
     * @param array $data Dados a validar (array de arrays)
     * @param array $headers Headers do arquivo (array com 'title' e 'key')
     * @param array $validations Regras de validação
     * 
     * @return array Resultado da validação com erros encontrados
     */
    public function validate(array $data, array $headers, array $validations): array
    {
        $validationErrors = [];
        $validatedRowCount = 0;
           $debugInfo = []; // ADICIONE ISTO

        // Criar mapa de coluna nome → índice
        $columnIndexMap = $this->createColumnIndexMap($headers);

        // Para cada linha de dados
        foreach ($data as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +1 porque começa em 0, +1 porque linha 1 é header

            // Para cada validação configurada
            foreach ($validations as $validation) {
                $columnName = $validation['column'] ?? null;
                $columnKey = $columnIndexMap[$columnName] ?? null;

                if (!$columnKey) {
                    continue;
                }

                // Extrair valor da célula
                $cellValue = $row[$columnKey] ?? '';
                 // DEBUG: Adicione isto
            $debugInfo[] = [
                'column' => $columnName,
                'value' => $cellValue,
                'type' => gettype($cellValue),
                'validationType' => $validation['type'] ?? null,
                'is_numeric' => is_numeric($cellValue),
                'is_string' => is_string($cellValue)
            ];
                // Executar validação
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
            'debug' => $debugInfo // ADICIONE ISTO
        ];
    }

    /**
     * Filtra dados válidos mantendo apenas linhas sem erro na validação
     * 
     * @param array $data Dados originais
     * @param array $headers Headers do arquivo
     * @param array $validations Regras de validação
     * 
     * @return array Dados filtrados (apenas linhas válidas)
     */
    public function filterValidData(array $data, array $headers, array $validations): array
    {
        $validData = [];
        $columnIndexMap = $this->createColumnIndexMap($headers);

        foreach ($data as $row) {
            $isRowValid = true;

            // Verificar se a linha tem algum erro
            foreach ($validations as $validation) {
                $columnName = $validation['column'] ?? null;
                $columnKey = $columnIndexMap[$columnName] ?? null;

                if (!$columnKey) {
                    continue;
                }

                $cellValue = $row[$columnKey] ?? '';

                // Se houver erro em qualquer célula desta linha, marca como inválida
                if ($this->validateCell($cellValue, $validation, 0, $columnName)) {
                    $isRowValid = false;
                    break;
                }
            }

            // Se a linha é válida, adicionar aos dados filtrados
            if ($isRowValid) {
                $validData[] = $row;
            }
        }

        return $validData;
    }

    /**
     * Valida uma célula individual contra suas regras
     * 
     * @param mixed $value Valor da célula
     * @param array $validation Regra de validação
     * @param int $rowNumber Número da linha
     * @param string $columnName Nome da coluna
     * 
     * @return null|array Null se válido, array com erro se inválido
     */
    private function validateCell($value, array $validation, int $rowNumber, string $columnName): ?array
    {
        $type = $validation['type'] ?? null;
        $rules = $validation['rules'] ?? [];

        if (!$type) {
            return null;
        }

        // Criar validador apropriado
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

        // Executar validação
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
     * Cria um mapa de coluna nome → chave (key)
     * 
     * @param array $headers Headers do arquivo
     * 
     * @return array Mapa [columnName => columnKey]
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
