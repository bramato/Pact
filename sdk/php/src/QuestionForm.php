<?php

declare(strict_types=1);

namespace Pact;

use Opis\JsonSchema\Validator;
use stdClass;

/** Compile a structurally validated form into its answer constraints. */
final class QuestionForm
{
    public static function schema(stdClass $form): stdClass
    {
        $properties = new stdClass;
        $required = [];
        foreach ($form->fields as $field) {
            Pact::ensure(!property_exists($properties, $field->key), 'QUESTION_FORM_INVALID', 'Duplicate field key');
            $list = str_ends_with($field->type, '[]');
            $item = (object) ['type' => $list ? substr($field->type, 0, -2) : $field->type];
            if (isset($field->options)) {
                $values = array_column($field->options, 'value');
                Pact::ensure($item->type === 'string' && count(array_unique($values)) === count($values), 'QUESTION_FORM_INVALID', 'Invalid choices');
                $item->enum = $values;
            }
            $rule = $list ? (object) ['type' => 'array', 'items' => $item] : $item;
            if ($field->required) {
                $required[] = $field->key;
                if ($list) {
                    $rule->minItems = 1;
                } elseif ($field->type === 'string') {
                    $rule->minLength = 1;
                }
            }
            $properties->{$field->key} = $rule;
        }

        return (object) ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false, 'required' => $required];
    }

    public static function validateAnswer(stdClass $form, mixed $value): void
    {
        Pact::ensure((new Validator)->validate($value, self::schema($form))->isValid(), 'QUESTION_ANSWER_INVALID', 'Invalid form answer');
    }
}
