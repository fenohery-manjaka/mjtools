<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Import;

use App\Tools\SupplierReconciliation\Import\FileFormat;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Import\ImportException;
use App\Tools\SupplierReconciliation\Import\ImportLimits;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

class FileImporterTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    public function test_it_reads_a_comma_separated_file(): void
    {
        $table = (new FileImporter)->import(Files::text("Invoice No,Date,Amount\nINV-1,12/08/2026,\"1,240.00\"\nINV-2,13/08/2026,99.90\n"));

        $this->assertSame(FileFormat::Csv, $table->format);
        $this->assertSame('comma', $table->details['delimiter']);
        $this->assertSame(['INV-1', '12/08/2026', '1,240.00'], $table->rows[1]);
    }

    public function test_it_reads_a_semicolon_file_with_french_numbers(): void
    {
        $table = (new FileImporter)->import(Files::text("Référence;Date;Montant\nFA-1;12/08/2026;1 240,00\nFA-2;13/08/2026;-420,00\n"));

        $this->assertSame('semicolon', $table->details['delimiter']);
        $this->assertSame(['FA-1', '12/08/2026', '1 240,00'], $table->rows[1]);
    }

    public function test_it_reads_tab_separated_values(): void
    {
        $table = (new FileImporter)->import(Files::text("Ref\tDate\tAmount\nA-100\t2026-08-12\t10.00\n", 'txt'));

        $this->assertSame('tab', $table->details['delimiter']);
        $this->assertSame(['A-100', '2026-08-12', '10.00'], $table->rows[1]);
    }

    public function test_it_handles_encodings(): void
    {
        $importer = new FileImporter;

        $bom = $importer->import(Files::text("\xEF\xBB\xBFRéférence;Montant\nFA-1;10,00\n"));
        $this->assertSame('Référence', $bom->rows[0][0]);

        $windows = $importer->import(Files::text(mb_convert_encoding("Référence;Libellé\nFA-1;Réglement\n", 'Windows-1252', 'UTF-8')));
        $this->assertSame('Windows-1252', $windows->details['encoding']);
        $this->assertSame('Libellé', $windows->rows[0][1]);

        $utf16 = $importer->import(Files::text("\xFF\xFE".mb_convert_encoding("Ref,Amount\nINV-1,10.00\n", 'UTF-16LE', 'UTF-8')));
        $this->assertSame(['INV-1', '10.00'], $utf16->rows[1]);
    }

    public function test_quoted_fields_may_contain_delimiters_and_line_breaks(): void
    {
        $table = (new FileImporter)->import(Files::text("Ref,Description,Amount\nINV-1,\"Goods, delivered\nin two parts\",10.00\n"));

        $this->assertSame("Goods, delivered\nin two parts", $table->rows[1][1]);
    }

    public function test_it_reads_xlsx_with_typed_cells(): void
    {
        $path = Files::xlsx([
            ['ACME Supplies — Statement of account'],
            [],
            ['Invoice No.', 'Date', 'Amount'],
            ['INV-1', new DateTimeImmutable('2026-08-12'), 1240.5],
            ['INV-2', new DateTimeImmutable('2026-08-13'), 99.9],
            ['CN-3', new DateTimeImmutable('2026-08-14'), -420],
        ]);

        $table = (new FileImporter)->import($path);

        $this->assertSame(FileFormat::Xlsx, $table->format);
        $this->assertSame(['INV-1', '2026-08-12', '1240.5'], $table->rows[3]);
        $this->assertSame(['INV-2', '2026-08-13', '99.9'], $table->rows[4]);
        $this->assertSame(['CN-3', '2026-08-14', '-420'], $table->rows[5]);

        $header = (new HeaderDetector)->detect($table);
        $this->assertSame(2, $header);

        $imported = ImportedTable::fromRaw($table, $header);
        $this->assertSame(['Invoice No.', 'Date', 'Amount'], $imported->headers);
        $this->assertSame(4, $imported->rows[0]['number']);
        $this->assertCount(3, $imported->rows);
    }

    public function test_header_detection_skips_title_rows_in_csv(): void
    {
        $table = (new FileImporter)->import(Files::text("ACME Ltd\nStatement date: 31/08/2026\n\nDoc No,Doc Date,Debit,Credit\nINV-1,01/08/2026,100.00,\n"));

        $this->assertSame(3, (new HeaderDetector)->detect($table));
    }

    public function test_imported_table_names_blank_and_duplicate_headers(): void
    {
        $table = (new FileImporter)->import(Files::text("Ref,,Amount,Amount\nA-1,x,1,2\n"));
        $imported = ImportedTable::fromRaw($table, 0);

        $this->assertSame(['Ref', 'Column B', 'Amount', 'Amount (2)'], $imported->headers);
        $this->assertSame(['x'], $imported->samples(1));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusableFiles(): array
    {
        return [
            'empty' => ['', 'empty'],
            'whitespace' => ["  \n \n", 'empty'],
            'legacy xls' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1rest", 'XLS'],
            'pdf' => ["%PDF-1.7\n...", 'PDF'],
            'png' => ["\x89PNG\r\n\x1a\n....", 'Image'],
            'binary' => ["abc\0def\0ghi", 'neither'],
            'single line' => ['Ref,Amount', 'No usable table'],
            'corrupted zip' => ["PK\x03\x04garbage-garbage", 'corrupted'],
        ];
    }

    #[DataProvider('unusableFiles')]
    public function test_unusable_files_are_explained(string $content, string $expectedMessage): void
    {
        try {
            (new FileImporter)->import(Files::text($content));
            $this->fail('An ImportException was expected.');
        } catch (ImportException $e) {
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    public function test_limits_are_enforced(): void
    {
        $rows = "Ref,Amount\n".str_repeat("INV-1,10.00\n", 30);

        $this->expectExceptionObject(ImportException::tooManyRows(20));
        (new FileImporter(new ImportLimits(maxRows: 20)))->import(Files::text($rows));
    }

    public function test_size_limit_is_enforced(): void
    {
        $this->expectExceptionMessage('too large');
        (new FileImporter(new ImportLimits(maxBytes: 10)))->import(Files::text("Ref,Amount\nINV-1,10.00\n"));
    }

    public function test_column_limit_is_enforced(): void
    {
        $this->expectExceptionMessage('columns');
        (new FileImporter(new ImportLimits(maxColumns: 3)))->import(Files::text("a,b,c,d\n1,2,3,4\n"));
    }
}
