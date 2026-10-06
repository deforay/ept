<?php

// Clean characters that are obviously not part of a name out of participant and data
// manager name columns. Most of these "names" are lab and institute names, so ordinary
// punctuation stays: full stops, hyphens, commas, brackets, slashes, colons, "&" and
// apostrophes (l'Hôpital, Children's). What goes:
//
//   - stray whitespace: tabs, line breaks, leading/trailing and doubled spaces
//   - HTML codes stored as text (&#34; &quot; &amp; &#39;), decoded first
//   - double quotes of any style (" “ ” „ « »)
//   - a leading or trailing asterisk
//   - text garbled by an old double UTF-8 encoding (MÃ©dical -> Médical), repaired
//     only when the repair is clean
//   - a value that is only an email address (3194_pt@host), which is a login, not a name
//   - letters lost to "?" by an old import (Laborat?rio -> Laboratório), restored only
//     when exactly one known word fits: words spelled correctly elsewhere in this same
//     database, plus a short list of common lab-name words. Otherwise left as it is.
//     A lone " ? " was a dash and becomes " - "; a "?" stuck to the front of a
//     capitalised word (?Oshana) was an invisible character and is dropped.
//
// A name is only cleaned, never swapped for another record's name, so a data manager
// shared across labs keeps their own. Runs on every install from its own data, so no
// database needs fixing by hand. Every change is printed and saved to BACKUP_PATH as
// JSON first.
// Pass --dry-run to preview without writing. Safe to re-run: clean values match nothing.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** Reverse a double UTF-8 encoding, or return null when the text is not clearly garbled. */
function repairMojibake(string $value): ?string
{
    // Garbled text carries a lead byte rendered as Ã, Â, Ð or Ñ followed by a
    // continuation byte rendered as a Latin-1 or Windows-1252 character.
    if (!preg_match('/[ÂÃÐÑ][\x{80}-\x{BF}\x{152}\x{153}\x{160}\x{161}\x{178}\x{17D}\x{17E}\x{192}\x{2C6}\x{2DC}\x{2013}\x{2014}\x{2018}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]/u', $value)) {
        return null;
    }
    // Windows-1252 characters that sit in 0x80-0x9F, mapped back to their byte
    $cp1252 = [
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85,
        0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A,
        0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92,
        0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97,
        0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C,
        0x017E => 0x9E, 0x0178 => 0x9F,
    ];
    $bytes = '';
    foreach (mb_str_split($value) as $char) {
        $code = mb_ord($char, 'UTF-8');
        if ($code <= 0xFF) {
            $bytes .= chr($code);
        } elseif (isset($cp1252[$code])) {
            $bytes .= chr($cp1252[$code]);
        } else {
            return null; // a character that cannot come from the garbling
        }
    }
    return mb_check_encoding($bytes, 'UTF-8') ? $bytes : null;
}

// Common words in lab and institute names whose accented letter imports lost to "?".
// Words spelled correctly anywhere in the database are added to these at run time.
const KNOWN_ACCENTED_WORDS = [
    'laboratório', 'laboratórios', 'clínico', 'clínica', 'clínicas', 'diagnóstico', 'diagnósticos',
    'saúde', 'pública', 'público', 'referência', 'nacional', 'hospitalário', 'universitário',
    'microbiología', 'virología', 'inmunología', 'biología', 'química', 'atención', 'crónicas',
    'investigación', 'región', 'unidad', 'médico', 'médica',
    'hôpital', 'hôpitaux', 'médical', 'médicale', 'médecine', 'santé', 'référence', 'unité',
    'bactériologie', 'rétrovirus', 'rétrovirologie', 'oncogènes', 'génétique', 'moléculaire',
    'biologie', 'hématologie', 'immunologie', 'parasitologie', 'virologie', 'université',
    'universitaire', 'régional', 'régionale', 'départemental', 'départementale', 'préfectoral',
    'ouédraogo', 'yalgado', 'josé', 'maría', 'garcía', 'rodríguez', 'ramírez',
];

/** Restore letters lost to "?" when exactly one known word fits. */
function restoreLostLetters(string $value, array $vocabulary): string
{
    $value = preg_replace('/(?<=\s)\?(?=\s)/u', '-', $value);
    $value = preg_replace('/(?<![\p{L}\p{N}])\?+(?=\p{Lu})/u', '', $value);

    return preg_replace_callback('/[\p{L}?]*\p{L}[\p{L}?]*\?[\p{L}?]*|[\p{L}?]*\?[\p{L}?]*\p{L}[\p{L}?]*/u', function ($m) use ($vocabulary) {
        $word = $m[0];
        $pattern = '/^' . str_replace('\?', '[^\x00-\x7F]', preg_quote(mb_strtolower($word), '/')) . '$/u';
        $fits = array_values(array_filter($vocabulary, fn ($known) => preg_match($pattern, $known)));
        if (count($fits) !== 1) {
            return $word;
        }
        // Keep the original's case: SHOUTED, Capitalised or lower
        $letters = str_replace('?', '', $word);
        if ($letters !== '' && $letters === mb_strtoupper($letters)) {
            return mb_strtoupper($fits[0]);
        }
        if (preg_match('/^\p{Lu}/u', $word)) {
            return mb_strtoupper(mb_substr($fits[0], 0, 1)) . mb_substr($fits[0], 1);
        }
        return $fits[0];
    }, $value);
}

function cleanName(string $value, array $vocabulary): string
{
    if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false) {
        return '';
    }
    $clean = repairMojibake($value) ?? $value;
    $clean = str_ireplace(['&#34;', '&quot;', '&#39;', '&#039;', '&apos;', '&amp;'], ['"', '"', "'", "'", "'", '&'], $clean);
    $clean = str_replace(['"', '“', '”', '„', '«', '»'], '', $clean);
    $clean = preg_replace('/\s+/u', ' ', $clean);
    $clean = preg_replace('/^\*+|\*+$/u', '', trim($clean));
    if (str_contains($clean, '?')) {
        $clean = restoreLostLetters($clean, $vocabulary);
    }
    return trim(preg_replace('/\s+/u', ' ', $clean));
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    $targets = [
        'participant' => ['participant_id', ['first_name', 'last_name', 'lab_name', 'institute_name']],
        'data_manager' => ['dm_id', ['first_name', 'last_name', 'institute']],
    ];

    // Accented words this database already spells correctly
    $vocabulary = array_flip(KNOWN_ACCENTED_WORDS);
    foreach ($targets as $table => [$idCol, $columns]) {
        foreach ($columns as $column) {
            foreach ($db->fetchCol("SELECT DISTINCT `$column` FROM `$table` WHERE `$column` <> ''") as $text) {
                $text = repairMojibake((string) $text) ?? (string) $text;
                foreach (preg_split('/[^\p{L}\p{M}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    if (preg_match('/[^\x00-\x7F]/u', $word)) {
                        $vocabulary[mb_strtolower($word)] = true;
                    }
                }
            }
        }
    }
    $vocabulary = array_keys($vocabulary);

    // Work out every change first, so the backup is written before anything is updated
    $changes = [];
    $pending = [];
    foreach ($targets as $table => [$idCol, $columns]) {
        foreach ($db->fetchAll("SELECT `$idCol` AS id, `" . implode('`, `', $columns) . "` FROM `$table`") as $row) {
            $update = [];
            foreach ($columns as $column) {
                $before = $row[$column];
                if ($before === null || $before === '') {
                    continue;
                }
                $after = cleanName((string) $before, $vocabulary);
                if ($after !== $before) {
                    $update[$column] = $after;
                    $changes[] = ['table' => $table, 'id' => $row['id'], 'column' => $column, 'before' => $before, 'after' => $after];
                }
            }
            if ($update !== []) {
                $pending[] = [$table, $idCol, $row['id'], $update];
            }
        }
    }

    if ($changes !== [] && !$dryRun) {
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0775, true);
        }
        $backupFile = BACKUP_PATH . DIRECTORY_SEPARATOR . 'clean-name-characters-' . date('Ymd-His') . '.json';
        file_put_contents($backupFile, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "Previous values saved to $backupFile" . PHP_EOL;
        foreach ($pending as [$table, $idCol, $id, $update]) {
            $db->update($table, $update, ["`$idCol` = ?" => $id]);
        }
    }
    foreach ($changes as $c) {
        echo "{$c['table']}.{$c['column']} #{$c['id']}: " . json_encode($c['before'], JSON_UNESCAPED_UNICODE) . ' -> ' . json_encode($c['after'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    echo ($dryRun ? 'Dry run: ' : '') . count($changes) . ' value(s) ' . ($dryRun ? 'would change' : 'cleaned') . PHP_EOL;
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'clean-name-characters failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
