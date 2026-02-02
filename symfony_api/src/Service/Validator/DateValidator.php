<?php

namespace App\Service\Validator;

/**
 * Validador de Datas
 */
class DateValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        $value = trim((string)$value);

        // Verificar se é obrigatório e está vazio
        if (($rules['required'] ?? false) && $value === '') {
            return 'Campo obrigatório não preenchido';
        }

        // Se vazio e não obrigatório, é válido
        if ($value === '') {
            return null;
        }

        // Formato esperado (padrão: DD/MM/YYYY)
        $format = $rules['format'] ?? 'DD/MM/YYYY';

        // Mapeia formato da interface para formato PHP
        $phpFormat = $this->convertToPhpDateFormat($format);
        
        // Tenta validar com o formato exato primeiro
        $dateTime = \DateTime::createFromFormat($phpFormat, $value);

        // Se falhar, tenta ser flexível com a hora
        if ($dateTime === false && !str_contains($phpFormat, 'H')) {
            // Tenta remover a hora se existir
            $valueWithoutTime = preg_replace('/\s\d{2}:\d{2}:\d{2}$/', '', $value);
            if ($valueWithoutTime !== $value) {
                $dateTime = \DateTime::createFromFormat($phpFormat, $valueWithoutTime);
            }
        }

        // Valida se a data é válida
        if ($dateTime === false) {
            return "Formato de data inválido. Esperado: {$format}. Recebido: {$value}";
        }

        // Verifica se a data faz sentido (evita datas inválidas como 32/13/2024)
        $errors = \DateTime::getLastErrors();
        if ($errors && $errors['error_count'] > 0) {
            return "Data inválida: {$value}";
        }

        return null;
    }

    private function convertToPhpDateFormat(string $format): string
    {
        $mapping = [
            'DD/MM/YYYY' => 'd/m/Y',
            'DD/MM/YYYY HH:mm:ss' => 'd/m/Y H:i:s',
            'YYYY-MM-DD' => 'Y-m-d',
            'YYYY-MM-DD HH:mm:ss' => 'Y-m-d H:i:s',
            'MM/DD/YYYY' => 'm/d/Y',
        ];

        return $mapping[$format] ?? 'd/m/Y';
    }
}
