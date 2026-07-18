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

    public function deleteAll(): void
    {
        foreach ($this->listFiles() as $file) {
            unlink($file);
        }
    }

    /**
     * Stage an uploaded picture for review: validates it (extension allow-list,
     * size limit, must be a real image), stores it under a hidden .review/
     * subfolder (invisible to both listEntries() and main.py's non-recursive
     * header_images/ scanning, so it can't be picked up as a gallery entry or
     * auto-converted before it's saved), and runs an initial conversion with
     * default settings so a preview is ready immediately. Only one image may be
     * under review at a time; staging a new one discards whatever was previously
     * staged.
     */
    public function stageUpload(array $uploadedFile, ImageConverterRunner $converter, int $maxWidth = 384): void
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

        $this->discardReview();

        $reviewDir = $this->reviewDir();
        if (!file_exists($reviewDir)) {
            mkdir($reviewDir, 0777, true);
        }

        $stem = pathinfo($filename, PATHINFO_FILENAME);
        // Named "original.<ext>" rather than "<stem>.<ext>" so it can never collide
        // with the "<stem>.bin"/"<stem>.jpg" files regeneratePreview() writes into
        // this same folder (which would happen whenever the upload itself is a
        // .jpg) - regenerating would otherwise silently re-convert an
        // already-converted preview instead of the true original.
        $originalFilename = 'original.' . $extension;
        if (!move_uploaded_file($uploadedFile['tmp_name'], $reviewDir . $originalFilename)) {
            throw new RuntimeException('Could not save uploaded picture.');
        }

        $meta = [
            'stem' => $stem,
            'original' => $originalFilename,
            'contrast' => 1.0,
            'threshold' => null,
            'rotation' => 0,
            'brightness' => 1.0,
            'autoContrast' => false,
        ];
        $this->writeReviewMeta($meta);

        $this->regeneratePreview($converter, 1.0, null, $maxWidth, 0, 1.0, false);
    }

    /**
     * @return array{stem: string, original: string, contrast: float, threshold: ?int, rotation: int, brightness: float, autoContrast: bool, hasPreview: bool}|null
     */
    public function getPendingReview(): ?array
    {
        $meta = $this->readReviewMeta();
        if ($meta === null) {
            return null;
        }

        $meta['hasPreview'] = file_exists($this->reviewDir() . $meta['stem'] . '.jpg');
        return $meta;
    }

    /** Re-run conversion from the staged original with new settings. */
    public function regeneratePreview(
        ImageConverterRunner $converter,
        float $contrast,
        ?int $threshold,
        int $maxWidth = 384,
        int $rotation = 0,
        float $brightness = 1.0,
        bool $autoContrast = false
    ): void {
        $meta = $this->readReviewMeta();
        if ($meta === null) {
            throw new RuntimeException('No image is currently staged for review.');
        }

        $reviewDir = $this->reviewDir();
        $originalPath = $reviewDir . $meta['original'];
        $binPath = $reviewDir . $meta['stem'] . '.bin';
        $previewPath = $reviewDir . $meta['stem'] . '.jpg';

        $converter->convert($originalPath, $binPath, $previewPath, $contrast, $threshold, $maxWidth, $rotation, $brightness, $autoContrast);

        $meta['contrast'] = $contrast;
        $meta['threshold'] = $threshold;
        $meta['rotation'] = $rotation;
        $meta['brightness'] = $brightness;
        $meta['autoContrast'] = $autoContrast;
        $this->writeReviewMeta($meta);
    }

    /**
     * Finalize the staged review image into header_images/. If the stem is already
     * taken by an existing entry, a numeric suffix (_2, _3, ...) is appended instead
     * of overwriting it - duplicates can be told apart and cleaned up via Delete in
     * the UI rather than one silently replacing the other.
     *
     * @return string the stem the image was actually saved under
     */
    public function saveReview(): string
    {
        $meta = $this->readReviewMeta();
        if ($meta === null) {
            throw new RuntimeException('No image is currently staged for review.');
        }

        $reviewDir = $this->reviewDir();
        $binPath = $reviewDir . $meta['stem'] . '.bin';
        $previewPath = $reviewDir . $meta['stem'] . '.jpg';

        if (!file_exists($binPath)) {
            throw new RuntimeException('Staged image has no converted .bin file yet.');
        }

        $finalStem = $this->uniqueStem($meta['stem']);

        rename($binPath, $this->dir . $finalStem . '.bin');
        if (file_exists($previewPath)) {
            rename($previewPath, $this->dir . $finalStem . '.jpg');
        }

        $this->discardReview();

        return $finalStem;
    }

    /** Find a stem not already used by any file in header_images/, appending _2, _3, ... if needed. */
    private function uniqueStem(string $stem): string
    {
        if ((glob($this->dir . $stem . '.*') ?: []) === []) {
            return $stem;
        }

        $counter = 2;
        while ((glob($this->dir . $stem . '_' . $counter . '.*') ?: []) !== []) {
            $counter++;
        }
        return $stem . '_' . $counter;
    }

    /** Discard whatever is currently staged for review, if anything. */
    public function discardReview(): void
    {
        $reviewDir = $this->reviewDir();
        if (!file_exists($reviewDir)) {
            return;
        }
        foreach (array_filter(glob($reviewDir . '*') ?: [], 'is_file') as $file) {
            unlink($file);
        }
    }

    private function reviewDir(): string
    {
        return $this->dir . '.review/';
    }

    private function reviewMetaPath(): string
    {
        return $this->reviewDir() . 'meta.json';
    }

    /** @return array{stem: string, original: string, contrast: float, threshold: ?int, rotation: int, brightness: float, autoContrast: bool}|null */
    private function readReviewMeta(): ?array
    {
        $path = $this->reviewMetaPath();
        if (!file_exists($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeReviewMeta(array $meta): void
    {
        file_put_contents($this->reviewMetaPath(), (string) json_encode($meta));
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
