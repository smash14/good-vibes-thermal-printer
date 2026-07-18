<?php

// Owns logfile.log — main.py's run log, viewable/deletable from the admin page.
final class Logfile
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function content(): string
    {
        return file_get_contents($this->path);
    }

    public function delete(): void
    {
        if (!$this->exists()) {
            throw new RuntimeException('Logfile does not exist.');
        }
        unlink($this->path);
    }
}
