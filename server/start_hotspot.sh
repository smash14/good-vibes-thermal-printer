#!/bin/bash
# Raspberry Pi Hotspot Startup Script
# Run this to start the hotspot (can be added to ~/.bashrc for auto-start)

# Wait for wlan0 to appear (in case called early on boot)
echo "[Hotspot] Waiting for wlan0 interface..."
RETRY=0
while ! ip link show wlan0 > /dev/null 2>&1; do
    RETRY=$((RETRY + 1))
    if [ $RETRY -gt 30 ]; then
        echo "[Hotspot] Error: wlan0 interface not found after 30 seconds"
        return 2>/dev/null || exit 1
    fi
    sleep 1
done
echo "[Hotspot] wlan0 interface found"

# Check if already running
if sudo /bin/systemctl is-active --quiet hostapd 2>/dev/null; then
    # Verify it's actually working by checking if IP is set
    if ip addr show wlan0 | grep -q "192.168.4.1"; then
        return 0 2>/dev/null || exit 0
    fi
fi

echo "[Hotspot] Starting WiFi hotspot..."

# Stop services first to clean up
echo "[Hotspot] Stopping existing services..."
sudo /bin/systemctl stop hostapd 2>/dev/null || true
sudo /bin/systemctl stop dnsmasq 2>/dev/null || true

# Ensure wlan0 is down first, then bring it up fresh
echo "[Hotspot] Configuring wlan0..."
sudo /sbin/ip link set wlan0 down 2>/dev/null || true
sleep 1
sudo /sbin/ip link set wlan0 up 2>/dev/null || true

# Flush any existing addresses and set static IP
sudo /sbin/ip addr flush dev wlan0 2>/dev/null || true
sleep 1
sudo /sbin/ip addr add 192.168.4.1/24 dev wlan0 2>/dev/null

# Restart dhcpcd to apply our config (it should skip wlan0 due to our config)
echo "[Hotspot] Restarting network services..."
sudo /bin/systemctl restart dhcpcd 2>/dev/null || true
sleep 2

# Verify wlan0 is up and has IP
if ! ip addr show wlan0 | grep -q "192.168.4.1"; then
    echo "[Hotspot] ✗ Failed to configure wlan0"
    ip addr show wlan0
    return 1 2>/dev/null || exit 1
fi

# Start hostapd first (needs to be up before dnsmasq serves)
echo "[Hotspot] Starting hostapd..."
sudo /bin/systemctl start hostapd 2>/dev/null
sleep 2

# Start dnsmasq after hostapd is running
echo "[Hotspot] Starting dnsmasq..."
sudo /bin/systemctl start dnsmasq 2>/dev/null
sleep 2

# Check if services are running
HOSTAPD_OK=$(sudo /bin/systemctl is-active --quiet hostapd 2>/dev/null && echo "yes" || echo "no")
DNSMASQ_OK=$(sudo /bin/systemctl is-active --quiet dnsmasq 2>/dev/null && echo "yes" || echo "no")

if [ "$HOSTAPD_OK" = "yes" ] && [ "$DNSMASQ_OK" = "yes" ]; then
    echo "[Hotspot] ✓ Hotspot started successfully"
    echo "[Hotspot] Connect to 'goodvibes' with password 'goodvibes123'"
    echo "[Hotspot] Hotspot IP: 192.168.4.1"
    echo "[Hotspot] Hotspot will auto-disable in 20 minutes"
    
    # Schedule automatic disable after 20 minutes (1200 seconds)
    (
        sleep 1200
        echo "[Hotspot] Auto-disabling hotspot after 20 minutes..."
        sudo /bin/systemctl stop hostapd 2>/dev/null || true
        sudo /bin/systemctl stop dnsmasq 2>/dev/null || true
        echo "[Hotspot] Hotspot disabled"
    ) &
    
    return 0 2>/dev/null || exit 0
else
    echo "[Hotspot] ✗ Failed to start hotspot"
    echo "[Hotspot] hostapd: $HOSTAPD_OK, dnsmasq: $DNSMASQ_OK"
    echo "[Hotspot] Checking hostapd status..."
    sudo /bin/systemctl status hostapd 2>&1 | head -5
    echo "[Hotspot] Checking dnsmasq status..."
    sudo /bin/systemctl status dnsmasq 2>&1 | head -5
    return 1 2>/dev/null || exit 1
fi
