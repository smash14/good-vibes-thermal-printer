<?php

// Owns the header_images/ folder: listing, uploading (both raw .bin files
// and plain pictures pending conversion by main.py), deleting, and zipping.
final class ImageRepository
{
    private string $dir;
    private array $allowedExtensions;
    private int $maxUploadBytes;

    public function __construct(string $dir, array $allowedExtensions, int $maxUploadBytes)
    {
        $this->dir = $dir;
        $this->allowedExtensions = $allowedExtensions;
        $this->maxUploadBytes = $maxUploadBytes;

        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    /** @return string[] filenames, not full paths */
    public function listFilenames(): array
    {
        $filenames = [];
        foreach ($this->listFiles() as $file) {
            $filenames[] = basename($file);
        }
        return $filenames;
    }

    public function uploadBinFile(array $uploadedFile): void
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('BIN upload failed.');
        }

        $filename = basename($uploadedFile['name']);
        if (!move_uploaded_file($uploadedFile['tmp_name'], $this->dir . $filename)) {
            throw new RuntimeException('Could not save uploaded BIN file.');
        }
    }

    public function uploadPicture(array $uploadedFile): void
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Picture upload failed (upload error).');
        }
        if ($uploadedFile['size'] > $this->maxUploadBytes) {
            throw new RuntimeException('Picture upload failed: file exceeds the 10MB limit.');
        }

        $filename = basename($uploadedFile['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw new RuntimeException('Picture upload failed: unsupported file type.');
        }
        if (@getimagesize($uploadedFile['tmp_name']) === false) {
            throw new RuntimeException('Picture upload failed: file is not a valid image.');
        }

        if (!move_uploaded_file($uploadedFile['tmp_name'], $this->dir . $filename)) {
            throw new RuntimeException('Could not save uploaded picture.');
        }
    }

    public function deleteAll(): void
    {
        foreach ($this->listFiles() as $file) {
            unlink($file);
        }
    }

    /**
     * Group files by filename stem into one logical entry per image:
     * - 'converted': a .bin exists; thumbnail is its sibling preview .jpg, or null
     *   if the preview hasn't been generated yet (pending next Pi restart).
     * - 'pending': no .bin yet, but the originally uploaded photo is still there;
     *   thumbnail is that original photo.
     * - 'other': neither (stray file); no thumbnail.
     *
     * @return array<int, array{stem: string, kind: string, thumbnail: ?string}>
     */
    public function listEntries(): array
    {
        $filesByStem = [];
        foreach ($this->listFiles() as $file) {
            $filename = basename($file);
            $stem = pathinfo($filename, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesByStem[$stem][$extension] = $filename;
        }

        $entries = [];
        foreach ($filesByStem as $stem => $filesByExtension) {
            if (isset($filesByExtension['bin'])) {
                $entries[] = [
                    'stem' => $stem,
                    'kind' => 'converted',
                    'thumbnail' => $filesByExtension['jpg'] ?? null,
                ];
                continue;
            }

            $originalFilename = null;
            foreach ($this->allowedExtensions as $extension) {
                if (isset($filesByExtension[$extension])) {
                    $originalFilename = $filesByExtension[$extension];
                    break;
                }
            }
            if ($originalFilename !== null) {
                $entries[] = ['stem' => $stem, 'kind' => 'pending', 'thumbnail' => $originalFilename];
                continue;
            }

            $entries[] = ['stem' => $stem, 'kind' => 'other', 'thumbnail' => null];
        }

        usort($entries, fn (array $a, array $b): int => strcmp($a['stem'], $b['stem']));

        return $entries;
    }

    /** Delete every file sharing the given filename stem (e.g. both a .bin and its preview .jpg). */
    public function deleteEntry(string $stem): void
    {
        $stem = basename($stem);
        if ($stem === '' || $stem === '.' || $stem === '..') {
            throw new RuntimeException('Invalid image entry.');
        }

        $deleted = false;
        foreach ($this->listFiles() as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $stem) {
                unlink($file);
                $deleted = true;
            }
        }

        if (!$deleted) {
            throw new RuntimeException('Image entry not found: ' . $stem);
        }
    }

    /** @return string path to a temporary ZIP file; caller is responsible for deleting it */
    public function createZipArchive(): string
    {
        if (!extension_loaded('zip')) {
            throw new RuntimeException('ZIP extension is not installed on this server.');
        }

        $files = $this->listFiles();
        if (empty($files)) {
            throw new RuntimeException('No files found in header_images directory.');
        }

        $zipPath = sys_get_temp_dir() . '/header_images_' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP archive at ' . $zipPath);
        }

        foreach ($files as $file) {
            if (!$zip->addFile($file, basename($file))) {
                throw new RuntimeException('Could not add file to ZIP: ' . basename($file));
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('Could not close ZIP archive.');
        }

        return $zipPath;
    }

    /** @return string[] full file paths */
    private function listFiles(): array
    {
        return array_filter(glob($this->dir . '*') ?: [], 'is_file');
    }
}
