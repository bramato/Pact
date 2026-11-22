# Question forms v1

SDK release 0.3.0 extends Core questions with an optional inline `form`. Its version is 1. A form-capable coordinator renders the prompt as text and the fields in order, using each label for display and its key for the answer object. Laravel discovery advertises supported form versions through `question_form_versions`. An omitted list indicates a legacy peer. Deploy a form-capable receiver before enabling form questions on the sender. Receivers that cannot represent a form must report an unsupported question; never ask a person to serialize it.

Each field has a unique key, a label, a required flag and a type (string, number, boolean, object, string[] or number[]). Strings and lists of strings may include options with unique string values and human-readable labels. Render one choice or multiple choices respectively. Preserve numeric and boolean types, including zero and false. Render objects as editable structured data. Omit unanswered optional fields. Present validation errors in the form.

The sender keeps the descriptor immutable for the lifetime of the question. The coordinator and agent validate the answer against both the form and the pinned answer schema. Submit one object through the existing correlated answer message. Existing expiry, authorization and idempotency rules apply.
