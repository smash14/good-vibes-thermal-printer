<?php

// Shuts down or reboots the Raspberry Pi. Relies on a passwordless sudo grant
// scoped to exactly these two commands for the www-data user, installed once by
// server/setup_power_control.sh (run as root) - see readme.md "Add Server Support".
final class SystemControl
{
    private string $systemctlBinary;

    public function __construct(string $systemctlBinary)
    {
        $this->systemctlBinary = $systemctlBinary;
    }

    public function shutdown(): void
    {
        $this->run(['sudo', $this->systemctlBinary, 'poweroff']);
    }

    public function reboot(): void
    {
        $this->run(['sudo', $this->systemctlBinary, 'reboot']);
    }

    private function run(array $command): void
    {
        // $command is passed as an array, so PHP execs the binary directly with no
        // shell involved.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start system command.');
        }

        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . trim($stderr));
        }
    }
}
