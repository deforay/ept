<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class Pt_Commons_SpreadsheetUtility
{
    /**
     * A new Spreadsheet starts with an empty sheet named "Worksheet". Exports that add
     * their own sheets leave it at the end as a blank tab. Remove it, and open the file
     * on the first sheet.
     */
    public static function removeEmptyDefaultSheet(Spreadsheet $excel): void
    {
        $defaultSheet = $excel->getSheetByName('Worksheet');
        if (
            $defaultSheet !== null
            && $excel->getSheetCount() > 1
            && $defaultSheet->getHighestDataRow() === 1
            && $defaultSheet->getHighestDataColumn() === 'A'
            && in_array($defaultSheet->getCell('A1')->getValue(), [null, ''], true)
        ) {
            $excel->removeSheetByIndex($excel->getIndex($defaultSheet));
        }
        $excel->setActiveSheetIndex(0);
    }
}
