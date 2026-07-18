#!/bin/bash
# Raspberry Pi Power Control Setup Script - Run once, as root.
# Grants the web server user (www-data) passwordless sudo access to exactly two
# commands - shutdown and reboot - so the Settings page in the web UI can offer
# those actions directly, without granting any other privileges.

set -e

if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root. Use: sudo bash setup_power_control.sh"
    exit 1
fi

# Must match config.php's 'systemctlBinary' entry - the sudoers grant below only
# covers this exact path, and server/lib/SystemControl.php invokes that config
# value directly (no PATH lookup), so a mismatch here means the buttons will fail.
SYSTEMCTL_PATH="/usr/bin/systemctl"
if [ ! -x "$SYSTEMCTL_PATH" ]; then
    echo "$SYSTEMCTL_PATH not found - check config.php's 'systemctlBinary' matches your system."
    exit 1
fi

echo "Configuring passwordless sudo for www-data (poweroff/reboot only)..."
cat > /etc/sudoers.d/goodvibes-power << EOF
# Allow the web server to shut down or reboot the Pi from the Settings page,
# without granting any other privileges.
www-data ALL=(ALL) NOPASSWD: $SYSTEMCTL_PATH poweroff, $SYSTEMCTL_PATH reboot
EOF
chmod 440 /etc/sudoers.d/goodvibes-power
visudo -c -f /etc/sudoers.d/goodvibes-power

echo "✓ Passwordless sudo configured for www-data (poweroff/reboot only)."
