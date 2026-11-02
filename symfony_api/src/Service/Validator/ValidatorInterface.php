<?php

namespace App\Service\Validator;

/**
 * Base interface for all validators
 */
interface ValidatorInterface
{
    /**
     * Validates a cell value
     * 
     * @param mixed $value Value to validate
     * @param array $rules Validation rules
     * 
     * @return null|string Null if valid, error message if invalid
     */
    public function validate($value, array $rules): ?string;
}
