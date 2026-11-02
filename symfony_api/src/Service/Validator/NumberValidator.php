<?php

namespace App\Service\Validator;

/**
 * Number Validator
 */
class NumberValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        // Convert to string and trim spaces
        $value = trim((string)$value);

        // Check if required and empty
        if (($rules['required'] ?? false) && $value === '') {
            return 'Campo obrigatório não preenchido';
        }

        // If empty and not required, it is valid
        if ($value === '') {
            return null;
        }
        // Validate numeric value
        if (!is_numeric($value)) {
            return 'Não é um número válido';
        }

        // Convert to number (float supports decimals)
        $numValue = (float)$value;

        // Validate zero FIRST (before min/max)
        if (!($rules['allowZero'] ?? true) && $numValue == 0) {
            return 'Zero não é permitido';
        }

        // Validate decimal places
        if (!($rules['allowDecimal'] ?? true) && strpos($value, '.') !== false) {
            return 'Casas decimais não permitidas';
        }

        // Validate minimum value
        if (isset($rules['minValue']) && $numValue < (float)$rules['minValue']) {
            return "Deve ser maior ou igual a {$rules['minValue']}";
        }

        // Validate maximum value
        if (isset($rules['maxValue']) && $numValue > (float)$rules['maxValue']) {
            return "Deve ser menor ou igual a {$rules['maxValue']}";
        }

        return null;
    }
}
