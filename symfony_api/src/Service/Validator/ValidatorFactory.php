<?php

namespace App\Service\Validator;

/**
 * Factory para criar validadores específicos
 */
class ValidatorFactory
{
    /**
     * Cria um validador baseado no tipo
     * 
     * @param string $type Tipo de validação (date, number, text, email, choice)
     * 
     * @return ValidatorInterface Validador apropriado
     * 
     * @throws \InvalidArgumentException Se tipo não suportado
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
