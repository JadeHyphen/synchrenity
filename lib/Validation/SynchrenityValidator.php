<?php
namespace Synchrenity\Validation;

class SynchrenityValidator {
    protected $auditTrail;
    protected $errors = [];
    protected $customRules = [];
    protected $hooks = [];

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    public function addRule($name, callable $rule) {
        $this->customRules[$name] = $rule;
    }
    public function addHook(callable $hook) {
        $this->hooks[] = $hook;
    }

    public function getErrors() {
        return $this->errors;
    }

    // Validate data against rules
    public function validate($data, $rules) {
        $this->errors = [];
        $result = true;
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ((array)$fieldRules as $rule) {
                $params = [];
                if (is_string($rule)) {
                    if (strpos($rule, ':') !== false) {
                        list($rule, $paramStr) = explode(':', $rule, 2);
                        $params = explode(',', $paramStr);
                    }
                }
                $valid = true;
                switch ($rule) {
                    case 'required':
                        $valid = !is_null($value) && $value !== '';
                        break;
                    case 'email':
                        $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        break;
                    case 'int':
                        $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                        break;
                    case 'string':
                        $valid = is_string($value);
                        break;
                    case 'min':
                        $valid = strlen($value) >= (int)$params[0];
                        break;
                    case 'max':
                        $valid = strlen($value) <= (int)$params[0];
                        break;
                    case 'regex':
                        $valid = preg_match($params[0], $value);
                        break;
                    default:
                        if (isset($this->customRules[$rule]) && is_callable($this->customRules[$rule])) {
                            $valid = call_user_func($this->customRules[$rule], $value, $params, $data);
                        }
                        break;
                }
                if (!$valid) {
                    $result = false;
                    $this->errors[$field][] = $rule;
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
}
