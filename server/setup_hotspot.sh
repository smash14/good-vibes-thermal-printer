#!/bin/bash
# Raspberry Pi Wireless Hotspot Setup Script
# This script sets up a wireless hotspot with SSID "goodvibes" and passphrase "728349028394"
# Run this once with: sudo bash setup_hotspot.sh

set -e

SSID="goodvibes"
PASSPHRASE="728349028394"
INTERFACE="wlan0"

echo "========================================="
echo "Raspberry Pi Hotspot Setup"
echo "SSID: $SSID"
echo "========================================="

# Update system
echo "Updating system packages..."
sudo apt-get update
sudo apt-get install -y hostapd dnsmasq iptables-persistent

# Stop services before configuration
echo "Stopping services..."
sudo systemctl stop hostapd || true
sudo systemctl stop dnsmasq || true
sudo systemctl stop wpa_supplicant || true

# Bring down wlan0 if it's up
echo "Bringing down $INTERFACE..."
sudo ip link set $INTERFACE down || true
sudo ip addr flush dev $INTERFACE || true

# Configure hostapd
echo "Configuring hostapd..."
sudo tee /etc/hostapd/hostapd.conf > /dev/null <<EOF
interface=$INTERFACE
ssid=$SSID
hw_mode=g
channel=7
wmm_enabled=0
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=$PASSPHRASE
wpa_key_mgmt=WPA-PSK
wpa_pairwise=CCMP
wpa_ptk_rekey=600
EOF

# Set hostapd config file path
echo "Setting hostapd configuration path..."
sudo sed -i 's|^#DAEMON_CONF=.*|DAEMON_CONF="/etc/hostapd/hostapd.conf"|' /etc/default/hostapd

# Configure dnsmasq
echo "Configuring dnsmasq..."
sudo cp /etc/dnsmasq.conf /etc/dnsmasq.conf.bak
sudo tee /etc/dnsmasq.conf > /dev/null <<EOF
interface=$INTERFACE
dhcp-range=192.168.4.2,192.168.4.20,255.255.255.0,24h
dhcp-option=option:router,192.168.4.1
EOF

# Configure static IP for wlan0
echo "Configuring static IP for $INTERFACE..."
sudo tee -a /etc/dhcpcd.conf > /dev/null <<EOF

# Static IP for wireless hotspot
interface $INTERFACE
static ip_address=192.168.4.1/24
nohook wpa_supplicant
EOF

# Bring up interface with static IP
echo "Bringing up $INTERFACE with static IP..."
sudo ip link set $INTERFACE up
sudo ip addr add 192.168.4.1/24 dev $INTERFACE || true

# Enable IP forwarding
echo "Enabling IP forwarding..."
sudo sed -i 's/^#net.ipv4.ip_forward=1/net.ipv4.ip_forward=1/' /etc/sysctl.conf
sudo sysctl -p

# Configure iptables for NAT
echo "Configuring iptables..."
sudo iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
sudo iptables -A FORWARD -i eth0 -o $INTERFACE -m state --state RELATED,ESTABLISHED -j ACCEPT
sudo iptables -A FORWARD -i $INTERFACE -o eth0 -j ACCEPT

# Save iptables rules
sudo iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null

# Unmask services (in case they are masked)
echo "Unmasking services..."
sudo systemctl unmask hostapd || true
sudo systemctl unmask dnsmasq || true

# Enable services at boot
echo "Enabling services at boot..."
sudo systemctl enable hostapd
sudo systemctl enable dnsmasq

# Start services
echo "Starting services..."
sudo systemctl restart dhcpcd || echo "Warning: dhcpcd not found, skipping..."

# Give the interface a moment to be ready
sleep 2

sudo systemctl start dnsmasq || echo "Warning: dnsmasq failed to start"
if ! sudo systemctl start hostapd; then
    echo "ERROR: hostapd failed to start!"
    echo "Troubleshooting: Check 'sudo systemctl status hostapd' or 'sudo journalctl -xeu hostapd.service'"
    echo "Common issues:"
    echo "  - wpa_supplicant is still running on wlan0"
    echo "  - Another wireless manager is controlling wlan0"
    echo "  - wlan0 is not available on this device"
    exit 1
fi

echo "========================================="
echo "Hotspot setup complete!"
echo "SSID: $SSID"
echo "Passphrase: $PASSPHRASE"
echo "IP Address: 192.168.4.1"
echo "========================================="
echo "Optional: Add this to ~/.bashrc to autostart:"
echo "  bash ~/start_hotspot.sh"
