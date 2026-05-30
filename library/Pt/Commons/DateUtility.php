<?php

final class Pt_Commons_DateUtility
{
    // MySQL "zero date" sentinel — treated as no value throughout.
    private const EMPTY_DATE = '0000-00-00';

    public static function isDateFormatValid($date, string $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim((string) $date);

        if ($date === '' || $date === '0' || $date === 'undefined' || $date === 'null') {
            return false;
        }

        $parsed = self::parseDate($date, [$format], false);
        if (!$parsed) {
            return false;
        }

        return !$strict || $parsed->format($format) === $date;
    }

    public static function getDateTime(?string $date, string $format = 'Y-m-d H:i:s', ?string $inputFormat = null): ?string
    {
        if ($date === null) {
            return null;
        }

        $trimmedDate = trim($date);
        $normalizedDate = self::normalizeDateString($trimmedDate);

        if (
            ($inputFormat !== null && $inputFormat !== '' && $inputFormat !== '0' && self::isDateFormatValid($normalizedDate, $inputFormat, true) === false)
            || (($inputFormat === null || $inputFormat === '' || $inputFormat === '0') && self::isDateValid($normalizedDate) === false)
        ) {
            return null;
        }

        try {
            $dateTime = null;

            if ($inputFormat === null && ctype_digit($normalizedDate) && strlen($normalizedDate) >= 10) {
                $timestamp = (int) $normalizedDate;
                $dateTime = (new DateTimeImmutable())->setTimestamp($timestamp);
            } elseif ($inputFormat !== null) {
                $dateTime = DateTimeImmutable::createFromFormat($inputFormat, $normalizedDate);
            } else {
                $dateTime = new DateTimeImmutable($normalizedDate);
            }

            if ($dateTime instanceof DateTimeImmutable) {
                return $dateTime->format($format);
            }
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logError('DateUtility::getDateTime error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }

        return null;
    }

    public static function getCurrentTimestamp(): int
    {
        return (new DateTimeImmutable())->getTimestamp();
    }

    public static function daysAgo(int $days, string $format = 'Y-m-d'): string
    {
        return (new DateTimeImmutable())->modify("-{$days} days")->format($format);
    }

    public static function isDateValid(mixed $date): bool
    {
        $date = trim((string) $date);

        if (
            $date === '' || $date === '0'
            || in_array($date, ['undefined', 'null', ''], true)
            || preg_match('/[_*]|--/', $date)
        ) {
            return false;
        }

        if (ctype_digit($date)) {
            if (strlen($date) >= 10) {
                $timestamp = (int) $date;
                return $timestamp >= 0 && $timestamp <= 4102444800;
            }
            return false;
        }

        $parsed = self::parseDate($date, null, true);
        if (!$parsed instanceof DateTimeImmutable) {
            return false;
        }
        // Reject year 0 dates (e.g., 0000-11-30 which formats as -0001)
        $year = (int) $parsed->format('Y');
        return $year >= 1000 && $year <= 9999;
    }

    public static function humanReadableDateFormat($date, $includeTime = false, ?string $format = null, $withSeconds = false): mixed
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format ??= $_SESSION['phpDateFormat'] ?? 'd-M-Y';
        $hasTimeComponent = preg_match('/[HhGgis]/', (string) $format);
        if ($includeTime && !$hasTimeComponent) {
            $format .= $withSeconds ? ' H:i:s' : ' H:i';
        }

        try {
            return (new DateTimeImmutable(self::normalizeDateString((string) $date)))->format($format);
        } catch (Throwable) {
            return null;
        }
    }

    public static function getCurrentDateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable())->format($format);
    }

    public static function isoDateFormat($date, $includeTime = false): mixed
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format = $includeTime ? 'Y-m-d H:i:s' : 'Y-m-d';
        return (new DateTimeImmutable(self::normalizeDateString((string) $date)))->format($format);
    }

    public static function ageInYearMonthDays($dateOfBirth): mixed
    {
        if (!self::isDateValid($dateOfBirth)) {
            return null;
        }

        $dob = new DateTimeImmutable(self::normalizeDateString((string) $dateOfBirth));
        $diff = $dob->diff(new DateTimeImmutable());
        return [
            'year' => $diff->y,
            'months' => $diff->m,
            'days' => $diff->d,
        ];
    }

    public static function dateDiff($dateString1, $dateString2, $format = null): ?string
    {
        if (!self::isDateValid($dateString1) || !self::isDateValid($dateString2)) {
            return null;
        }

        $d1 = new DateTimeImmutable(self::normalizeDateString((string) $dateString1));
        $d2 = new DateTimeImmutable(self::normalizeDateString((string) $dateString2));
        $interval = $d1->diff($d2);
        return $format === null ? $interval->format('%a days') : $interval->format($format);
    }

    public static function hasFutureDates($dates, ?array $formats = null): bool
    {
        $now = new DateTimeImmutable();
        $dates = is_array($dates) ? $dates : [$dates];

        foreach ($dates as $dateStr) {
            if (!empty($dateStr)) {
                $date = self::parseDate((string) $dateStr, $formats, true);
                if ($date && $date > $now) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function parseDate(string $dateStr, ?array $formats = null, $ignoreTime = false): ?DateTimeImmutable
    {
        $dateStr = self::normalizeDateString($dateStr);

        if ($ignoreTime === true) {
            $dateStr = explode(' ', $dateStr)[0];
        }

        if ($formats) {
            foreach ($formats as $format) {
                $dt = DateTimeImmutable::createFromFormat($format, $dateStr);
                if ($dt instanceof DateTimeImmutable) {
                    $errors = DateTimeImmutable::getLastErrors();
                    if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                        return $dt;
                    }
                }
            }
        }

        try {
            return new DateTimeImmutable($dateStr);
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logError("Invalid or unparseable date $dateStr : " . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }

    private static function normalizeDateString(string $dateStr): string
    {
        $replacements = [
            '/\bjanv\.?\b/iu' => 'jan',
            '/\bfévr\.?\b/iu' => 'feb',
            '/\bfevr\.?\b/iu' => 'feb',
            '/\bmars\b/iu' => 'mar',
            '/\bavr\.?\b/iu' => 'apr',
            '/\bmai\b/iu' => 'may',
            '/\bjuin\b/iu' => 'jun',
            '/\bjuil\.?\b/iu' => 'jul',
            '/\baoût\b/iu' => 'aug',
            '/\baout\b/iu' => 'aug',
            '/\bsept\.?\b/iu' => 'sep',
            '/\boct\.?\b/iu' => 'oct',
            '/\bnov\.?\b/iu' => 'nov',
            '/\bdéc\.?\b/iu' => 'dec',
            '/\bdec\.?\b/iu' => 'dec',
        ];

        $normalized = $dateStr;
        foreach ($replacements as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        return $normalized;
    }

    public static function isDateGreaterThan(?string $inputDate, ?string $comparisonDate): bool
    {
        try {
            $parsedInputDate = $inputDate ? new DateTimeImmutable(self::normalizeDateString($inputDate)) : null;
            $parsedComparisonDate = $comparisonDate ? new DateTimeImmutable(self::normalizeDateString($comparisonDate)) : null;

            if (!$parsedInputDate || !$parsedComparisonDate) {
                return false;
            }

            return $parsedInputDate > $parsedComparisonDate;
        } catch (Throwable) {
            return false;
        }
    }

    public static function compareDateWithInterval(string $datetime, string $operator, string $interval): bool
    {
        $base = new DateTimeImmutable(self::normalizeDateString($datetime));
        $isNegative = str_starts_with($interval, '-');
        $intervalValue = ltrim($interval, '-');
        $dateInterval = DateInterval::createFromDateString($intervalValue);
        $modified = $isNegative ? $base->sub($dateInterval) : $base->add($dateInterval);

        return match ($operator) {
            '>' => $base > $modified,
            '<' => $base < $modified,
            default => throw new Exception("Invalid comparison operator: $operator. Use '>' or '<'."),
        };
    }

    public static function convertDateRange(?string $dateRange, $seperator = 'to', bool $includeTime = false): array
    {
        if ($dateRange === null || $dateRange === '' || $dateRange === '0') {
            return ['', ''];
        }

        $dates = explode($seperator, $dateRange ?? '');
        $dates = array_map('trim', $dates);

        $startDate = '';
        $endDate = '';

        if (!empty($dates[0])) {
            try {
                $start = new DateTimeImmutable(self::normalizeDateString($dates[0]));
                if ($includeTime) {
                    $startDate = preg_match('/\d{2}:\d{2}/', $dates[0])
                        ? $start->format('Y-m-d H:i:s')
                        : $start->setTime(0, 0, 0)->format('Y-m-d H:i:s');
                } else {
                    $startDate = $start->format('Y-m-d');
                }
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logError('Failed to parse start date: ' . $dates[0] . ' - ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if (!empty($dates[1])) {
            try {
                $end = new DateTimeImmutable(self::normalizeDateString($dates[1]));
                if ($includeTime) {
                    $endDate = preg_match('/\d{2}:\d{2}/', $dates[1])
                        ? $end->format('Y-m-d H:i:s')
                        : $end->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                } else {
                    $endDate = $end->format('Y-m-d');
                }
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logError('Failed to parse end date: ' . $dates[1] . ' - ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return [$startDate, $endDate];
    }

    public static function endOfDay(?string $date): ?DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }
        return (new DateTimeImmutable($date))->setTime(23, 59, 59);
    }

    // Resolve the cutoff timezone for shipment due dates.
    // Reads `cutoff_timezone` from global_config; falls back to the PHP default
    // (application.ini `timezone`). International programs can set this to
    // `Etc/GMT+12` (Anywhere on Earth) so participants in any TZ get the full day.
    private static function cutoffTimezone(): DateTimeZone
    {
        $tz = null;
        try {
            $tz = Pt_Commons_General::getConfig('cutoff_timezone');
        } catch (Throwable $e) {
            // global_config table not reachable (CLI bootstrap edge cases); fall through.
        }
        if (empty($tz)) {
            $tz = date_default_timezone_get();
        }
        try {
            return new DateTimeZone($tz);
        } catch (Throwable $e) {
            return new DateTimeZone(date_default_timezone_get());
        }
    }

    // Returns the cutoff moment for a shipment due date as an absolute instant.
    //
    // `response_deadline` is a DATETIME: its time component is the exact close
    // time (e.g. "2026-05-29 23:00:00"). A bare date with no time component
    // defaults to 23:59:59 so the whole due-date day stays open (this also covers
    // any legacy date-only value). The wall-clock value is interpreted in the
    // program's cutoff timezone (global_config.cutoff_timezone, falling back to
    // the server default), then converted to UTC for unambiguous comparison with
    // response timestamps.
    public static function shipmentCutoff(?string $dueDate): ?DateTimeImmutable
    {
        $value = trim((string) ($dueDate ?? ''));
        if (!self::isDateValid($value)) {
            return null;
        }

        // Split into date + optional time; a date-only value closes end-of-day.
        $parts = explode(' ', $value, 2);
        $dateOnly = $parts[0];
        $time = (isset($parts[1]) && trim($parts[1]) !== '') ? trim($parts[1]) : '23:59:59';

        $tz = self::cutoffTimezone();
        try {
            return (new DateTimeImmutable($dateOnly . ' ' . $time, $tz))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return null;
        }
    }

    // Builds the DATETIME value to store in shipment.response_deadline from the
    // add/edit form. The deadline arrives from a single datetime picker as one
    // value (e.g. "29-May-2026 23:00"); when its time component is present it is
    // used verbatim, otherwise the deadline defaults to 23:59:59 (end of day). A
    // separate $timeInput is still honoured for backward compatibility. Returns
    // "Y-m-d H:i:s", or null when the date itself is empty/invalid.
    public static function shipmentDeadlineValue($dateInput, $timeInput = null): ?string
    {
        $raw = trim((string) ($dateInput ?? ''));
        $hasExplicitTime = !empty($timeInput) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', trim((string) $timeInput), $m);

        // Single datetime-picker value carrying its own time — keep full date+time.
        if (!$hasExplicitTime && preg_match('/\d{1,2}:\d{2}/', $raw)) {
            return self::isoDateFormat($raw, true) ?: null;
        }

        // Otherwise: date-only, with the explicit time if supplied else end of day.
        $isoDate = self::isoDateFormat($raw);
        $time = $hasExplicitTime
            ? sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0)
            : '23:59:59';
        return (empty($isoDate) || $isoDate === self::EMPTY_DATE) ? null : $isoDate . ' ' . $time;
    }

    // True if the response was submitted strictly after the shipment cutoff.
    // Missing data is treated as "not late" — callers gate the on-time path
    // separately when a response is required at all.
    public static function isResponseLate(?string $responseDate, ?string $dueDate): bool
    {
        $cutoff = self::shipmentCutoff($dueDate);
        if ($cutoff === null || empty($responseDate)) {
            return false;
        }
        $responseDateOnly = explode(' ', trim((string) $responseDate))[0];
        if ($responseDateOnly === '' || $responseDateOnly === self::EMPTY_DATE) {
            return false;
        }
        try {
            // Response date is a calendar date (no time component captured today).
            // Compare it as end-of-day in the same cutoff TZ — a response logged on
            // the due date is on-time regardless of admin TZ.
            $tz = self::cutoffTimezone();
            $response = (new DateTimeImmutable($responseDateOnly . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return false;
        }
        return $response > $cutoff;
    }

    public static function getDateBeforeMonths(int $months): string
    {
        return (new DateTimeImmutable())->modify("-{$months} months")->format('Y-m-d');
    }

    private static function filterValidDates(array $dates): array
    {
        return array_filter($dates, fn ($date): bool => self::isDateValid($date));
    }

    public static function getLowestDate(...$dates): ?string
    {
        $validDates = self::filterValidDates($dates);
        if ($validDates === []) {
            return null;
        }

        $earliest = null;
        foreach ($validDates as $date) {
            $dt = new DateTimeImmutable(self::normalizeDateString((string) $date));
            if ($earliest === null || $dt < $earliest) {
                $earliest = $dt;
            }
        }

        return $earliest?->format('Y-m-d H:i:s');
    }

    public static function getHighestDate(...$dates): ?string
    {
        $validDates = self::filterValidDates($dates);
        if ($validDates === []) {
            return null;
        }

        $latest = null;
        foreach ($validDates as $date) {
            $dt = new DateTimeImmutable(self::normalizeDateString((string) $date));
            if ($latest === null || $dt > $latest) {
                $latest = $dt;
            }
        }

        return $latest?->format('Y-m-d H:i:s');
    }
}
