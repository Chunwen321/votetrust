<?php

// PIC: Low Wen Kai — Election module spreadsheet parser. Source files are never persisted.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use Throwable;

final class PrivateVoterRosterFile
{
    private const MAX_ROWS = 1000;

    /**
     * The `ic_number` column name is kept for a simple, consistent template.
     * Its values may contain either a Malaysian IC number or a passport number.
     *
     * @return list<array{row:int,fullName:string,identityNumber:string}>
     */
    public function parse(UploadedFile $file): array
    {
        return $this->parseFor($file, 'voter');
    }

    /** @return list<array{row:int,fullName:string,identityNumber:string}> */
    public function parseCandidates(UploadedFile $file): array
    {
        return $this->parseFor($file, 'candidate');
    }

    /** @return list<array{row:int,fullName:string,identityNumber:string}> */
    private function parseFor(UploadedFile $file, string $participant): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => $this->participantsFromRows($this->csvRows($file, $participant), $participant),
            'xlsx', 'xls' => $this->participantsFromRows($this->spreadsheetRows($file, $participant), $participant),
            default => throw new RuntimeException("Upload a CSV, XLSX, or XLS {$participant} list."),
        };
    }

    /** @return list<array{row:int,values:list<string>}> */
    private function csvRows(UploadedFile $file, string $participant): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException("The {$participant} CSV could not be read.");
        }

        try {
            $rows = [];
            $line = 0;
            while (($record = fgetcsv($handle)) !== false) {
                $line++;
                if ($line > self::MAX_ROWS + 1) {
                    throw new RuntimeException("A private {$participant} list can contain up to ".self::MAX_ROWS." {$participant}s.");
                }
                $rows[] = [
                    'row' => $line,
                    'values' => array_map(static fn (mixed $value): string => trim((string) $value), $record),
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return list<array{row:int,values:list<string>}> */
    private function spreadsheetRows(UploadedFile $file, string $participant): array
    {
        $spreadsheet = null;
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $sheet->getHighestDataRow();
            if ($highestRow > self::MAX_ROWS + 1) {
                throw new RuntimeException("A private {$participant} list can contain up to ".self::MAX_ROWS." {$participant}s.");
            }

            $highestColumn = $sheet->getHighestDataColumn();
            if ($highestRow < 1 || $highestColumn === 'A' && trim((string) $sheet->getCell('A1')->getFormattedValue()) === '') {
                return [];
            }

            $rows = $sheet->rangeToArray(
                "A1:{$highestColumn}{$highestRow}",
                null,
                false,
                true,
                false,
            );

            return array_map(
                static fn (array $values, int $index): array => [
                    'row' => $index + 1,
                    'values' => array_map(static fn (mixed $value): string => trim((string) $value), $values),
                ],
                $rows,
                array_keys($rows),
            );
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException("The Excel {$participant} list could not be read. Use a valid XLSX or XLS file.");
        } finally {
            if ($spreadsheet instanceof Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    /**
     * @param  list<array{row:int,values:list<string>}>  $rows
     * @return list<array{row:int,fullName:string,identityNumber:string}>
     */
    private function participantsFromRows(array $rows, string $participant): array
    {
        $header = array_shift($rows);
        if ($header === null) {
            throw new RuntimeException("The {$participant} list must include a header row.");
        }

        $columns = [];
        foreach ($header['values'] as $index => $name) {
            $columns[$this->normaliseHeader($name)] = $index;
        }
        if (! isset($columns['fullname'], $columns['icnumber'])) {
            throw new RuntimeException('Use the headers "full_name" and "ic_number". The ic_number column can contain an IC or passport number.');
        }

        $participants = [];
        foreach ($rows as $record) {
            $fullName = trim((string) ($record['values'][$columns['fullname']] ?? ''));
            $identityNumber = trim((string) ($record['values'][$columns['icnumber']] ?? ''));
            if ($fullName === '' && $identityNumber === '') {
                continue;
            }
            if ($fullName === '' || $identityNumber === '') {
                throw new RuntimeException(ucfirst($participant)."-list row {$record['row']} must include both full_name and ic_number.");
            }
            if (count($participants) >= self::MAX_ROWS) {
                throw new RuntimeException("A private {$participant} list can contain up to ".self::MAX_ROWS." {$participant}s.");
            }

            $participants[] = [
                'row' => $record['row'],
                'fullName' => $fullName,
                'identityNumber' => $identityNumber,
            ];
        }

        if ($participants === []) {
            throw new RuntimeException("The {$participant} list does not contain any {$participant} rows.");
        }

        return $participants;
    }

    private function normaliseHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($header)));
    }
}
