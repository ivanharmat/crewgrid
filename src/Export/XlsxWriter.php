<?php

namespace CrewGrid\Export;

use RuntimeException;
use ZipArchive;

/**
 * A dependency-free .xlsx writer - the format is a zip of five small XML
 * parts, and writing values-plus-a-bold-header does not justify pulling a
 * spreadsheet library into every host app. Strings are written as inline
 * strings, which Excel never evaluates, so a value starting with "=" arrives
 * as text rather than as a formula. PHP ints and floats become numeric cells;
 * everything else, including numeric-looking strings, stays text so item
 * numbers and phone numbers keep their leading zeros.
 *
 * Rows stream to a temp file as they are produced, so the peak cost of a
 * large export is the zip step, not an all-rows array.
 */
class XlsxWriter
{
    /** @var resource */
    private $sheet;

    private string $sheetPath;

    private int $row = 0;

    public function __construct()
    {
        $this->sheetPath = (string) tempnam(sys_get_temp_dir(), 'crewgrid-sheet');
        $handle = fopen($this->sheetPath, 'w');
        if ($handle === false) {
            throw new RuntimeException('CrewGrid export could not open a temp file for the worksheet.');
        }
        $this->sheet = $handle;
        fwrite($this->sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    public function writeRow(array $cells, bool $bold = false): void
    {
        $this->row++;
        $xml = '<row r="'.$this->row.'">';
        $column = 0;
        foreach ($cells as $cell) {
            $reference = self::columnLetter($column++).$this->row;
            $style = $bold ? ' s="1"' : '';
            if (is_int($cell) || is_float($cell)) {
                $xml .= '<c r="'.$reference.'"'.$style.'><v>'.$cell.'</v></c>';
            } else {
                $value = self::xmlEscape((string) $cell);
                $xml .= '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$value.'</t></is></c>';
            }
        }
        fwrite($this->sheet, $xml.'</row>');
    }

    /**
     * Assemble the workbook around the streamed sheet and write it to $path.
     */
    public function save(string $path): void
    {
        fwrite($this->sheet, '</sheetData></worksheet>');
        fclose($this->sheet);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($this->sheetPath);
            throw new RuntimeException('CrewGrid export could not create the .xlsx archive.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Two fonts and two cell formats: index 0 is the default, index 1 is
        // bold, which writeRow(..., bold: true) picks with s="1".
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font/><font><b/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            .'</styleSheet>');

        $zip->addFile($this->sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();

        @unlink($this->sheetPath);
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        }

        return $letter;
    }

    private static function xmlEscape(string $value): string
    {
        // Control characters below 0x20 (except tab/newline/return) are not
        // representable in XML 1.0 and would corrupt the sheet.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
