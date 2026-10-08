<?php
declare(strict_types=1);

namespace VeloxRouter\Validator;

class Validator
{
    /**
     * @param array<string, mixed> $data Dados a validar
     * @param array<string, string> $rules Regras de validação (ex: ['email' => 'required|email'])
     * @param array<string, string> $messages Mensagens customizadas opcionais
     * @param string $locale Idioma ('pt' ou 'en')
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly string $locale = 'pt'
    ) {}

    public function passes(): bool
    {
        return empty($this->errors());
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->rules as $field => $ruleString) {
            foreach (explode('|', $ruleString) as $rule) {
                $value = $this->data[$field] ?? null;
                
                $ruleParts = explode(':', $rule, 2);
                $ruleName = $ruleParts[0];
                $ruleParam = $ruleParts[1] ?? null;

                $key = "$field.$ruleName";
                $isValid = true;

                switch ($ruleName) {
                    case 'required':
                        $isValid = !empty($value) || $value === 0 || $value === '0';
                        break;
                    case 'email':
                        $isValid = empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        break;
                    case 'url':
                        $isValid = empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
                        break;
                    case 'numeric':
                        $isValid = empty($value) || is_numeric($value);
                        break;
                    case 'alpha':
                        $isValid = empty($value) || ctype_alpha((string)$value);
                        break;
                    case 'alphanum':
                        $isValid = empty($value) || ctype_alnum((string)$value);
                        break;
                    case 'uuid':
                        $isValid = empty($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$value);
                        break;
                    case 'min':
                        if (!empty($value)) {
                            $limit = (int)$ruleParam;
                            $isValid = is_numeric($value) ? ((float)$value >= $limit) : (mb_strlen((string)$value) >= $limit);
                        }
                        break;
                    case 'max':
                        if (!empty($value)) {
                            $limit = (int)$ruleParam;
                            $isValid = is_numeric($value) ? ((float)$value <= $limit) : (mb_strlen((string)$value) <= $limit);
                        }
                        break;
                    case 'oneof':
                        if (!empty($value) && $ruleParam !== null) {
                            $allowed = explode(',', $ruleParam);
                            $isValid = in_array((string)$value, $allowed, true);
                        }
                        break;
                }

                if (!$isValid) {
                    $msg = $this->messages[$key] 
                        ?? $this->messages[$field] 
                        ?? $this->getDefaultMessage($field, $ruleName, $ruleParam);
                    
                    $errors[$field][] = $msg;
                }
            }
        }

        return $errors;
    }

    private function getDefaultMessage(string $field, string $ruleName, ?string $param): string
    {
        $isEnglish = str_starts_with(strtolower($this->locale), 'en');

        return match ($ruleName) {
            'required'   => $isEnglish ? "Field '$field' is required." : "O campo '$field' é obrigatório.",
            'email'      => $isEnglish ? "Field '$field' must be a valid email address." : "O campo '$field' deve ser um endereço de email válido.",
            'url'        => $isEnglish ? "Field '$field' must be a valid URL." : "O campo '$field' deve ser um URL válido.",
            'numeric'    => $isEnglish ? "Field '$field' must contain only numeric values." : "O campo '$field' deve conter apenas valores numéricos.",
            'alpha'      => $isEnglish ? "Field '$field' must contain only alphabetic characters." : "O campo '$field' deve conter apenas letras.",
            'alphanum'   => $isEnglish ? "Field '$field' must contain only alphanumeric characters." : "O campo '$field' deve conter apenas caracteres alfanuméricos.",
            'uuid'       => $isEnglish ? "Field '$field' must be a valid UUID." : "O campo '$field' deve ser um UUID válido.",
            'min'        => $isEnglish ? "Field '$field' must be at least $param." : "O campo '$field' deve ser de pelo menos $param.",
            'max'        => $isEnglish ? "Field '$field' must be at most $param." : "O campo '$field' deve ser no máximo $param.",
            'oneof'      => $isEnglish ? "Field '$field' must be one of: $param." : "O campo '$field' deve ser um de: $param.",
            default      => $isEnglish ? "Field '$field' is invalid." : "O campo '$field' é inválido.",
        };
    }
}

