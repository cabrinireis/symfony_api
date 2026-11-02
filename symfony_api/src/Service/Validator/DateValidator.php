<?php

namespace App\Service\Validator;

/**
 * Date Validator
 */
class DateValidator implements ValidatorInterface
{
    public function validate($value, array $rules): ?string
    {
        $value = trim((string)$value);

        // Check if required and empty
        if (($rules['required'] ?? false) && $value === '') {
            return 'Campo obrigatÃ³rio nÃ£o preenchido';
        }

        // If empty and not required, it is valid
        if ($value === '') {
            return null;
        }

        $format = $rules['format'] ?? 'DD/MM/YYYY';
        $phpFormat = $this->convertToPhpDateFormat($format);

        // Native date value from ODS (normalized by OdsReaderService)
        $candidates = [[$phpFormat, $value]];
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
            $candidates[] = [str_contains($value, ':') ? 'Y-m-d H:i:s' : 'Y-m-d', $value];
        }
        // Tolerates extra time component when format does not include it
        if (!str_contains($phpFormat, 'H')) {
            $candidates[] = [$phpFormat, preg_replace('/\s\d{2}:\d{2}(:\d{2})?$/', '', $value)];
        }

        foreach ($candidates as [$fmt, $val]) {
            $dt = \DateTime::createFromFormat('!' . $fmt, $val);
            $err = \DateTime::getLastErrors();
            if ($dt !== false && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))
                && $dt->format($fmt) === $val) {
                return null;
            }
        }

        return "Formato de data inválido. Esperado: {$format}. Recebido: {$value}";
    }

    private function convertToPhpDateFormat(string $format): string
    {
        return strtr($format, [
            'YYYY' => 'Y', 'YY' => 'y',
            'DD' => 'd', 'MM' => 'm',
            'HH' => 'H', 'mm' => 'i', 'ss' => 's',
        ]);
    }
}
