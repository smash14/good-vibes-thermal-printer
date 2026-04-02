#!/bin/bash
# Raspberry Pi Hotspot Setup Script - Run once to configure
# This script installs and configures everything needed for a WiFi hotspot

set -e  # Exit on error

echo "=== Raspberry Pi Hotspot Setup ==="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root. Use: sudo bash setup_hotspot.sh"
    exit 1
fi

# Update system packages
echo "Updating system packages..."
apt-get update
apt-get upgrade -y

# Install required packages
echo "Installing required packages..."
apt-get install -y hostapd dnsmasq

# Backup original configurations
echo "Backing up original configurations..."
[ ! -f /etc/hostapd/hostapd.conf.bak ] && cp /etc/hostapd/hostapd.conf /etc/hostapd/hostapd.conf.bak || true
[ ! -f /etc/dnsmasq.conf.bak ] && cp /etc/dnsmasq.conf /etc/dnsmasq.conf.bak || true
[ -f /etc/dhcpcd.conf ] && [ ! -f /etc/dhcpcd.conf.bak ] && cp /etc/dhcpcd.conf /etc/dhcpcd.conf.bak || true

# Configure hostapd
echo "Configuring hostapd..."
cat > /etc/hostapd/hostapd.conf << 'EOF'
interface=wlan0
driver=nl80211
ssid=goodvibes
hw_mode=g
channel=7
wmm_enabled=0
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=goodvibes123
wpa_key_mgmt=WPA-PSK
wpa_pairwise=CCMP
wpa_group_rekey=86400
EOF

echo "✓ hostapd configured (SSID: goodvibes, Password: goodvibes123)"

# Stop dnsmasq if it's running
systemctl stop dnsmasq 2>/dev/null || true

# Configure dnsmasq - create new minimal config
cat > /etc/dnsmasq.conf << 'EOF'
port=53
interface=wlan0
bind-interfaces
dhcp-range=192.168.4.2,192.168.4.20,255.255.255.0,24h
dhcp-option=3,192.168.4.1
dhcp-option=6,192.168.4.1
log-dhcp
EOF

echo "✓ dnsmasq configured"

# Configure dhcpcd to exclude wlan0 (we manage it manually)
if [ -f /etc/dhcpcd.conf ]; then
    echo "Configuring dhcpcd to exclude wlan0..."

    # Remove any existing wlan0 config from dhcpcd.conf
    sed -i '/^interface wlan0/,/^$/d' /etc/dhcpcd.conf

    # Add config to exclude wlan0 entirely
    cat >> /etc/dhcpcd.conf << 'EOF'

# Hotspot configuration - managed by start_hotspot.sh
denyinterfaces wlan0
EOF
    echo "✓ dhcpcd configured to exclude wlan0"
fi

# Enable IP forwarding
echo "Enabling IP forwarding..."
grep -q "net.ipv4.ip_forward=1" /etc/sysctl.conf || echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
sysctl -p > /dev/null

# Configure iptables NAT
echo "Configuring iptables for NAT..."
iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE 2>/dev/null || true
iptables -A FORWARD -i eth0 -o wlan0 -m state --state RELATED,ESTABLISHED -j ACCEPT 2>/dev/null || true
iptables -A FORWARD -i wlan0 -o eth0 -j ACCEPT 2>/dev/null || true

# Save iptables rules if iptables-persistent is installed
if command -v iptables-save >/dev/null; then
    mkdir -p /etc/iptables
    iptables-save > /etc/iptables/rules.v4 2>/dev/null || true
    echo "✓ iptables NAT configured and saved"
else
    echo "✓ iptables NAT configured"
fi

# Disable services from auto-start (will be started by start_hotspot.sh)
echo "Disabling hotspot services from auto-start..."
systemctl disable hostapd 2>/dev/null || true
systemctl disable dnsmasq 2>/dev/null || true

# Configure passwordless sudo for hotspot commands
echo "Configuring passwordless sudo access..."
cat > /etc/sudoers.d/hotspot << 'EOF'
# Allow hotspot commands without password
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl start hostapd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl stop hostapd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl restart hostapd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl start dnsmasq
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl stop dnsmasq
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl restart dnsmasq
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl start dhcpcd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl stop dhcpcd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl restart dhcpcd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl is-active hostapd
%sudo ALL=(ALL) NOPASSWD: /bin/systemctl is-active dnsmasq
%sudo ALL=(ALL) NOPASSWD: /sbin/ip addr add 192.168.4.1/24 dev wlan0
%sudo ALL=(ALL) NOPASSWD: /sbin/ip link set wlan0 up
EOF
chmod 440 /etc/sudoers.d/hotspot

echo "✓ Passwordless sudo configured"

echo ""
echo "=== Setup Complete ==="
echo "The hotspot is now configured. To start it, run: ./start_hotspot.sh"
echo "To make it start automatically after boot, add to ~/.bashrc:"
echo "  source ~/goodvibes/start_hotspot.sh"
echo ""
echo "Configuration Details:"
echo "  WiFi SSID: goodvibes"
echo "  WiFi Password: goodvibes123"
echo "  Hotspot IP: 192.168.4.1"
echo "  DHCP Range: 192.168.4.2 - 192.168.4.20"
echo ""
echo "Note: Your user must be in the 'sudo' group for passwordless commands to work."
