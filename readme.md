# Good Vibes Printer

Print Good Vibes Quotes using a Raspberry Pi and a Thermal Printer. Each time a physical button is pressed, another random Good Vibes Quote gets printed.

![Good Vibes Printer Setup](img.jpg)

## Prerequisites

- A Raspberry Pi 
- A connected printer
- A physical Push Button connected to the Pi

In this example, a BIXOLON SPP-R220 Mobile Thermal Printer is used

## Instruction For Use
### Quick Start Guide
1) Connect Thermal Printer via USB to Raspberry Pi and turn the printer on
2) Power on Raspberry Pi by plugging in the power cable
3) Wait until Bootup-Message is printed
4) A single push on the button will print out a random quote and image
5) To turn off printer, press and hold for at least 5 seconds

### Print all available quotes and images
For debug purposes, all available quotes and images can be printed at once. This is useful to check for style errors, e.g. if a quote does not fit in one line.

To do so, press and hold the push button for at least 5 seconds within 20 seconds after the welcome line has been printed.

### Create new Quotes
Quotes can be created by editing the `main_code/goodVibes.csv` file. Please note that there is no automatic new line if a quote is too long. Instead, use `\n` within a quote to mark a new line.

After editing is done, check below on how to update the files on the Pi

### Create new images
Before each quote, an image can be printed. New images can be added by uploading any common picture file (jpg, png, gif, bmp, webp, tiff - max 10MB) via the Web Interface. The upload is immediately converted to a black & white preview, which is shown on the page along with rotation, auto-contrast, brightness, contrast, and threshold controls - rotate widescreen/landscape photos 90° so they print along the paper's length instead of being squeezed down to the printer's width, try "Auto-contrast" as a quick one-click fix for flat/washed-out photos, adjust the other settings as needed, and click "Regenerate Preview" as many times as you like, then click "Save Image" once you're happy with the result (or "Discard" to cancel). If an image with the same name already exists, saving keeps both as separate images instead of overwriting the existing one - unwanted duplicates can be removed with the Delete button. Only after saving does the image become part of the header image rotation - while the main script is running, it picks up the new `.bin` file and prints it once, announced with a short header line, so you know it's ready. If the script isn't running yet, that first announcement print instead happens the next time it starts.

### Change other translations
Other translations, like the welcome line, can be changed by editing the `main_code/strings.json` file. Like quotes and images, changes are picked up automatically while the main script is running, without needing a restart.

### Upload changes to Raspberry Pi
All texts can be changed using a Web Interface. 
> [!NOTE]
> The web interface gets disabled automatically 20 minutes after boot up.
1) Connect to the Wireless Network "goodvibes" with passphrase "goodvibes123"
2) Open Web Browser and navigate to "192.168.4.1"
3) The Web Interface now allows uploading new texts
4) Changes are picked up automatically within a short time while the printer is running - no restart needed. New quotes/images are also printed once automatically to confirm they arrived (this can be turned off, and the check interval adjusted, via the `runtime_print_on_update` / `runtime_poll_interval_seconds` entries in `strings.json`).

The Settings page also lets you tick "print_welcome_enabled" / "print_quotes_enabled" / "print_images_enabled" / "print_finish_enabled" on or off to control which parts of a button-press printout (welcome lines, quote, header image, sign-off line) actually get printed - all four are on by default.

The Settings page also has a "Power Control" section to reboot or shut down the Pi directly from the browser, without needing SSH access - see "Add Server Support" below for the one-time setup this requires.

## Development Instruction

### Test Mobile Thermal Printer Printout

In this project, the [Codepage 850](https://de.wikipedia.org/wiki/Codepage_850) is used for printer commands.

You can use the following code snippet in terminal to print a sample text using the standard Unix/Linux printing command from the CUPS printing system:

```python
import subprocess

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

For debug purposes, you could also press the `ENTER` key on your keyboard to trigger a printout.

### Add Server Support
Optionally, you can setup a hotspot and a PHP Server to upload new quotes via web interface.
- Setup Hotspot by calling `server/setup_hotspot.sh`
- Autostart Hotspot by putting `server/start_hotspot.sh` to `~/.bashrc`
- The hotspot will be active for 20 minutes after each system booot up
- install php: `sudo apt-get install php`
- adopt rights: `chmod -R 777 /var/www/html`
- install php zip: `sudo apt-get install php5-zip`
- the PHP admin page shells out to `python3` (running `server/scripts/image_converter.py`) to do interactive image conversions when reviewing an upload - make sure `python3` and Pillow are available to whichever user Apache runs as (usually `www-data`), e.g. `sudo apt-get install python3-pil`
- to enable the Settings page's Reboot/Shutdown buttons, run `sudo bash server/setup_power_control.sh` once - this grants `www-data` passwordless sudo access to exactly `systemctl poweroff` and `systemctl reboot`, nothing else. Without this step, clicking either button will show a permission error.

While `main.py` is running, all files uploaded via Webinterface (quotes, images, strings.json) are automatically recognized within a short time (30 seconds by default, see `runtime_poll_interval_seconds` in `strings.json`) - no reboot needed. If the script isn't running yet, everything pending is picked up the next time it starts.

### Increase PHP upload limits
The default Debian/Apache PHP install caps uploads well below the Web Interface's
10MB picture-upload limit (`upload_max_filesize = 2M`, `post_max_size = 8M`). A file
over these limits fails silently: PHP empties `$_POST`/`$_FILES` with no error at all,
so the page just reloads with no message and no file saved.

To match the app's 10MB limit, edit `/etc/php/<version>/apache2/php.ini` (e.g.
`/etc/php/8.2/apache2/php.ini`) and raise both values, then restart Apache:

```
upload_max_filesize = 12M
post_max_size = 13M
```

```
sudo systemctl restart apache2
```

### Execute the Code
Simply run `main_code/main.py` to run the code.

### Autostart Python File
open bashrc using `nano ~/.bashrc` and put your python code at the end of file.

```
# autostart kaffee quote
python /home/PATH_TO_PYTHON_FILE/main.py
```

