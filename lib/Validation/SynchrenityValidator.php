<?php

declare(strict_types=1);

namespace Synchrenity\Validation;

class SynchrenityValidator
{
    protected $auditTrail;
    protected $errors      = [];
    protected $customRules = [];
    protected $hooks       = [];

    public function setAuditTrail($auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    public function addRule($name, callable $rule)
    {
        $this->customRules[$name] = $rule;
    }
    public function addHook(callable $hook)
    {
        $this->hooks[] = $hook;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    // Validate data against rules
    public function validate($data, $rules)
    {
        $this->errors = [];
        $result       = true;
        $messages     = [
            'required'    => 'The :field field is required.',
            'email'       => 'The :field must be a valid email address.',
            'int'         => 'The :field must be an integer.',
            'numeric'     => 'The :field must be numeric.',
            'string'      => 'The :field must be a string.',
            'boolean'     => 'The :field must be true or false.',
            'min'         => 'The :field must be at least :min characters.',
            'max'         => 'The :field may not be greater than :max characters.',
            'confirmed'   => 'The :field confirmation does not match.',
            'date'        => 'The :field is not a valid date.',
            'url'         => 'The :field is not a valid URL.',
            'unique'      => 'The :field must be unique.',
            'in'          => 'The :field must be one of: :values.',
            'not_in'      => 'The :field must not be one of: :values.',
            'regex'       => 'The :field format is invalid.',
            'required_if' => 'The :field field is required when :other is :value.',
            'after'       => 'The :field must be a date after :date.',
            'before'      => 'The :field must be a date before :date.',
        ];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $bail  = false;

            foreach ((array)$fieldRules as $rule) {
                $params   = [];
                $origRule = $rule;

                if (is_string($rule)) {
                    if (strpos($rule, ':') !== false) {
                        list($rule, $paramStr) = explode(':', $rule, 2);
                        $params                = explode(',', $paramStr);
                    }
                }
                $valid = true;

                switch ($rule) {
                    case 'bail':
                        $bail = true;
                        continue 2;

                    case 'required':
                        $valid = !is_null($value) && $value !== '';
                        break;

                    case 'required_if':
                        $other    = $params[0] ?? null;
                        $otherVal = $params[1] ?? null;

                        if (($data[$other] ?? null) == $otherVal) {
                            $valid = !is_null($value) && $value !== '';
                        }
                        break;

                    case 'email':
                        $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        break;

                    case 'int':
                        $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                        break;

                    case 'numeric':
                        $valid = is_numeric($value);
                        break;

                    case 'string':
                        $valid = is_string($value);
                        break;

                    case 'boolean':
                        $valid = is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1' || $value === true || $value === false;
                        break;

                    case 'min':
                        $valid = is_numeric($value) ? $value >= (float)$params[0] : strlen($value) >= (int)$params[0];
                        break;

                    case 'max':
                        $valid = is_numeric($value) ? $value <= (float)$params[0] : strlen($value) <= (int)$params[0];
                        break;

                    case 'confirmed':
                        $valid = isset($data[$field.'_confirmation']) && $value === $data[$field.'_confirmation'];
                        break;

                    case 'date':
                        $valid = strtotime($value) !== false;
                        break;

                    case 'url':
                        $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                        break;

                    case 'unique':
                        // Usage: unique:table,column, except_id (requires custom handler)
                        if (isset($this->customRules['unique'])) {
                            $valid = call_user_func($this->customRules['unique'], $value, $params, $data);
                        }
                        break;

                    case 'in':
                        $valid = in_array($value, $params);
                        break;

                    case 'not_in':
                        $valid = !in_array($value, $params);
                        break;

                    case 'regex':
                        $valid = preg_match($params[0], $value);
                        break;

                    case 'after':
                        $valid = strtotime($value) > strtotime($params[0]);
                        break;

                    case 'before':
                        $valid = strtotime($value) < strtotime($params[0]);
                        break;
                    default:
                        if (isset($this->customRules[$rule]) && is_callable($this->customRules[$rule])) {
                            $valid = call_user_func($this->customRules[$rule], $value, $params, $data);
                        }
                        break;
                }

                if (!$valid) {
                    $result                 = false;
                    $msg                    = $messages[$rule] ?? $rule;
                    $msg                    = str_replace([':field', ':min', ':max', ':values', ':other', ':value', ':date'], [$field, $params[0] ?? '', $params[0] ?? '', implode(', ', $params), $params[0] ?? '', $params[1] ?? '', $params[0] ?? ''], $msg);
                    $this->errors[$field][] = $msg;

                    if ($bail) {
                        break;
                    }
                }
            }
        }

        foreach ($this->hooks as $hook) {
            call_user_func($hook, $data, $rules, $this->errors);
        }
        $meta = [ 'data' => $data, 'rules' => $rules, 'errors' => $this->errors ];

        if ($this->auditTrail) {
            $this->auditTrail->log('validate', $meta, null);
        }

        return $result;
    }

    // Output validation result as JSON for API
    public function toJson()
    {
        return json_encode(['errors' => $this->errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
