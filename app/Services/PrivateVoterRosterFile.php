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
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => $this->votersFromRows($this->csvRows($file)),
            'xlsx', 'xls' => $this->votersFromRows($this->spreadsheetRows($file)),
            default => throw new RuntimeException('Upload a CSV, XLSX, or XLS voter list.'),
        };
    }

    /** @return list<array{row:int,values:list<string>}> */
    private function csvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The voter CSV could not be read.');
        }

        try {
            $rows = [];
            $line = 0;
            while (($record = fgetcsv($handle)) !== false) {
                $line++;
                if ($line > self::MAX_ROWS + 1) {
                    throw new RuntimeException('A private voter list can contain up to '.self::MAX_ROWS.' voters.');
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
    private function spreadsheetRows(UploadedFile $file): array
    {
        $spreadsheet = null;
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $sheet->getHighestDataRow();
            if ($highestRow > self::MAX_ROWS + 1) {
                throw new RuntimeException('A private voter list can contain up to '.self::MAX_ROWS.' voters.');
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
            throw new RuntimeException('The Excel voter list could not be read. Use a valid XLSX or XLS file.');
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
    private function votersFromRows(array $rows): array
    {
        $header = array_shift($rows);
        if ($header === null) {
            throw new RuntimeException('The voter list must include a header row.');
        }

        $columns = [];
        foreach ($header['values'] as $index => $name) {
            $columns[$this->normaliseHeader($name)] = $index;
        }
        if (! isset($columns['fullname'], $columns['icnumber'])) {
            throw new RuntimeException('Use the headers "full_name" and "ic_number". The ic_number column can contain an IC or passport number.');
        }

        $voters = [];
        foreach ($rows as $record) {
            $fullName = trim((string) ($record['values'][$columns['fullname']] ?? ''));
            $identityNumber = trim((string) ($record['values'][$columns['icnumber']] ?? ''));
            if ($fullName === '' && $identityNumber === '') {
                continue;
            }
            if ($fullName === '' || $identityNumber === '') {
                throw new RuntimeException("Voter-list row {$record['row']} must include both full_name and ic_number.");
            }
            if (count($voters) >= self::MAX_ROWS) {
                throw new RuntimeException('A private voter list can contain up to '.self::MAX_ROWS.' voters.');
            }

            $voters[] = [
                'row' => $record['row'],
                'fullName' => $fullName,
                'identityNumber' => $identityNumber,
            ];
        }

        if ($voters === []) {
            throw new RuntimeException('The voter list does not contain any voter rows.');
        }

        return $voters;
    }

    private function normaliseHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($header)));
    }
}
