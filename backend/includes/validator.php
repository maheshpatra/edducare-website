<?php
class Validator {
    private $errors = [];

    public function validate($data, $rules) {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = explode('|', $fieldRules);

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    private function applyRule($field, $value, $rule, $data) {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleValue = $ruleParts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = ucfirst($field) . ' is required';
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be a valid email';
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $ruleValue) {
                    $this->errors[$field][] = ucfirst($field) . ' must be at least ' . $ruleValue . ' characters';
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $ruleValue) {
                    $this->errors[$field][] = ucfirst($field) . ' must not exceed ' . $ruleValue . ' characters';
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be numeric';
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be a valid date';
                }
                break;

            case 'in':
                $allowedValues = explode(',', $ruleValue);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be one of: ' . implode(', ', $allowedValues);
                }
                break;
        }
    }

    public function getErrors() {
        return $this->errors;
    }
}
?>
