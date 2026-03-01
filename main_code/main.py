# === Configuration ===
SYSTEM_IS_RPI = 0
BUTTON_PIN = 15  # GPIO15 (physical pin 10)
CSV_FILE = "goodVibes.csv"
IMAGE_FILE = "header_out.bin"
LONG_PRESS_THRESHOLD = 5.0  # seconds
VERSION = "V2.0.0"

import sys

if "linux" in sys.platform:
    SYSTEM_IS_RPI = 1
else:
    SYSTEM_IS_RPI = 0
    print(f"system is no Raspberry Pi ({sys.platform})")
if SYSTEM_IS_RPI:
    import RPi.GPIO as GPIO
import time
import csv
import random
import subprocess
import os
import logging


# === Class to manage print content and data ===
class PrintBuffer:
    def __init__(self, filename):
        self.lines = self._load_csv(filename)
        self.text = ""
        self.text_invert_style = b"\x1D\x42\x00"  # no invert
        self.text_align = b"\x1B\x61\x00"  # align left
        self.font_size = b"\x1D\x21\x00"  # normal 
        self.font_bold = b"\x1B\x45\x00"  # bold off
        self.feed_lines = b"\x1B\x64\x00"  # 0 feed lines after text

    @staticmethod
    def _load_csv(filename):
        try:
            with open(filename, newline='', encoding='utf-8') as file:
                reader = csv.reader(file)
                lines = [", ".join(row) for row in reader if row]
                lines = [line.replace("\\n", "\n") for line in lines]
            print(f"Loaded {len(lines)} lines from CSV.")
            return lines
        except Exception as e:
            print(f"Failed to load CSV: {e}")
            return ["<CSV load error>"]

    def select_random_line(self):
        if self.lines:
            self.text = random.choice(self.lines)
        else:
            self.text = "<No data available>"

    def set_text(self, new_text):
        self.text = new_text

    def set_font_size(self, new_font_size="normal"):
        if new_font_size == "normal":
            self.font_size = b"\x1D\x21\x00"
        elif new_font_size == "big":
            self.font_size = b"\x1D\x21\x10"
        elif new_font_size == "bigger":
            self.font_size = b"\x1D\x21\x33"
        else:
            self.font_size = b"\x1D\x21\x00"  # normal

    def set_font_bold(self, bold_on):
        if bold_on:
            self.font_bold = b"\x1B\x45\x01"
        else:
            self.font_bold = b"\x1B\x45\x00"

    def set_text_to_invert(self, invert_on):
        if invert_on:
            self.text_invert_style = b"\x1D\x42\x01"
        else:
            self.text_invert_style = b"\x1D\x42\x00"

    def set_text_align(self, align="left"):
        if align == "left":
            self.text_align = b"\x1B\x61\x00"  # align left
        elif align == "center":
            self.text_align = b"\x1B\x61\x01"  # align center
        elif align == "right":
            self.text_align = b"\x1B\x61\x02"  # align right
        else:
            self.text_align = b"\x1B\x61\x00"  # align left

    def set_feed_lines(self, n):
        if not (0 <= n <= 255):
            raise ValueError("Line count must be between 0 and 255")
        self.feed_lines = b'\x1B\x64' + bytes([n])

    def set_text_stars(self):
        self.text = "*****************************"

    def _reset_font_styles(self):
        self.text = ""
        self.text_invert_style = b"\x1D\x42\x00"  # no invert
        self.text_align = b"\x1B\x61\x00"  # align left
        self.font_size = b"\x1D\x21\x00"  # normal
        self.font_bold = b"\x1B\x45\x00"  # bold off
        self.feed_lines = b"\x1B\x64\x00"  # 0 feed lines after text

    def print_all_quotes(self):
        for line in self.lines:
            self._reset_font_styles()
            self.set_text_align("center")
            self.set_text(line)
            self.print()
            self.set_text_stars()
            self.print()

    def print_bootup_lines(self):
        self.set_text(f"All good vibes loaded\n{VERSION}")
        self.set_feed_lines(5)
        self.print()

    def print_welcome_lines(self):
        self._reset_font_styles()
        self.set_text_align("center")
        self.set_font_size("big")
        self.set_text("Good Vibes Only\n")
        self.print()
        self.set_font_bold(False)
        self.set_font_size("normal")
        self.set_text("~~~ <3 ~~~")
        self.set_feed_lines(2)
        self.print()
        self.set_feed_lines(0)

    def print_random_general_kaffee_quote(self):
        self._reset_font_styles()
        self.set_text_align("center")
        self.set_text_stars()
        self.print()
        self.select_random_line()
        self.print()
        self.set_text_stars()
        self.set_feed_lines(3)
        self.print()

    def print_finish_line(self):
        self._reset_font_styles()
        list_of_words = [
            "wonderful",
            "fantastic",
            "beautiful",
            "really good",
            "outstanding",
            "friendly",
            "sunny",
            "successful",
        ]
        text = random.choice(list_of_words)
        self.set_text_align("center")
        self.set_text(f"Have a\n{text} day! <3")
        self.set_feed_lines(5)
        self.print()

    def print(self):
        try:
            printer_text = self.text.encode("cp850")
        except Exception as e:
            printer_text = b"error transcoding text"
            logging.error(f"Text encoding failed with exception {e}")
        printer_text_with_style = self.text_align + self.font_bold + self.text_invert_style + self.font_size + printer_text + self.feed_lines
        if SYSTEM_IS_RPI:
            try:
                subprocess.run(["lp", "-o", "raw"], input=printer_text_with_style, check=True)
                logging.info("Printed successfully.")
            except Exception as e:
                logging.error("Printing failed:", e)
        else:
            logging.info(printer_text_with_style)

    def print_image(self):
        subprocess.run(["lp", "-o", "raw", IMAGE_FILE])
        self._reset_font_styles()
        self.set_feed_lines(2)
        self.print()


# === GPIO Setup ===
def setup_gpio():
    if SYSTEM_IS_RPI:
        GPIO.setmode(GPIO.BCM)
        GPIO.setup(BUTTON_PIN, GPIO.IN, pull_up_down=GPIO.PUD_UP)


# === Shutdown Function ===
def shutdown():
    if SYSTEM_IS_RPI:
        time.sleep(1)
        os.system("sudo shutdown now")


def cleanup_printer_queue():
    if SYSTEM_IS_RPI:
        print_command = ["cancel", '-a', '-x']
        try:
            result = subprocess.run(print_command, stdout=subprocess.PIPE).stdout.decode('utf-8')
        except Exception as e:
            logging.error(f"Error while deleting pending print jobs: {e}")
            raise

        logging.info(f"Pending print jobs have been cancelled with return value: {result}")

        print_command = ["lpstat", '-o']
        try:
            result = subprocess.run(print_command, stdout=subprocess.PIPE).stdout.decode('utf-8')
        except Exception as e:
            logging.error(f"Error while gathering pending print jobs: {e}")
            raise
        if result:
            logging.warning(f"It seems there are still pending print jobs after trying to cancel them: {result}")
        else:
            logging.info(f"No pending print jobs left {result}")
        return True
    else:
        logging.info("System is no Raspberry Pi, skip deleting printer queue")


# === Main Logic ===
def main():
    logging.basicConfig(filename='logfile.log', filemode='a', level=logging.INFO,
                        format='%(asctime)s - %(levelname)s - %(message)s', datefmt='%d-%b-%y %H:%M:%S')
    logging.getLogger().addHandler(logging.StreamHandler())
    logging.info(f"============================ {VERSION} ============================")
    logging.info("Start Main Application")
    cleanup_printer_queue()
    logging.info("Good Vibes Printer ready.")
    setup_gpio()
    buffer = PrintBuffer(CSV_FILE)

    buffer.print_bootup_lines()
    # buffer.print_all_quotes()

    if SYSTEM_IS_RPI:
        try:
            while True:
                GPIO.wait_for_edge(BUTTON_PIN, GPIO.FALLING)
                press_start = time.time()

                # Wait for button release
                while GPIO.input(BUTTON_PIN) == GPIO.LOW:
                    time.sleep(0.01)

                press_duration = time.time() - press_start

                if press_duration >= LONG_PRESS_THRESHOLD:
                    logging.info("Long press detected: shutting down.")
                    buffer.set_text("Shutting down all the good vibes...")
                    buffer.print()
                    shutdown()
                else:
                    logging.info("Short press detected: printing coffee quote.")
                    buffer.print_welcome_lines()
                    buffer.print_image()
                    buffer.print_random_general_kaffee_quote()
                    buffer.print_finish_line()
                    logging.info(f"Selected line: {buffer.text}")
                    time.sleep(3)
                    cleanup_printer_queue()

                time.sleep(0.5)  # Debounce delay

        except KeyboardInterrupt:
            logging.info("Interrupted by user.")
        finally:
            GPIO.cleanup()


if __name__ == "__main__":
    main()
