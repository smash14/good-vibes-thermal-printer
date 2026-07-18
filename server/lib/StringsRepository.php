<?php

// Owns reading/writing strings.json (the user-facing text shown on receipts).
final class StringsRepository
{
    private string $stringsFile;
    private string $defaultStringsFile;

    public function __construct(string $stringsFile, string $defaultStringsFile)
    {
        $this->stringsFile = $stringsFile;
        $this->defaultStringsFile = $defaultStringsFile;
    }

    public function exists(): bool
    {
        return file_exists($this->stringsFile);
    }

    public function rawContent(): string
    {
        return file_get_contents($this->stringsFile);
    }

    public function prettyContent(): string
    {
        $decoded = json_decode($this->rawContent(), true);
        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function replaceWithUploadedFile(string $tmpUploadPath): void
    {
        if (!move_uploaded_file($tmpUploadPath, $this->stringsFile)) {
            throw new RuntimeException('Could not save uploaded strings.json file.');
        }
    }

    public function restoreDefault(): void
    {
        if (!file_exists($this->defaultStringsFile)) {
            throw new RuntimeException('Default strings.json file not found.');
        }
        if (!copy($this->defaultStringsFile, $this->stringsFile)) {
            throw new RuntimeException('Could not restore strings.json from default.');
        }
    }

    /** @return array<string, array{description?: string, text: string|array<int, string>}> */
    public function getEntries(): array
    {
        $decoded = json_decode($this->rawContent(), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function updateEntryValue(string $key, string $rawValue): void
    {
        $decoded = json_decode($this->rawContent(), true);
        if (!is_array($decoded) || !array_key_exists($key, $decoded)) {
            throw new RuntimeException("Unknown strings.json entry: {$key}");
        }

        $normalized = str_replace("\r\n", "\n", $rawValue);

        if (is_array($decoded[$key]['text'] ?? null)) {
            $decoded[$key]['text'] = array_values(array_filter(
                array_map('trim', explode("\n", $normalized)),
                static fn (string $line): bool => $line !== ''
            ));
        } else {
            $decoded[$key]['text'] = $normalized;
        }

        $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($this->stringsFile, $json) === false) {
            throw new RuntimeException('Could not save strings.json.');
        }
    }
}
