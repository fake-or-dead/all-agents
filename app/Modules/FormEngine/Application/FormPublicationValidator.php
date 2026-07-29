<?php

namespace App\Modules\FormEngine\Application;

use InvalidArgumentException;

final class FormPublicationValidator
{
    private const array FieldTypes = [
        'short_text',
        'long_text',
        'phone',
        'single_choice',
        'multi_choice',
        'select',
        'date',
        'repeatable_group',
        'acknowledgement',
    ];

    private const array ValidationRules = [
        'required',
        'min_length',
        'max_length',
        'pattern',
        'phone',
        'row_complete',
    ];

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    public function validate(array $sections): void
    {
        $fieldKeys = [];
        $fieldDefinitions = [];
        $sectionKeys = [];
        $sectionOrders = [];

        foreach ($sections as $section) {
            $sectionKey = $section['semantic_key'] ?? null;
            $sectionOrder = $section['display_order'] ?? null;

            if (! is_string($sectionKey) || $sectionKey === '') {
                throw new InvalidArgumentException('Section semantic key is required.');
            }

            $sectionTitle = $section['title_th'] ?? null;

            if (! is_string($sectionTitle) || trim($sectionTitle) === '') {
                throw new InvalidArgumentException(
                    "Thai section title is required for {$sectionKey}.",
                );
            }

            if (mb_strlen($sectionTitle) > 240) {
                throw new InvalidArgumentException(
                    "Thai section title exceeds 240 characters for {$sectionKey}.",
                );
            }

            if (isset($sectionKeys[$sectionKey])) {
                throw new InvalidArgumentException("Duplicate section semantic key: {$sectionKey}.");
            }

            if (! is_int($sectionOrder) || $sectionOrder < 1) {
                throw new InvalidArgumentException("Invalid section display order in {$sectionKey}.");
            }

            if (isset($sectionOrders[$sectionOrder])) {
                throw new InvalidArgumentException("Duplicate section display order: {$sectionOrder}.");
            }

            $sectionKeys[$sectionKey] = true;
            $sectionOrders[$sectionOrder] = true;
            $fields = $section['fields'] ?? [];

            if (! is_array($fields) || ! array_is_list($fields)) {
                throw new InvalidArgumentException('Section fields must be a list.');
            }

            $fieldOrders = [];
            foreach ($fields as $field) {
                if (! is_array($field)) {
                    throw new InvalidArgumentException('Form fields must be objects.');
                }

                $key = $field['semantic_key'] ?? null;

                if (! is_string($key) || $key === '') {
                    throw new InvalidArgumentException('Field semantic key is required.');
                }

                if (isset($fieldKeys[$key])) {
                    throw new InvalidArgumentException("Duplicate field semantic key: {$key}.");
                }

                $label = $field['label_th'] ?? null;

                if (! is_string($label) || trim($label) === '') {
                    throw new InvalidArgumentException("Thai field label is required for {$key}.");
                }

                if (mb_strlen($label) > 500) {
                    throw new InvalidArgumentException(
                        "Thai field label exceeds 500 characters for {$key}.",
                    );
                }

                $type = $field['field_type'] ?? null;

                if (! is_string($type) || ! in_array($type, self::FieldTypes, true)) {
                    $actual = is_string($type) ? $type : get_debug_type($type);
                    throw new InvalidArgumentException("Unknown field type for {$key}: {$actual}.");
                }

                $hiddenAnswerPolicy = $field['hidden_answer_policy'] ?? null;

                if (
                    ! is_string($hiddenAnswerPolicy)
                    || ! in_array($hiddenAnswerPolicy, ['clear', 'retain'], true)
                ) {
                    throw new InvalidArgumentException(
                        "Hidden-answer policy is required for {$key}.",
                    );
                }

                $fieldKeys[$key] = true;
                $fieldDefinitions[$key] = $field;
                $fieldOrder = $field['display_order'] ?? null;

                if (! is_int($fieldOrder) || $fieldOrder < 1) {
                    throw new InvalidArgumentException("Invalid field display order in {$key}.");
                }

                if (isset($fieldOrders[$fieldOrder])) {
                    throw new InvalidArgumentException(
                        "Duplicate field display order in {$sectionKey}: {$fieldOrder}.",
                    );
                }

                $fieldOrders[$fieldOrder] = true;
                $this->validateOptions($key, $field['options'] ?? []);
            }
        }

        foreach ($fieldDefinitions as $key => $field) {
            $required = $field['required_rule'] ?? null;

            if (! is_bool($required)) {
                $this->validateRule($key, $required);
            }

            $visibility = $field['visibility_rule'] ?? null;

            if ($visibility !== null) {
                $this->validateRule($key, $visibility);
            }

            $this->validateValidationRules($key, $field['validation_rules'] ?? null);
            $initialValue = $field['initial_value'] ?? null;
            $this->validateStoredValue($key, $initialValue);
            $this->assertEncodedJsonWithinBounds($key, $initialValue, 'Initial value');
        }

        $dependencies = array_fill_keys(array_keys($fieldDefinitions), []);

        foreach ($fieldDefinitions as $key => $field) {
            $visibility = $field['visibility_rule'] ?? null;

            if (($field['required_rule'] ?? null) === true && $visibility !== null) {
                throw new InvalidArgumentException("Always-required field may be hidden: {$key}.");
            }

            foreach ([$field['required_rule'] ?? null, $visibility] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                foreach ($this->ruleDependencies($rule) as $dependency) {
                    if (! isset($fieldDefinitions[$dependency])) {
                        throw new InvalidArgumentException(
                            "Unknown rule dependency in {$key}: {$dependency}.",
                        );
                    }

                    $dependencies[$key][$dependency] = true;
                }
            }
        }

        $states = [];

        foreach (array_keys($fieldDefinitions) as $key) {
            $this->assertAcyclic($key, $dependencies, $states);
        }
    }

    private function validateRule(string $fieldKey, mixed $rule, int $depth = 0): void
    {
        if ($depth > 8) {
            throw new InvalidArgumentException("Rule nesting exceeds 8 in {$fieldKey}.");
        }

        if (! is_array($rule) || array_is_list($rule)) {
            throw new InvalidArgumentException(
                "Rule for {$fieldKey} must be a declarative object.",
            );
        }

        $encodedRule = json_encode($rule, JSON_THROW_ON_ERROR);

        if (strlen($encodedRule) > 16_384) {
            throw new InvalidArgumentException("Stored rule values exceed bounds in {$fieldKey}.");
        }

        if (isset($rule['question']) || isset($rule['operator'])) {
            $operator = $rule['operator'] ?? null;

            if (! is_string($operator) || ! in_array(
                $operator,
                ['equals', 'not_equals', 'in', 'not_in', 'exists'],
                true,
            )) {
                $actual = is_string($operator) ? $operator : get_debug_type($operator);
                throw new InvalidArgumentException(
                    "Unknown rule operator in {$fieldKey}: {$actual}.",
                );
            }

            $question = $rule['question'] ?? null;

            if (! is_string($question) || $question === '') {
                throw new InvalidArgumentException(
                    "Rule question is required in {$fieldKey}.",
                );
            }

            if (mb_strlen($question) > 160) {
                throw new InvalidArgumentException("Rule question is too long in {$fieldKey}.");
            }

            $expectedKeys = match ($operator) {
                'equals', 'not_equals' => ['question', 'operator', 'value'],
                'in', 'not_in' => ['question', 'operator', 'values'],
                'exists' => ['question', 'operator'],
            };
            $actualKeys = array_keys($rule);
            sort($expectedKeys);
            sort($actualKeys);

            if ($actualKeys !== $expectedKeys) {
                throw new InvalidArgumentException("Invalid rule shape in {$fieldKey}.");
            }

            if (
                in_array($operator, ['in', 'not_in'], true)
                && (! is_array($rule['values']) || ! array_is_list($rule['values']))
            ) {
                throw new InvalidArgumentException("Rule values must be a list in {$fieldKey}.");
            }

            if (array_key_exists('value', $rule)) {
                $this->validateStoredValue($fieldKey, $rule['value']);
            }

            if (array_key_exists('values', $rule)) {
                $this->validateStoredValue($fieldKey, $rule['values']);
            }

            return;
        }

        foreach (['all', 'any'] as $composite) {
            if (array_key_exists($composite, $rule)) {
                if (
                    count($rule) !== 1
                    || ! is_array($rule[$composite])
                    || ! array_is_list($rule[$composite])
                    || $rule[$composite] === []
                    || count($rule[$composite]) > 50
                ) {
                    throw new InvalidArgumentException("Invalid {$composite} rule in {$fieldKey}.");
                }

                foreach ($rule[$composite] as $nested) {
                    $this->validateRule($fieldKey, $nested, $depth + 1);
                }

                return;
            }
        }

        if (array_key_exists('not', $rule) && count($rule) === 1) {
            $this->validateRule($fieldKey, $rule['not'], $depth + 1);

            return;
        }

        throw new InvalidArgumentException("Invalid rule shape in {$fieldKey}.");
    }

    private function validateValidationRules(string $fieldKey, mixed $rules): void
    {
        if (! is_array($rules) || ! array_is_list($rules)) {
            throw new InvalidArgumentException(
                "Validation rules for {$fieldKey} must be a list.",
            );
        }

        if (count($rules) > 50) {
            throw new InvalidArgumentException(
                "Validation rules exceed bounds for {$fieldKey}.",
            );
        }
        $this->assertEncodedJsonWithinBounds(
            $fieldKey,
            $rules,
            'Validation rules',
        );

        foreach ($rules as $rule) {
            $name = is_array($rule) ? ($rule['rule'] ?? null) : null;

            if (! is_string($name) || ! in_array($name, self::ValidationRules, true)) {
                $actual = is_string($name) ? $name : get_debug_type($name);
                throw new InvalidArgumentException(
                    "Unknown validation rule for {$fieldKey}: {$actual}.",
                );
            }

            $messageKey = $rule['messageKey'] ?? null;

            if (
                ! is_string($messageKey)
                || trim($messageKey) === ''
                || mb_strlen($messageKey) > 160
            ) {
                throw new InvalidArgumentException(
                    "Validation message key is required for {$fieldKey}.",
                );
            }

            $requiresParameters = in_array(
                $name,
                ['min_length', 'max_length', 'pattern'],
                true,
            );
            $expectedKeys = $requiresParameters
                ? ['messageKey', 'parameters', 'rule']
                : ['messageKey', 'rule'];
            $keys = array_keys($rule);
            sort($keys);

            if ($keys !== $expectedKeys) {
                throw new InvalidArgumentException(
                    "Invalid validation rule shape for {$fieldKey}.",
                );
            }

            if (! $requiresParameters) {
                continue;
            }

            $parameters = $rule['parameters'];

            if (
                ! is_array($parameters)
                || array_keys($parameters) !== ['value']
            ) {
                throw new InvalidArgumentException(
                    "Invalid validation parameters for {$fieldKey}: {$name}.",
                );
            }

            $value = $parameters['value'];

            if (in_array($name, ['min_length', 'max_length'], true)) {
                if (
                    ! is_int($value)
                    || $value < 1
                    || $value > 10_000
                ) {
                    throw new InvalidArgumentException(
                        "Invalid validation parameters for {$fieldKey}: {$name}.",
                    );
                }

                continue;
            }

            if (
                ! is_string($value)
                || trim($value) === ''
                || mb_strlen($value) > 500
                || ! mb_check_encoding($value, 'UTF-8')
                || preg_match('/[\x00-\x1F\x7F]/u', $value) !== 0
            ) {
                throw new InvalidArgumentException(
                    "Invalid validation parameters for {$fieldKey}: {$name}.",
                );
            }
        }
    }

    private function validateStoredValue(string $fieldKey, mixed $value, int $depth = 0): void
    {
        if ($depth > 8) {
            throw new InvalidArgumentException("Stored rule values exceed bounds in {$fieldKey}.");
        }

        if (is_string($value) && mb_strlen($value) > 2_000) {
            throw new InvalidArgumentException("Stored rule values exceed bounds in {$fieldKey}.");
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException("Stored rule values must be finite in {$fieldKey}.");
        }

        if (
            $value === null
            || is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
        ) {
            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("Stored rule values must use JSON types in {$fieldKey}.");
        }

        if (count($value) > 100) {
            throw new InvalidArgumentException("Stored rule values exceed bounds in {$fieldKey}.");
        }

        foreach ($value as $nested) {
            $this->validateStoredValue($fieldKey, $nested, $depth + 1);
        }
    }

    private function assertEncodedJsonWithinBounds(
        string $fieldKey,
        mixed $value,
        string $label,
    ): void {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException(
                "{$label} must contain valid JSON values for {$fieldKey}.",
            );
        }

        if (strlen($encoded) > 16_384) {
            throw new InvalidArgumentException(
                "{$label} exceeds bounds for {$fieldKey}.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function ruleDependencies(array $rule): array
    {
        if (isset($rule['question']) && is_string($rule['question'])) {
            return [$rule['question']];
        }

        if (isset($rule['all']) && is_array($rule['all'])) {
            return array_values(array_unique(array_merge(
                ...array_map(fn (array $nested): array => $this->ruleDependencies($nested), $rule['all']),
            )));
        }

        if (isset($rule['any']) && is_array($rule['any'])) {
            return array_values(array_unique(array_merge(
                ...array_map(fn (array $nested): array => $this->ruleDependencies($nested), $rule['any']),
            )));
        }

        if (isset($rule['not']) && is_array($rule['not'])) {
            return $this->ruleDependencies($rule['not']);
        }

        return [];
    }

    /**
     * @param  array<string, array<string, true>>  $dependencies
     * @param  array<string, int>  $states
     */
    private function assertAcyclic(string $key, array $dependencies, array &$states): void
    {
        if (($states[$key] ?? 0) === 2) {
            return;
        }

        if (($states[$key] ?? 0) === 1) {
            throw new InvalidArgumentException("Rule dependency cycle includes {$key}.");
        }

        $states[$key] = 1;

        foreach (array_keys($dependencies[$key]) as $dependency) {
            $this->assertAcyclic($dependency, $dependencies, $states);
        }

        $states[$key] = 2;
    }

    private function validateOptions(string $fieldKey, mixed $options): void
    {
        if (! is_array($options) || ! array_is_list($options)) {
            throw new InvalidArgumentException("Options for {$fieldKey} must be a list.");
        }

        $keys = [];
        $values = [];
        $orders = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                throw new InvalidArgumentException("Options for {$fieldKey} must be objects.");
            }

            $key = $option['semantic_key'] ?? null;
            $value = $option['value'] ?? null;
            $label = $option['label_th'] ?? null;
            $order = $option['display_order'] ?? null;

            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException("Option semantic key is required in {$fieldKey}.");
            }

            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException("Option value is required in {$fieldKey}.");
            }

            if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 500) {
                throw new InvalidArgumentException(
                    "Valid Thai option label is required in {$fieldKey}.",
                );
            }

            if (! is_int($order) || $order < 1) {
                throw new InvalidArgumentException("Invalid option display order in {$fieldKey}.");
            }

            if (isset($keys[$key])) {
                throw new InvalidArgumentException(
                    "Duplicate option semantic key in {$fieldKey}: {$key}.",
                );
            }

            if (isset($values[$value])) {
                throw new InvalidArgumentException("Duplicate option value in {$fieldKey}: {$value}.");
            }

            if (isset($orders[$order])) {
                throw new InvalidArgumentException(
                    "Duplicate option display order in {$fieldKey}: {$order}.",
                );
            }

            $keys[$key] = true;
            $values[$value] = true;
            $orders[$order] = true;
        }
    }
}
