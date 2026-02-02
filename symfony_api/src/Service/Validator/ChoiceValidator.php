<?php

namespace App\Service\Validator;

/**
 * Validador de Escolha (Enum)
 */
class ChoiceValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        $value = (string)$value;

        // Verificar se é obrigatório e está vazio
        if (($rules['required'] ?? false) && empty($value)) {
            return 'Campo obrigatório não preenchido';
        }

        // Se vazio e não obrigatório, é válido
        if (empty($value)) {
            return null;
        }

        $allowedValues = $rules['allowedValues'] ?? [];

        // Validar se está na lista de valores permitidos
        if (!in_array($value, $allowedValues, true)) {
            $valuesList = implode(', ', $allowedValues);
            return "Deve ser um dos valores: $valuesList";
        }

        return null;
    }
}
