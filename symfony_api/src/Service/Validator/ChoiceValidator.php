<?php

namespace App\Service\Validator;

/**
 * Choice Validator (Enum)
 */
class ChoiceValidator implements ValidatorInterface
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

        $allowedValues = $rules['allowedValues'] ?? [];

        // Validate if value is in the allowed list
        if (!in_array($value, $allowedValues, true)) {
            $valuesList = implode(', ', $allowedValues);
            return "Deve ser um dos valores: $valuesList";
        }

        return null;
    }
}
