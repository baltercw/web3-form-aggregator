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
