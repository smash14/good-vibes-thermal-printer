<?php

// Owns all reading/writing of goodVibes.csv. Callers work with plain quote
// strings and row indexes; nothing outside this class needs to know the
// data is stored as a single-column CSV.
final class QuoteRepository
{
    private string $csvFile;
    private int $maxLineLength;

    public function __construct(string $csvFile, int $maxLineLength)
    {
        $this->csvFile = $csvFile;
        $this->maxLineLength = $maxLineLength;
    }

    public function exists(): bool
    {
        return file_exists($this->csvFile);
    }

    /** @return string[] quote text, indexed by row number */
    public function all(): array
    {
        return array_map(
            static fn (array $row): string => str_replace('\\n', "\n", $row[0] ?? ''),
            $this->loadRows()
        );
    }

    public function replaceWithUploadedFile(string $tmpUploadPath): void
    {
        if (!move_uploaded_file($tmpUploadPath, $this->csvFile)) {
            throw new RuntimeException('Could not save uploaded CSV file.');
        }
    }

    public function add(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Quote text must not be empty.');
        }

        $fp = fopen($this->csvFile, 'a');
        fputcsv($fp, [QuoteFormatter::wrap($text, $this->maxLineLength)]);
        fclose($fp);
    }

    public function update(int $rowIndex, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Quote text must not be empty.');
        }

        $rows = $this->loadRows();
        if (!isset($rows[$rowIndex])) {
            throw new OutOfRangeException("No quote at index {$rowIndex}.");
        }

        $rows[$rowIndex] = [QuoteFormatter::wrap($text, $this->maxLineLength)];
        $this->saveRows($rows);
    }

    public function delete(int $rowIndex): void
    {
        $rows = $this->loadRows();
        if (!isset($rows[$rowIndex])) {
            throw new OutOfRangeException("No quote at index {$rowIndex}.");
        }

        unset($rows[$rowIndex]);
        $this->saveRows(array_values($rows));
    }

    /** @return array<int, array<int, string>> */
    private function loadRows(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $handle = fopen($this->csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read CSV file.');
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /** @param array<int, array<int, string>> $rows */
    private function saveRows(array $rows): void
    {
        $fp = fopen($this->csvFile, 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
}
