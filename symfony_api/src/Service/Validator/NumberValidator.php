<?php

namespace App\Service\Validator;

/**
 * Validador de Números
 */
class NumberValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        // Converter para string e remover espaços
        $value = trim((string)$value);

        // Verificar se é obrigatório e está vazio
        if (($rules['required'] ?? false) && $value === '') {
            return 'Campo obrigatório não preenchido';
        }

        // Se vazio e não obrigatório, é válido
        if ($value === '') {
            return null;
        }
        // Validar se é numérico
        if (!is_numeric($value)) {
            return 'Não é um número válido';
        }

        // Converter para número (float para suportar decimais)
        $numValue = (float)$value;

        // Validar zero PRIMEIRO (antes de min/max)
        if (!($rules['allowZero'] ?? true) && $numValue == 0) {
            return 'Zero não é permitido';
        }

        // Validar casas decimais
        if (!($rules['allowDecimal'] ?? true) && strpos($value, '.') !== false) {
            return 'Casas decimais não permitidas';
        }

        // Validar mínimo
        if (isset($rules['minValue']) && $numValue < (float)$rules['minValue']) {
            return "Deve ser maior ou igual a {$rules['minValue']}";
        }

        // Validar máximo
        if (isset($rules['maxValue']) && $numValue > (float)$rules['maxValue']) {
            return "Deve ser menor ou igual a {$rules['maxValue']}";
        }

        return null;
    }
}
