<?php

namespace Tests\Unit\FormEngine;

use App\Modules\FormEngine\Application\FormPublicationValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormPublicationValidatorTest extends TestCase
{
    public function test_rejects_duplicate_field_semantic_keys(): void
    {
        $sections = $this->validSections();
        $sections[1]['fields'][0]['semantic_key'] = 'profile.phone';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate field semantic key: profile.phone.');

        (new FormPublicationValidator)->validate($sections);
    }

    public function test_rejects_duplicate_section_field_and_option_order_keys(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $sections[1]['display_order'] = 1;
        $this->assertInvalid($validator, $sections, 'Duplicate section display order: 1.');

        $sections = $this->validSections();
        $sections[1]['fields'][1]['display_order'] = 1;
        $this->assertInvalid($validator, $sections, 'Duplicate field display order in preferences: 1.');

        $sections = $this->validSections();
        $sections[1]['fields'][0]['options'] = [
            ['semantic_key' => 'yes', 'value' => 'yes', 'label_th' => 'ใช่', 'display_order' => 1],
            ['semantic_key' => 'no', 'value' => 'no', 'label_th' => 'ไม่ใช่', 'display_order' => 1],
        ];
        $this->assertInvalid(
            $validator,
            $sections,
            'Duplicate option display order in preferences.needs_dinner: 1.',
        );
    }

    public function test_rejects_blank_thai_labels_unknown_types_and_missing_hidden_policy(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $sections[0]['title_th'] = ' ';
        $this->assertInvalid($validator, $sections, 'Thai section title is required for profile.');

        $sections = $this->validSections();
        $sections[0]['fields'][0]['label_th'] = '';
        $this->assertInvalid($validator, $sections, 'Thai field label is required for profile.phone.');

        $sections = $this->validSections();
        $sections[0]['fields'][0]['field_type'] = 'raw_html';
        $this->assertInvalid($validator, $sections, 'Unknown field type for profile.phone: raw_html.');

        $sections = $this->validSections();
        unset($sections[0]['fields'][0]['hidden_answer_policy']);
        $this->assertInvalid(
            $validator,
            $sections,
            'Hidden-answer policy is required for profile.phone.',
        );
    }

    public function test_rejects_unknown_or_executable_rule_languages(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $sections[1]['fields'][1]['visibility_rule']['operator'] = 'contains_sql';
        $this->assertInvalid(
            $validator,
            $sections,
            'Unknown rule operator in preferences.dinner_reason: contains_sql.',
        );

        $sections = $this->validSections();
        $sections[1]['fields'][1]['visibility_rule'] = 'SELECT true';
        $this->assertInvalid(
            $validator,
            $sections,
            'Rule for preferences.dinner_reason must be a declarative object.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['validation_rules'] = [
            ['rule' => 'eval', 'messageKey' => 'validation.eval'],
        ];
        $this->assertInvalid(
            $validator,
            $sections,
            'Unknown validation rule for profile.phone: eval.',
        );
    }

    public function test_rejects_broken_dependencies_cycles_and_required_while_hidden(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $sections[1]['fields'][1]['visibility_rule']['question'] = 'missing.field';
        $this->assertInvalid(
            $validator,
            $sections,
            'Unknown rule dependency in preferences.dinner_reason: missing.field.',
        );

        $sections = $this->validSections();
        $sections[1]['fields'][0]['required_rule'] = false;
        $sections[1]['fields'][0]['visibility_rule'] = [
            'question' => 'preferences.dinner_reason',
            'operator' => 'exists',
        ];
        $this->assertInvalid(
            $validator,
            $sections,
            'Rule dependency cycle includes preferences.needs_dinner.',
        );

        $sections = $this->validSections();
        $sections[1]['fields'][1]['required_rule'] = true;
        $this->assertInvalid(
            $validator,
            $sections,
            'Always-required field may be hidden: preferences.dinner_reason.',
        );
    }

    public function test_bounds_rule_depth_and_published_field_sizes(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $rule = [
            'question' => 'preferences.needs_dinner',
            'operator' => 'exists',
        ];
        for ($depth = 0; $depth < 9; $depth++) {
            $rule = ['not' => $rule];
        }
        $sections[1]['fields'][1]['visibility_rule'] = $rule;
        $this->assertInvalid(
            $validator,
            $sections,
            'Rule nesting exceeds 8 in preferences.dinner_reason.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['label_th'] = str_repeat('ก', 501);
        $this->assertInvalid(
            $validator,
            $sections,
            'Thai field label exceeds 500 characters for profile.phone.',
        );
    }

    public function test_rejects_unbounded_or_noncanonical_validation_rule_records(): void
    {
        $validator = new FormPublicationValidator;

        $sections = $this->validSections();
        $sections[0]['fields'][0]['validation_rules'] = [['rule' => 'phone']];
        $this->assertInvalid(
            $validator,
            $sections,
            'Validation message key is required for profile.phone.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['validation_rules'] = [[
            'rule' => 'phone',
            'messageKey' => 'validation.phone',
            'php' => 'run',
        ]];
        $this->assertInvalid(
            $validator,
            $sections,
            'Invalid validation rule shape for profile.phone.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['validation_rules'] = [[
            'rule' => 'max_length',
            'messageKey' => 'validation.max_length',
            'parameters' => ['value' => ['nested' => true]],
        ]];
        $this->assertInvalid(
            $validator,
            $sections,
            'Invalid validation parameters for profile.phone: max_length.',
        );

        $sections = $this->validSections();
        $sections[1]['fields'][1]['visibility_rule']['value'] = str_repeat('x', 20_000);
        $this->assertInvalid(
            $validator,
            $sections,
            'Stored rule values exceed bounds in preferences.dinner_reason.',
        );
    }

    public function test_accepts_each_canonical_validation_rule_shape(): void
    {
        $rules = [
            ['rule' => 'required', 'messageKey' => 'validation.required'],
            ['rule' => 'phone', 'messageKey' => 'validation.phone'],
            ['rule' => 'row_complete', 'messageKey' => 'validation.row_complete'],
            [
                'rule' => 'min_length',
                'messageKey' => 'validation.min_length',
                'parameters' => ['value' => 1],
            ],
            [
                'rule' => 'max_length',
                'messageKey' => 'validation.max_length',
                'parameters' => ['value' => 10_000],
            ],
            [
                'rule' => 'pattern',
                'messageKey' => 'validation.pattern',
                'parameters' => ['value' => '^[ก-๙]+$'],
            ],
        ];

        foreach ($rules as $rule) {
            $sections = $this->validSections();
            $sections[0]['fields'][0]['validation_rules'] = [$rule];
            (new FormPublicationValidator)->validate($sections);
        }

        self::assertCount(6, $rules);
    }

    public function test_rejects_rule_specific_parameter_shape_and_bounds(): void
    {
        $invalidRules = [
            ['rule' => 'required', 'messageKey' => 'validation.required', 'parameters' => []],
            ['rule' => 'max_length', 'messageKey' => 'validation.max_length'],
            [
                'rule' => 'min_length',
                'messageKey' => 'validation.min_length',
                'parameters' => ['value' => 0],
            ],
            [
                'rule' => 'max_length',
                'messageKey' => 'validation.max_length',
                'parameters' => ['value' => 10_001],
            ],
            [
                'rule' => 'max_length',
                'messageKey' => 'validation.max_length',
                'parameters' => ['value' => 5.0],
            ],
            [
                'rule' => 'max_length',
                'messageKey' => 'validation.max_length',
                'parameters' => ['value' => INF],
            ],
            [
                'rule' => 'pattern',
                'messageKey' => 'validation.pattern',
                'parameters' => ['value' => ''],
            ],
            [
                'rule' => 'pattern',
                'messageKey' => 'validation.pattern',
                'parameters' => ['value' => "abc\n"],
            ],
            [
                'rule' => 'pattern',
                'messageKey' => 'validation.pattern',
                'parameters' => ['value' => 'abc', 'flags' => 'i'],
            ],
        ];

        foreach ($invalidRules as $rule) {
            $sections = $this->validSections();
            $sections[0]['fields'][0]['validation_rules'] = [$rule];

            try {
                (new FormPublicationValidator)->validate($sections);
                self::fail('Expected publication validation to fail.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        self::assertCount(9, $invalidRules);
    }

    public function test_rejects_oversized_validation_rules_and_invalid_initial_values(): void
    {
        $validator = new FormPublicationValidator;
        $sections = $this->validSections();
        $sections[0]['fields'][0]['validation_rules'] = array_fill(
            0,
            51,
            ['rule' => 'required', 'messageKey' => 'validation.required'],
        );
        $this->assertInvalid(
            $validator,
            $sections,
            'Validation rules exceed bounds for profile.phone.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['initial_value'] = INF;
        $this->assertInvalid(
            $validator,
            $sections,
            'Stored rule values must be finite in profile.phone.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['initial_value'] = str_repeat('x', 16_385);
        $this->assertInvalid(
            $validator,
            $sections,
            'Stored rule values exceed bounds in profile.phone.',
        );

        $sections = $this->validSections();
        $sections[0]['fields'][0]['initial_value'] = array_fill(
            0,
            20,
            str_repeat('x', 1_000),
        );
        $this->assertInvalid(
            $validator,
            $sections,
            'Initial value exceeds bounds for profile.phone.',
        );

        $sections = $this->validSections();
        $deepValue = true;
        for ($depth = 0; $depth < 10; $depth++) {
            $deepValue = ['nested' => $deepValue];
        }
        $sections[0]['fields'][0]['initial_value'] = $deepValue;
        $this->assertInvalid(
            $validator,
            $sections,
            'Stored rule values exceed bounds in profile.phone.',
        );
    }

    public function test_allows_bounded_structured_unknown_rule_operands(): void
    {
        $sections = $this->validSections();
        $sections[1]['fields'][1]['visibility_rule']['value'] = [
            'code' => 'yes',
            'metadata' => ['fixture' => true],
        ];

        (new FormPublicationValidator)->validate($sections);

        self::assertTrue(true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validSections(): array
    {
        return [
            [
                'semantic_key' => 'profile',
                'title_th' => 'ข้อมูลผู้สมัคร',
                'display_order' => 1,
                'fields' => [
                    [
                        'semantic_key' => 'profile.phone',
                        'field_type' => 'phone',
                        'label_th' => 'หมายเลขโทรศัพท์',
                        'display_order' => 1,
                        'required_rule' => true,
                        'validation_rules' => [],
                        'visibility_rule' => null,
                        'hidden_answer_policy' => 'retain',
                    ],
                ],
            ],
            [
                'semantic_key' => 'preferences',
                'title_th' => 'ความต้องการ',
                'display_order' => 2,
                'fields' => [
                    [
                        'semantic_key' => 'preferences.needs_dinner',
                        'field_type' => 'single_choice',
                        'label_th' => 'ต้องการอาหารเย็นหรือไม่',
                        'display_order' => 1,
                        'required_rule' => true,
                        'validation_rules' => [],
                        'visibility_rule' => null,
                        'hidden_answer_policy' => 'retain',
                    ],
                    [
                        'semantic_key' => 'preferences.dinner_reason',
                        'field_type' => 'long_text',
                        'label_th' => 'เหตุผล',
                        'display_order' => 2,
                        'required_rule' => false,
                        'validation_rules' => [],
                        'visibility_rule' => [
                            'question' => 'preferences.needs_dinner',
                            'operator' => 'equals',
                            'value' => 'yes',
                        ],
                        'hidden_answer_policy' => 'clear',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function assertInvalid(
        FormPublicationValidator $validator,
        array $sections,
        string $message,
    ): void {
        try {
            $validator->validate($sections);
            self::fail('Expected publication validation to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }
}
