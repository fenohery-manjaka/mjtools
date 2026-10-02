<?php

namespace App\Tools\SupplierReconciliation\Import;

use RuntimeException;

/**
 * A file that cannot be used, with a message meant for the user (spec §37).
 */
final class ImportException extends RuntimeException
{
    public static function empty(): self
    {
        return new self('This file is empty.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self('This file is too large (maximum '.round($maxBytes / 1_048_576).' MB).');
    }

    public static function tooManyRows(int $maxRows): self
    {
        return new self("This file has more than {$maxRows} rows. Please split it or export a shorter period.");
    }

    public static function tooManyColumns(int $maxColumns): self
    {
        return new self("This file has more than {$maxColumns} columns. Please export only the useful columns.");
    }

    public static function sheetNotFound(): self
    {
        // The sheet name is not repeated: import messages reach the usage logs.
        return new self('The workbook has no sheet with this name. Choose one of its sheets.');
    }

    public static function corrupted(): self
    {
        return new self('This file appears to be corrupted and could not be read.');
    }

    public static function noTable(): self
    {
        return new self('No usable table was found: the file needs a header row and at least one data row.');
    }

    public static function unsupported(string $kind): self
    {
        return new self("{$kind} files are not supported. Please save the file as XLSX or CSV and try again.");
    }

    public static function notText(): self
    {
        return new self('This file is neither a CSV text file nor an XLSX workbook.');
    }
}
