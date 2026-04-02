# Good Vibes Printer

Print Good Vibe Quotes using a Raspberry Pi and a Thermal Printer. Each time a physical button is pressed, another random Good Vibe Quote gets printed.

![Good Vibes Printer Setup](img.jpg)

## Prerequisites

- A Raspberry Pi 
- A connected printer
- A physical Push Button connected to the Pi

In this example, a BIXOLON SPP-R220 Mobile Thermal Printer is used

## Instraction For Use
### Quick Start Guide
1) Connect Thermal Printer via USB to Raspberry Pi and turn the printer on
2) Power on Raspberry Pi by plugging in the power cable
3) Wait until Bootup-Message is printed
4) A single push on the button will print out a random quote and image
5) To turn off printer, press and hold for at least 5 seconds

### Change Quotes, Welcome Text and Footer Text
All texts can be changed using a Web Interface. 
1) Connect to the Wireless Network "goodvibes" with passphrase "goodvibes123"
2) Open Web Browser and open page "192.168.4.1"
3) The Web Interface now allows uploading new texts
4) Restart Pi to apply changes

## Development Instruction

### Test Mobile Thermal Printer Printout

In this project, the [Codepage 850](https://de.wikipedia.org/wiki/Codepage_850) is used for printer commands.

You can use the following code snippet in terminal to print a sample text using the standard Unix/Linux printing command from the CUPS printing system:

```python
escpos_data = b"\x1b@\nHello receipt\n\x1dV\x00"
subprocess.run(["lp", "-o", "raw"], input=escpos_data, check=True)
```

> [!NOTE]
> Bixolon Thermal Printers are not supported by default. The installation of the CUPS drivers are covered in the next chapter.

### Install BIXOLON Thermal Printer CUPS driver

Download and follow the guide from BIXOLON to install CUPS drivers for various printers: [Software Linux CUPS Driver POS V1.5.9](https://www.bixolon.com/download_view.php?idx=24&s_key=Linux)

After installation is complete open the CUPS Webinterface: http://localhost:631 and add your specific printer under the Administrator panel.

### Setup Push Button
The code uses Physical Pin 10 (GPIO15) with an internal pull-up resistor, so the wiring should look like this:

`PIN 10 (GPIO15) -> Button -> PIN 6 (GND)`

Fur debug purposes, you could also press the `ENTER` key on your keyboard to trigger a printout.

### Add Server Support
Optionally, you can setup a hotspot and a PHP Server to upload new quotes via web interface.
- Setup Hotspot by calling `server/setup_hotspot.sh`
- Autostart Hotsport by putting `server/start_hotspot.sh` to `~/.bashrc`
- install php: `sudo apt-get install php`
- adopt rights: `chmod -R 777 /var/www/html`
- install php zip: `sudo apt-get install php5-zip`

All files uploaded via Webinterface will automatically be recognized after reboot.


### Execute the Code
Simply run `main.py` to run the code.

### Autostart Python File
open bashrc using `nano ~/.bashrc` and put your python code at the end of file.

```
# autostart kaffee quote
python /home/PATH_TO_PYTHON_FILE/main.py
```

