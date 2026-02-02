<?php

namespace App\Service\Validator;

/**
 * Validador de Texto
 */
class TextValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        $value = (string)$value;
        error_log('NumberValidator - Value: ' . var_export($value, true) . ', Type: ' . gettype($value) . ', is_numeric: ' . var_export(is_numeric($value), true));

        // Verificar se é obrigatório e está vazio
        if (($rules['required'] ?? false) && empty($value)) {
            return 'Campo obrigatório não preenchido';
        }

        // Se vazio e não obrigatório, é válido
        if (empty($value)) {
            return null;
        }

// ADICIONE ISTO: Rejeitar números puros
    if (is_numeric($value) && !isset($rules['allowNumeric'])) {
        return 'Não é permitido valores numéricos puros. Use o tipo "number" para números';
    }
    
        $length = strlen($value);

        // Validar comprimento mínimo
        if (isset($rules['minLength']) && $length < $rules['minLength']) {
            return "Deve ter no mínimo {$rules['minLength']} caracteres";
        }

        // Validar comprimento máximo
        if (isset($rules['maxLength']) && $length > $rules['maxLength']) {
            return "Deve ter no máximo {$rules['maxLength']} caracteres";
        }

        return null;
    }
}
