<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class LookupExcelSeeder extends Seeder
{
    private const SHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PACKAGE_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedTable('provinces', base_path('GOVERNARATE.xlsx'));
        $this->seedTable('nationalities', base_path('NATIONALITY.xlsx'));
        $this->seedTable('medical_universities', base_path('UNIVERSITY.xlsx'));
        $this->seedTable('grades', base_path('التقدير.xlsx'));
    }

    private function seedTable(string $table, string $filePath): void
    {
        $rows = $this->parseCodeNameRows($filePath);
        $codes = array_column($rows, 'code');
        $now = now();

        DB::transaction(function () use ($table, $rows, $codes, $now): void {
            $existingRows = DB::table($table)
                ->select('id', 'code', 'name')
                ->get();

            $existingByCode = [];
            $existingByArabicName = [];
            foreach ($existingRows as $existingRow) {
                $code = $existingRow->code;
                if (is_numeric($code)) {
                    $existingByCode[(int) $code] = $existingRow;
                }

                $arabicName = $this->extractArabicName($existingRow->name);
                if ($arabicName !== '') {
                    $existingByArabicName[$arabicName] = $existingRow;
                }
            }

            foreach ($rows as $row) {
                $code = $row['code'];
                $name = $row['name'];
                $jsonName = json_encode([
                    'ar' => $name,
                    'en' => $name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $matched = $existingByCode[$code]
                    ?? $existingByArabicName[$name]
                    ?? null;

                if ($matched) {
                    DB::table($table)
                        ->where('id', $matched->id)
                        ->update([
                            'code' => $code,
                            'name' => $jsonName,
                            'updated_at' => $now,
                        ]);

                    continue;
                }

                DB::table($table)->insert([
                    'code' => $code,
                    'name' => $jsonName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $staleIds = DB::table($table)
                ->whereNull('code')
                ->orWhereNotIn('code', $codes)
                ->pluck('id')
                ->all();

            foreach ($staleIds as $id) {
                try {
                    DB::table($table)->where('id', $id)->delete();
                } catch (QueryException) {
                    // Keep referenced rows to avoid foreign key violations.
                }
            }
        });
    }

    /**
     * @return array<int, array{code: int, name: string}>
     */
    private function parseCodeNameRows(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException(sprintf('Excel file not found: %s', $filePath));
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(sprintf('Unable to open Excel file: %s', $filePath));
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->resolveFirstSheetPath($zip);
        $rows = $this->readSheetRows($zip, $sheetPath, $sharedStrings);
        $zip->close();

        // Skip header row (CODE, NAME) and normalize.
        $rows = array_slice($rows, 1);

        $result = [];
        foreach ($rows as $row) {
            $rawCode = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($rawCode === '' || $name === '') {
                continue;
            }

            $code = (int) round((float) $rawCode);
            $result[$code] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return array_values($result);
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if (! $shared instanceof SimpleXMLElement) {
            return [];
        }

        $shared->registerXPathNamespace('m', self::SHEET_NAMESPACE);
        $items = $shared->xpath('//m:si') ?: [];

        $values = [];
        foreach ($items as $item) {
            $item->registerXPathNamespace('m', self::SHEET_NAMESPACE);
            $singleText = $item->xpath('./m:t');

            if (! empty($singleText)) {
                $values[] = (string) ($singleText[0] ?? '');
                continue;
            }

            $runs = $item->xpath('./m:r/m:t') ?: [];
            $combined = '';
            foreach ($runs as $run) {
                $combined .= (string) $run;
            }

            $values[] = $combined;
        }

        return $values;
    }

    private function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationshipXml === false) {
            throw new RuntimeException('Invalid Excel workbook structure.');
        }

        $workbook = simplexml_load_string($workbookXml);
        $relationships = simplexml_load_string($relationshipXml);

        if (! $workbook instanceof SimpleXMLElement || ! $relationships instanceof SimpleXMLElement) {
            throw new RuntimeException('Failed to parse workbook metadata.');
        }

        $workbook->registerXPathNamespace('m', self::SHEET_NAMESPACE);
        $sheets = $workbook->xpath('//m:sheets/m:sheet') ?: [];
        $firstSheet = $sheets[0] ?? null;

        if (! $firstSheet instanceof SimpleXMLElement) {
            throw new RuntimeException('No worksheets found in Excel file.');
        }

        $sheetRelationships = $firstSheet->attributes(self::RELATIONSHIP_NAMESPACE);
        $sheetRelationId = (string) ($sheetRelationships['id'] ?? '');

        if ($sheetRelationId === '') {
            throw new RuntimeException('Worksheet relationship id not found.');
        }

        $relationships->registerXPathNamespace('r', self::PACKAGE_RELATIONSHIP_NAMESPACE);
        $matches = $relationships->xpath(sprintf("//r:Relationship[@Id='%s']", $sheetRelationId)) ?: [];
        $relationship = $matches[0] ?? null;

        if (! $relationship instanceof SimpleXMLElement) {
            throw new RuntimeException('Worksheet relationship target not found.');
        }

        $target = (string) ($relationship['Target'] ?? '');
        if ($target === '') {
            throw new RuntimeException('Worksheet path is empty.');
        }

        return 'xl/' . ltrim($target, '/');
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function readSheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            throw new RuntimeException(sprintf('Worksheet not found: %s', $sheetPath));
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Failed to parse worksheet XML.');
        }

        $sheet->registerXPathNamespace('m', self::SHEET_NAMESPACE);
        $rows = $sheet->xpath('//m:sheetData/m:row') ?: [];

        $result = [];
        foreach ($rows as $row) {
            $cells = [];
            $maxColumnIndex = -1;

            $row->registerXPathNamespace('m', self::SHEET_NAMESPACE);
            $cellElements = $row->xpath('./m:c') ?: [];

            foreach ($cellElements as $cell) {
                $cellRef = (string) ($cell['r'] ?? '');
                if (! preg_match('/^[A-Z]+/', $cellRef, $matches)) {
                    continue;
                }

                $columnIndex = $this->columnIndexFromLetters($matches[0]);
                $maxColumnIndex = max($maxColumnIndex, $columnIndex);

                $type = (string) ($cell['t'] ?? '');
                $value = $this->extractCellValue($cell, $type, $sharedStrings);
                $cells[$columnIndex] = $value;
            }

            if ($maxColumnIndex < 0) {
                continue;
            }

            $rowData = array_fill(0, $maxColumnIndex + 1, '');
            foreach ($cells as $index => $value) {
                $rowData[$index] = $value;
            }

            if (! collect($rowData)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                $result[] = $rowData;
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function extractCellValue(SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 's') {
            $index = (int) ($cell->v ?? 0);

            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            $text = $cell->xpath('./m:is/m:t');

            return (string) ($text[0] ?? '');
        }

        return (string) ($cell->v ?? '');
    }

    private function columnIndexFromLetters(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function extractArabicName(mixed $rawName): string
    {
        $value = (string) $rawName;
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return trim((string) ($decoded['ar'] ?? ''));
        }

        return trim($value);
    }
}
