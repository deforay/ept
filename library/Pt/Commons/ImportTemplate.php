<?php

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pt_Commons_MiscUtility as MiscUtility;

/**
 * Builds the bulk-import Excel templates in the admin's language and validates uploads in
 * any installed language.
 *
 * The importers read cells by column letter, so a header is never data. A header proves
 * that the column under it is the column the importer expects. The header can therefore be
 * translated without loosening the check. The check compares each header with that
 * column's label in every installed language, not with one English file.
 *
 * Validation has two layers. Each layer catches what the other cannot.
 *  - The very-hidden `_columns` sheet records the template type and the key of every
 *    column. It proves which template a file came from. It does not move when someone
 *    inserts a column into the visible sheet. It is lost when rows are pasted into a fresh
 *    workbook. If the sheet is present, its keys are checked. If it is absent, the file
 *    still passes on its headers.
 *  - The visible header row is always checked. It is the only record of where the columns
 *    actually are.
 *
 * The installed languages come from application/languages/<locale>/<locale>.mo. Adding a
 * language needs no change here. The English rendering of every header must stay equal to
 * the headers of the static files in public/files/, ignoring case and whitespace.
 * Otherwise templates already in circulation stop validating.
 */
final class Pt_Commons_ImportTemplate
{
    public const PARTICIPANTS = 'participants';
    public const PTCC = 'ptcc';
    public const ENROLLMENT = 'enrollment';

    public const DEFAULT_PASSWORD = 'ept1@)(*&^'; // NOSONAR

    private const COLUMNS_SHEET = '_columns';
    private const COUNTRY_SHEET = 'Country List';
    private const SOURCE_LOCALE = 'en_US';

    /** Rows of the visible sheet that get the dropdowns, matching the static templates. */
    private const LAST_INPUT_ROW = 1000;

    /** @var array<string, Zend_Translate|null>|null */
    private static ?array $translators = null;

    /** @var array<string, int>|null */
    private static ?array $countryIndex = null;

    /**
     * Marks a string for extraction into the .po catalogs without translating it here:
     * a header is translated once per language, at render and at validation time.
     */
    private static function _(string $msgid): string
    {
        return $msgid;
    }

    /**
     * One definition per template. Each column is its letter (by position), a stable key
     * recorded in `_columns`, and its header as a list of separately translated lines.
     * 'version' goes up whenever the columns change.
     */
    private static function definition(string $type): array
    {
        $country = ['key' => 'country', 'header' => [self::_('Country Name'), self::_('(Pick from dropdown)')], 'country' => true];
        $password = ['key' => 'password', 'header' => [self::_('Password'), self::_('- if left blank, the default password is %s')]];

        $definitions = [
            self::PARTICIPANTS => [
                'version' => 1,
                'base' => 'Participant-Bulk-Import-Excel-Format-v2.xlsx',
                'columns' => [
                    ['key' => 's_no', 'header' => [self::_('S.No.')]],
                    ['key' => 'participant_id', 'header' => [self::_('Participant ID')]],
                    ['key' => 'individual', 'header' => [self::_('Individual Participant'), self::_('(yes/no)')], 'yesno' => true],
                    ['key' => 'first_name', 'header' => [self::_('Participant First Name (OR) Lab Name')]],
                    ['key' => 'last_name', 'header' => [self::_('Participant Last Name')]],
                    ['key' => 'institute', 'header' => [self::_('Institute Name')]],
                    ['key' => 'department', 'header' => [self::_('Department')]],
                    ['key' => 'address', 'header' => [self::_('Address')]],
                    ['key' => 'shipping_address', 'header' => [self::_('Shipping Address')]],
                    ['key' => 'district', 'header' => [self::_('District/County')]],
                    ['key' => 'state', 'header' => [self::_('Province/State')]],
                    ['key' => 'region', 'header' => [self::_('Region')]],
                    $country,
                    ['key' => 'zip', 'header' => [self::_('Zip')]],
                    ['key' => 'longitude', 'header' => [self::_('Longitude')]],
                    ['key' => 'latitude', 'header' => [self::_('Latitude')]],
                    ['key' => 'mobile', 'header' => [self::_('Mobile Number')]],
                    ['key' => 'email', 'header' => [self::_('Email ID')]],
                    $password,
                    ['key' => 'additional_email', 'header' => [self::_('Additional Email ID')]],
                ],
            ],
            self::PTCC => [
                'version' => 1,
                'base' => 'PTCC_Bulk_Import_Excel_Format.xlsx',
                'columns' => [
                    ['key' => 's_no', 'header' => [self::_('S.No.')]],
                    ['key' => 'primary_email', 'header' => [self::_('Primary Email')]],
                    ['key' => 'first_name', 'header' => [self::_('User First Name')]],
                    ['key' => 'last_name', 'header' => [self::_('User Last Name')]],
                    ['key' => 'institute', 'header' => [self::_('Institute Name')]],
                    ['key' => 'secondary_email', 'header' => [self::_('Secondary e-mail Address')]],
                    ['key' => 'cell_phone', 'header' => [self::_('Cell Phone Number')]],
                    ['key' => 'phone', 'header' => [self::_('Phone Number')]],
                    ['key' => 'view_only_access', 'header' => [self::_('Enable View-Only Access')], 'yesno' => true],
                    $country,
                    ['key' => 'state', 'header' => [self::_('State/Province')]],
                    ['key' => 'district', 'header' => [self::_('District/County')]],
                    $password,
                ],
            ],
            self::ENROLLMENT => [
                'version' => 1,
                'base' => 'scheme-entrollment-bulk-import-format.xlsx',
                'columns' => [
                    ['key' => 'participant_id', 'header' => [self::_('Participant ID')]],
                ],
            ],
        ];

        if (!isset($definitions[$type])) {
            throw new InvalidArgumentException("Unknown import template: {$type}");
        }
        return $definitions[$type];
    }

    // --- Languages ---------------------------------------------------------------------

    /**
     * Every installed locale, the source locale first. A locale is installed when its
     * compiled catalog exists. The source locale is always present, catalog or not.
     *
     * @return string[]
     */
    public static function locales(): array
    {
        $locales = [self::SOURCE_LOCALE];
        foreach (glob(APPLICATION_PATH . '/languages/*/*.mo') ?: [] as $file) {
            $locale = basename(dirname($file));
            if (basename($file, '.mo') === $locale && !in_array($locale, $locales, true)) {
                $locales[] = $locale;
            }
        }
        return $locales;
    }

    private static function currentLocale(): string
    {
        $locale = Zend_Registry::isRegistered('Zend_Locale') ? (string) Zend_Registry::get('Zend_Locale') : '';
        return in_array($locale, self::locales(), true) ? $locale : self::SOURCE_LOCALE;
    }

    private static function translate(string $msgid, string $locale): string
    {
        if (self::$translators === null) {
            self::$translators = [];
            foreach (self::locales() as $l) {
                $mo = APPLICATION_PATH . "/languages/{$l}/{$l}.mo";
                try {
                    self::$translators[$l] = is_file($mo)
                        ? new Zend_Translate(['adapter' => 'gettext', 'content' => $mo, 'locale' => $l])
                        : null;
                } catch (Throwable $e) {
                    // A broken catalog costs that language its translations, not the import.
                    Pt_Commons_LoggerUtility::logError("Import template: cannot load {$mo}: " . $e->getMessage());
                    self::$translators[$l] = null;
                }
            }
        }
        $translator = self::$translators[$locale] ?? null;
        $text = $translator ? (string) $translator->_($msgid) : $msgid;
        return $text !== '' ? $text : $msgid;
    }

    /** A column's header in one language, one translated line per part. */
    private static function header(array $column, string $locale): string
    {
        $lines = [];
        foreach ($column['header'] as $part) {
            $lines[] = str_replace('%s', self::DEFAULT_PASSWORD, self::translate($part, $locale));
        }
        return implode("\n", $lines);
    }

    /** What two headers must agree on: everything but case and whitespace. */
    private static function normalizeHeader($header): string
    {
        $text = (string) MiscUtility::normalizeImportText((string) $header);
        return mb_strtolower(preg_replace('/\s+/u', '', $text));
    }

    private static function columnLetter(int $index): string
    {
        return chr(ord('A') + $index);
    }

    // --- Values --------------------------------------------------------------------------

    /**
     * 'yes' or 'no' for a yes/no cell written in any installed language ("Oui", "No",
     * "có"...), or null when the cell is neither. Callers decide what null means.
     */
    public static function normalizeYesNo($value): ?string
    {
        $text = mb_strtolower(trim((string) MiscUtility::normalizeImportText((string) $value)));
        if ($text === '') {
            return null;
        }
        foreach (['yes' => 'Yes', 'no' => 'No'] as $canonical => $msgid) {
            if ($text === $canonical) {
                return $canonical;
            }
            foreach (self::locales() as $locale) {
                if ($text === mb_strtolower(self::translate($msgid, $locale))) {
                    return $canonical;
                }
            }
        }
        return null;
    }

    /** The name a country goes by in a locale: ICU's, falling back to ePT's own. */
    private static function countryName(array $country, string $locale): string
    {
        if ($locale !== self::SOURCE_LOCALE && !empty($country['iso2'])) {
            $name = Locale::getDisplayRegion('-' . $country['iso2'], $locale);
            if ($name !== false && $name !== '' && strcasecmp($name, $country['iso2']) !== 0) {
                return $name;
            }
        }
        return (string) $country['iso_name'];
    }

    /**
     * id => the name each country shows in this language's dropdown. A translated name
     * that already means another country gets that country's ISO3 code appended. One
     * example is Vietnamese "Nga" (Russia), which is also Nigeria's ISO3 code. Without
     * the code, picking it would store the other country.
     *
     * @return array<int, string>
     */
    private static function countryDisplayNames(string $locale, $db = null): array
    {
        static $names = [];
        if (isset($names[$locale])) {
            return $names[$locale];
        }
        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
        $countries = $db->fetchAll($db->select()->from('countries', ['id', 'iso_name', 'iso2', 'iso3'])->order('id'));
        $base = MiscUtility::buildBaseCountryCache($db);

        $display = [];
        $taken = [];
        foreach ($countries as $country) {
            $id = (int) $country['id'];
            $name = self::countryName($country, $locale);
            $key = mb_strtolower($name);
            $clashes = (isset($base[$key]) && (int) $base[$key] !== $id)
                || (isset($taken[$key]) && $taken[$key] !== $id);
            if ($clashes && $locale !== self::SOURCE_LOCALE) {
                $name .= ' (' . strtoupper((string) ($country['iso3'] ?: $country['iso2'])) . ')';
                $key = mb_strtolower($name);
            }
            $taken[$key] ??= $id;
            $display[$id] = $name;
        }
        return $names[$locale] = $display;
    }

    /**
     * Lowercased dropdown name in every installed language => country id, for the
     * importers' country lookup. Built from the same names the dropdowns offer, so every
     * dropdown entry resolves to its own country.
     *
     * @return array<string, int>
     */
    public static function localizedCountryIndex($db = null): array
    {
        if (self::$countryIndex !== null) {
            return self::$countryIndex;
        }
        $index = [];
        foreach (self::locales() as $locale) {
            if ($locale === self::SOURCE_LOCALE) {
                continue;
            }
            foreach (self::countryDisplayNames($locale, $db) as $id => $name) {
                $index[mb_strtolower($name)] ??= $id;
            }
        }
        return self::$countryIndex = $index;
    }

    // --- Building -------------------------------------------------------------------------

    /**
     * The template in the current admin's language: the static file's layout and styling,
     * with translated headers, the country list rebuilt from the countries table in that
     * language, yes/no dropdowns, and the `_columns` sheet.
     */
    public static function build(string $type, ?string $locale = null): Spreadsheet
    {
        $definition = self::definition($type);
        $locale ??= self::currentLocale();

        $spreadsheet = IOFactory::load(WEB_ROOT . '/files/' . $definition['base']);
        $sheet = $spreadsheet->getSheet(0);

        foreach ($definition['columns'] as $i => $column) {
            $letter = self::columnLetter($i);
            $sheet->setCellValue("{$letter}1", self::header($column, $locale));
            $sheet->getStyle("{$letter}1")->getAlignment()->setWrapText(true);

            if (!empty($column['yesno'])) {
                self::translateSampleYesNo($sheet, $letter, $locale);
                self::addListValidation(
                    $sheet,
                    "{$letter}2:{$letter}" . self::LAST_INPUT_ROW,
                    '"' . self::translate('Yes', $locale) . ',' . self::translate('No', $locale) . '"'
                );
            }
            if (!empty($column['country'])) {
                self::rebuildCountryList($spreadsheet, $sheet, $letter, $locale);
            }
        }

        self::writeColumnsSheet($spreadsheet, $type, $definition, $locale);
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    /** Sends the template as a download and ends the request. */
    public static function send(string $type): void
    {
        $locale = self::currentLocale();
        $spreadsheet = self::build($type, $locale);
        $base = pathinfo(self::definition($type)['base'], PATHINFO_FILENAME);
        $fileName = $base . ($locale === self::SOURCE_LOCALE ? '' : '-' . $locale) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: private, no-store');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
        exit;
    }

    private static function addListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(false)
            ->setShowErrorMessage(true)
            // PhpSpreadsheet's flag is inverted: true would HIDE the in-cell arrow.
            ->setShowDropDown(false)
            ->setFormula1($formula);
        $sheet->setDataValidation($range, $validation);
    }

    private static function translateSampleYesNo(Worksheet $sheet, string $letter, string $locale): void
    {
        for ($row = 2, $last = $sheet->getHighestDataRow($letter); $row <= $last; $row++) {
            $value = self::normalizeYesNo($sheet->getCell("{$letter}{$row}")->getValue());
            if ($value !== null) {
                $sheet->setCellValue("{$letter}{$row}", self::translate(ucfirst($value), $locale));
            }
        }
    }

    /**
     * Replaces the hidden country list with the countries table in this language, sorted
     * as that language sorts, and points the dropdown at it. The import resolves countries
     * against the table, so the dropdown offers exactly what the import accepts. The
     * static file's copy goes stale between country refreshes.
     */
    private static function rebuildCountryList(Spreadsheet $spreadsheet, Worksheet $sheet, string $letter, string $locale): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $countries = $db->fetchAll($db->select()->from('countries', ['id', 'iso_name', 'iso2', 'iso3', 'numeric_code']));
        $names = self::countryDisplayNames($locale, $db);
        foreach ($countries as &$country) {
            $country['display'] = $names[(int) $country['id']] ?? (string) $country['iso_name'];
        }
        unset($country);
        $collator = new Collator($locale);
        usort($countries, fn ($a, $b) => $collator->compare($a['display'], $b['display']));

        $list = $spreadsheet->getSheetByName(self::COUNTRY_SHEET);
        if ($list !== null) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($list));
        }
        $list = $spreadsheet->createSheet();
        $list->setTitle(self::COUNTRY_SHEET);
        $list->fromArray(['id', 'iso_name', 'iso2', 'iso3', 'numeric_code'], null, 'A1');
        $row = 2;
        foreach ($countries as $country) {
            $list->fromArray([$country['id'], $country['display'], $country['iso2'], $country['iso3'], $country['numeric_code']], null, "A{$row}", true);
            $row++;
        }
        $list->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // Translate the sample rows' countries too, or the sample itself fails the dropdown.
        $index = MiscUtility::buildCountryCache($db);
        $display = array_column($countries, 'display', 'id');
        for ($r = 2, $last = $sheet->getHighestDataRow($letter); $r <= $last; $r++) {
            $value = trim((string) $sheet->getCell("{$letter}{$r}")->getValue());
            $id = isset($index[mb_strtolower($value)]) ? (int) $index[mb_strtolower($value)] : null;
            if ($value !== '' && $id !== null && isset($display[$id])) {
                $sheet->setCellValue("{$letter}{$r}", $display[$id]);
            }
        }

        $sheet->setDataValidation("{$letter}2:{$letter}" . self::LAST_INPUT_ROW, null);
        self::addListValidation(
            $sheet,
            "{$letter}2:{$letter}" . self::LAST_INPUT_ROW,
            "'" . self::COUNTRY_SHEET . "'!\$B\$2:\$B\$" . ($row - 1)
        );
    }

    private static function writeColumnsSheet(Spreadsheet $spreadsheet, string $type, array $definition, string $locale): void
    {
        $meta = $spreadsheet->createSheet();
        $meta->setTitle(self::COLUMNS_SHEET);
        $meta->fromArray([
            ['template', $type],
            ['version', $definition['version']],
            ['locale', $locale],
            [],
            ['column', 'key', 'header'],
        ], null, 'A1');
        foreach ($definition['columns'] as $i => $column) {
            $meta->fromArray([self::columnLetter($i), $column['key'], self::header($column, $locale)], null, 'A' . (6 + $i));
        }
        // Very hidden: Excel's Unhide dialog does not list it, so nobody edits it by accident.
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
    }

    // --- Validation -----------------------------------------------------------------------

    /**
     * Header problems in an uploaded file, as [column, expected, actual] rows. The list is
     * empty when the file matches. "Expected" is in the current admin's language.
     */
    public static function validate(string $type, string $fileName): array
    {
        $definition = self::definition($type);
        $current = self::currentLocale();
        $spreadsheet = IOFactory::load($fileName);
        $uploaded = $spreadsheet->getSheet(0)->rangeToArray('A1:Z1', null, false, false)[0];
        $recorded = self::readColumns($spreadsheet, $type);

        $mismatches = [];
        foreach (range(0, 25) as $i) {
            $letter = self::columnLetter($i);
            $column = $definition['columns'][$i] ?? null;
            $actual = trim(preg_replace('/\s+/u', ' ', (string) ($uploaded[$i] ?? '')));

            if ($column === null) {
                // Past the template's last column a header means the file has more
                // columns than the importer knows about, and some of them are misplaced.
                if ($actual !== '') {
                    $mismatches[] = ['column' => $letter, 'expected' => '', 'actual' => $actual];
                }
                continue;
            }

            $keyOk = $recorded === null || ($recorded[$letter]['key'] ?? null) === $column['key'];

            // The header the file was generated with also passes. A later edit to a
            // translation then does not reject templates downloaded before it.
            $accepted = [$recorded[$letter]['header'] ?? ''];
            foreach (self::locales() as $locale) {
                $accepted[] = self::header($column, $locale);
            }
            $headerOk = false;
            foreach ($accepted as $header) {
                if ($header !== '' && self::normalizeHeader($actual) === self::normalizeHeader($header)) {
                    $headerOk = true;
                    break;
                }
            }

            if (!$headerOk || !$keyOk) {
                $mismatches[] = [
                    'column' => $letter,
                    'expected' => trim(preg_replace('/\s+/u', ' ', self::header($column, $current))),
                    'actual' => $actual,
                ];
            }
        }
        return $mismatches;
    }

    /**
     * letter => [key, header] from the `_columns` sheet, or null when the file has none.
     * A sheet naming another template yields no columns, so every column fails the key
     * check.
     *
     * @return array<string, array{key: string, header: string}>|null
     */
    private static function readColumns(Spreadsheet $spreadsheet, string $type): ?array
    {
        $meta = $spreadsheet->getSheetByName(self::COLUMNS_SHEET);
        if ($meta === null) {
            return null;
        }
        if (trim((string) $meta->getCell('B1')->getValue()) !== $type) {
            return [];
        }
        $columns = [];
        for ($row = 6, $last = $meta->getHighestDataRow(); $row <= $last; $row++) {
            $letter = trim((string) $meta->getCell("A{$row}")->getValue());
            if ($letter !== '') {
                $columns[$letter] = [
                    'key' => trim((string) $meta->getCell("B{$row}")->getValue()),
                    'header' => (string) $meta->getCell("C{$row}")->getValue(),
                ];
            }
        }
        return $columns;
    }
}
