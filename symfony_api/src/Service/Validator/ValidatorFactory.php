<?php

namespace App\Service\Validator;

/**
 * Factory for creating specific validators
 */
class ValidatorFactory
{
    /**
     * Creates a validator based on type
     * 
     * @param string $type Validation type (date, number, text, email, choice)
     * 
     * @return ValidatorInterface Appropriate validator
     * 
     * @throws \InvalidArgumentException If type is not supported
     */
    public static function create(string $type): ValidatorInterface
    {
        return match($type) {
            'date' => new DateValidator(),
            'number' => new NumberValidator(),
            'text' => new TextValidator(),
            'email' => new EmailValidator(),
            'choice' => new ChoiceValidator(),
            default => throw new \InvalidArgumentException("Tipo de validação não suportado: $type")
        };
    }
}
