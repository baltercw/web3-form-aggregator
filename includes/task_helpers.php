<?php

declare(strict_types=1);

function task_taipei_tz(): DateTimeZone
{
    return new DateTimeZone('Asia/Taipei');
}

function task_now_taipei(): DateTimeImmutable
{
    return new DateTimeImmutable('now', task_taipei_tz());
}

function task_parse_db_datetime(string $s): ?DateTimeImmutable
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s, task_taipei_tz());
    if ($dt !== false) {
        return $dt;
    }
    $dt2 = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $s), task_taipei_tz());

    return $dt2 !== false ? $dt2 : null;
}

function task_format_taipei_display(?string $dbDatetime): string
{
    if ($dbDatetime === null || $dbDatetime === '') {
        return '—';
    }
    $dt = task_parse_db_datetime($dbDatetime);

    return $dt ? $dt->format('Y/m/d H:i') : $dbDatetime;
}

function task_datetime_local_value(?string $dbDatetime): string
{
    if ($dbDatetime === null || $dbDatetime === '') {
        return '';
    }
    $dt = task_parse_db_datetime($dbDatetime);

    return $dt ? $dt->format('Y-m-d\TH:i') : '';
}

/** 表單 datetime-local 視為台灣時間，回傳 MySQL DATETIME 字串。 */
function task_parse_datetime_local_input(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw, task_taipei_tz());
    if ($dt === false) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $raw, task_taipei_tz());
    }

    return $dt !== false ? $dt->format('Y-m-d H:i:s') : null;
}

/**
 * @param array<string, mixed> $task
 * @return array{can_submit: bool, block_codes: list<string>, block_labels: list<string>, spots_left: ?int, approved_count: int}
 */
function task_submission_gate(array $task, int $approvedCount, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? task_now_taipei();
    $codes = [];
    $labels = [];

    $status = (string) ($task['task_status'] ?? 'published');
    if ($status === 'ended') {
        $codes[] = 'ended';
        $labels[] = '主辦方已結束投稿';
    }

    $starts = isset($task['starts_at']) ? task_parse_db_datetime((string) $task['starts_at']) : null;
    $ends = isset($task['ends_at']) ? task_parse_db_datetime((string) $task['ends_at']) : null;
    if ($starts && $now < $starts) {
        $codes[] = 'not_started';
        $labels[] = '尚未開放提交';
    }
    if ($ends && $now > $ends) {
        $codes[] = 'deadline';
        $labels[] = '已超過截止時間';
    }

    $maxRaw = $task['max_completions'] ?? null;
    if ($maxRaw !== null && $maxRaw !== '' && (int) $maxRaw > 0 && $approvedCount >= (int) $maxRaw) {
        $codes[] = 'quota';
        $labels[] = '核准名額已滿';
    }

    $spots = null;
    if ($maxRaw !== null && $maxRaw !== '' && (int) $maxRaw > 0) {
        $spots = max(0, (int) $maxRaw - $approvedCount);
    }

    return [
        'can_submit' => $codes === [],
        'block_codes' => $codes,
        'block_labels' => $labels,
        'spots_left' => $spots,
        'approved_count' => $approvedCount,
    ];
}

/**
 * 根據任務的 form_schema 推斷 web3 相關需求（目前專案沒有獨立 DB 欄位，因此以欄位 key 進行啟發式判斷）。
 * 只用來做 UI 標籤，不影響後端審核流程。
 *
 * @param array<int, array<string, mixed>> $schemaFields
 * @return array{wallet_input: bool, onchain: bool, kyc: bool}
 */
function task_web3_flags_from_schema(array $schemaFields): array
{
    $keys = [];
    foreach ($schemaFields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $k = isset($field['key']) ? (string) $field['key'] : '';
        $k = trim($k);
        if ($k !== '') {
            $keys[] = $k;
        }
    }

    $lower = array_map('strtolower', $keys);

    $walletInput = false;
    foreach ($lower as $k) {
        if (
            $k === 'wallet' ||
            $k === 'wallet_address' ||
            $k === 'address' ||
            strpos($k, 'wallet') !== false ||
            strpos($k, 'addr') !== false
        ) {
            $walletInput = true;
            break;
        }
    }

    $onchain = false;
    foreach ($lower as $k) {
        // 常見鏈上資料/簽章/交易雜湊欄位 key（用啟發式判斷）
        if (
            strpos($k, 'tx') !== false ||
            strpos($k, 'transaction') !== false ||
            strpos($k, 'hash') !== false ||
            strpos($k, 'signature') !== false ||
            strpos($k, 'sign') !== false ||
            strpos($k, 'nonce') !== false ||
            strpos($k, 'message') !== false ||
            strpos($k, 'proof') !== false ||
            strpos($k, 'onchain') !== false
        ) {
            $onchain = true;
            break;
        }
    }

    $kyc = false;
    foreach ($lower as $k) {
        if (
            strpos($k, 'kyc') !== false ||
            strpos($k, 'passport') !== false ||
            strpos($k, 'document') !== false ||
            strpos($k, 'selfie') !== false ||
            strpos($k, 'verification') !== false ||
            // 避免把一般 id 當作 KYC；但若 schema 真的用 id_doc 這類 key，仍能捕捉
            (strpos($k, 'id') !== false && (strpos($k, 'doc') !== false || strpos($k, 'card') !== false || strpos($k, 'verify') !== false))
        ) {
            $kyc = true;
            break;
        }
    }

    return [
        'wallet_input' => $walletInput,
        'onchain' => $onchain,
        'kyc' => $kyc,
    ];
}

/**
 * @param array<string, mixed> $task
 * @return array{wallet_input: bool, onchain: bool, kyc: bool}
 */
function task_web3_flags_from_task(array $task): array
{
    $raw = $task['form_schema'] ?? null;
    if (!is_string($raw) || trim($raw) === '') {
        return ['wallet_input' => false, 'onchain' => false, 'kyc' => false];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['wallet_input' => false, 'onchain' => false, 'kyc' => false];
    }

    // 這裡不強依賴 required 欄位的有無，只要 key 可用即可。
    return task_web3_flags_from_schema($decoded);
}
