<?php

namespace App\Service\Validator;

/**
 * Interface base para todos os validadores
 */
interface ValidatorInterface
{
    /**
     * Valida um valor de célula
     * 
     * @param mixed $value Valor a validar
     * @param array $rules Regras de validação
     * 
     * @return null|string Null se válido, mensagem de erro se inválido
     */
    public function validate($value, array $rules): ?string;
}
