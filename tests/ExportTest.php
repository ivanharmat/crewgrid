<?php

namespace CrewGrid\Tests;

use CrewGrid\Export\XlsxWriter;
use CrewGrid\Tests\Fixtures\OrdersGrid;
use Livewire\Livewire;
use ZipArchive;

class ExportTest extends TestCase
{
    public function test_the_writer_produces_a_valid_workbook(): void
    {
        $writer = new XlsxWriter;
        $writer->writeRow(['Name', 'Qty'], bold: true);
        $writer->writeRow(['A & B <steel>', 42]);
        $writer->writeRow(['=SUM(A1:A9)', '007']);

        $path = (string) tempnam(sys_get_temp_dir(), 'crewgrid-test');
        $writer->save($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml', 'xl/styles.xml'] as $part) {
            $this->assertNotFalse($zip->locateName($part), $part.' must exist.');
        }
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('A &amp; B &lt;steel&gt;', $sheet);
        $this->assertStringContainsString('<v>42</v>', $sheet, 'Ints are numeric cells.');
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=SUM(A1:A9)</t>', $sheet,
            'A leading = exports as text, never as a formula.');
        $this->assertStringContainsString('<t xml:space="preserve">007</t>', $sheet, 'Numeric-looking strings keep their leading zeros.');
        $this->assertStringContainsString('s="1"', $sheet, 'The header row is bold.');
    }

    public function test_the_grid_exports_the_filtered_sorted_set(): void
    {
        $component = Livewire::test(OrdersGrid::class)
            ->set('filters.customer', ['Acme' => true])
            ->call('sortBy', 'total')
            ->call('export')
            ->assertFileDownloaded();

        $sheet = $this->downloadedSheet($component);

        $this->assertStringContainsString('ORD-001', $sheet);
        $this->assertStringContainsString('ORD-003', $sheet);
        $this->assertStringNotContainsString('ORD-002', $sheet, 'Filtered-out rows stay out.');
        $this->assertLessThan(strpos($sheet, 'ORD-001'), strpos($sheet, 'ORD-003'), 'The sort applies: 75 before 100.');

        $this->assertStringContainsString('<v>100</v>', $sheet, 'exportAs() exports the raw number, not the $-formatted string.');
        $this->assertStringContainsString('>paid<', $sheet, 'An html() column exports its raw field value, not its markup.');
        $this->assertStringNotContainsString('badge', $sheet);
        $this->assertStringNotContainsString('example.test', $sheet, 'The notExportable() action column stays out.');
    }

    public function test_hiding_a_column_shapes_the_export(): void
    {
        $component = Livewire::test(OrdersGrid::class)
            ->call('toggleColumn', 'customer')
            ->call('export');

        $this->assertStringNotContainsString('>Customer<', $this->downloadedSheet($component));
    }

    public function test_export_can_be_switched_off_per_grid(): void
    {
        Livewire::test(OrdersGrid::class, ['exportable' => false])
            ->call('export')
            ->assertStatus(403);
    }

    private function downloadedSheet($component): string
    {
        $download = $component->effects['download'] ?? null;
        $this->assertNotNull($download, 'The export must produce a download.');

        $path = (string) tempnam(sys_get_temp_dir(), 'crewgrid-test');
        file_put_contents($path, base64_decode($download['content']));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path), 'The download must be a readable .xlsx.');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        return $sheet;
    }
}
