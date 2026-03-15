# Good Vibes Printer

Print Good Vibe Quotes using a Raspberry Pi and a Thermal Printer. Each time a physical button is pressed, another random Good Vibe Quote gets printed.

## prerequisites

- A Raspberry Pi 
- A connected printer
- A physical Push Button connected to the Pi

In this example, a BIXOLON SPP-R220 Mobile Thermal Printer is used

## Test Mobile Thermal Printer Printout

In this project, the [Codepage 850](https://de.wikipedia.org/wiki/Codepage_850) is used for printer commands.

You can use the following code snippet in terminal to print a sample text using the standard Unix/Linux printing command from the CUPS printing system:

```python
escpos_data = b"\x1b@\nHello receipt\n\x1dV\x00"
subprocess.run(["lp", "-o", "raw"], input=escpos_data, check=True)
```

> [!NOTE]
> Bixolon Thermal Printers are not supported by default. The installation of the CUPS drivers are covered in the next chapter.

## Install BIXOLON Thermal Printer CUPS driver

Download and follow the guide from BIXOLON to install CUPS drivers for various printers: [Software Linux CUPS Driver POS V1.5.9](https://www.bixolon.com/download_view.php?idx=24&s_key=Linux)

After installation is complete open the CUPS Webinterface: http://localhost:631 and add your specific printer under the Administrator panel.

## Setup Push Button
The code uses Physical Pin 10 (GPIO15) with an internal pull-up resistor, so the wiring should look like this:

`PIN 10 (GPIO15) -> Button -> PIN 6 (GND)`

Fur debug purposes, you could also press the `ENTER` key on your keyboard to trigger a printout.

## Execute the code
Simply run `main.py` to run the code.


