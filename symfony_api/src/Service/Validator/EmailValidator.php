<?php

namespace App\Service\Validator;

/**
 * Validador de Email
 */
class EmailValidator implements ValidatorInterface
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

        // Validar formato de email
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Email inválido';
        }

        return null;
    }
}
