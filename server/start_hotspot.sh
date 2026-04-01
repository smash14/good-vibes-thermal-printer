#!/bin/bash
# Start Wireless Hotspot
# This script starts the hostapd and dnsmasq services for the wireless hotspot
# Usage: bash start_hotspot.sh

echo "Starting wireless hotspot 'goodvibes'..."
sudo systemctl start hostapd
sudo systemctl start dnsmasq

# Wait a moment for services to start
sleep 2

# Check status
echo ""
echo "Hotspot Status:"
sudo systemctl status hostapd --no-pager | head -3
echo ""
echo "Access the hotspot at 192.168.4.1"
