<?php

declare(strict_types=1);

/**
 * @return list<array{key: string, label: string, type: string, required: bool}>
 */
function form_schema_normalize_list(array $raw): array
{
    $out = [];
    foreach ($raw as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = trim((string) ($field['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '') {
            $label = $key;
        }
        $type = trim((string) ($field['type'] ?? 'text'));
        $allowed = ['text', 'url', 'textarea', 'email', 'checkbox'];
        if (!in_array($type, $allowed, true)) {
            $type = 'text';
        }
        $reqRaw = $field['required'] ?? null;
        $required = true;
        if ($reqRaw === false || $reqRaw === 0 || $reqRaw === '0' || $reqRaw === '') {
            $required = false;
        }
        $out[] = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
        ];
    }

    return $out;
}

/**
 * @param list<array{key: string, label: string, type: string, required: bool}> $schema
 * @param array<string, mixed> $responsePost 通常為 $_POST['response'] ?? []
 * @return array{0: bool, 1: string, 2: array<string, string>}
 */
function form_schema_collect_responses(array $schema, array $responsePost): array
{
    $responses = [];
    foreach ($schema as $field) {
        $k = $field['key'];
        $label = $field['label'];
        $type = $field['type'];
        $req = $field['required'];

        if ($type === 'checkbox') {
            $raw = $responsePost[$k] ?? null;
            $checked = $raw === '1' || $raw === 1 || $raw === true || $raw === 'on';
            if ($req && !$checked) {
                return [false, '請勾選：' . $label, []];
            }
            $responses[$k] = $checked ? '1' : '';
            continue;
        }

        $val = trim((string) ($responsePost[$k] ?? ''));
        if ($req && $val === '') {
            return [false, '請填寫：' . $label, []];
        }
        if ($val !== '' && $type === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Email 格式不正確：' . $label, []];
        }
        if ($val !== '' && $type === 'url' && !filter_var($val, FILTER_VALIDATE_URL)) {
            return [false, '網址格式不正確：' . $label, []];
        }
        $responses[$k] = $val;
    }

    return [true, '', $responses];
}
