<?php

namespace App\Service\Validator;

/**
 * Email Validator
 */
class EmailValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        $value = (string)$value;

        // Check if required and empty
        if (($rules['required'] ?? false) && empty($value)) {
            return 'Campo obrigatório não preenchido';
        }

        // If empty and not required, it is valid
        if (empty($value)) {
            return null;
        }

        // Validate email format
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Email inválido';
        }

        return null;
    }
}
