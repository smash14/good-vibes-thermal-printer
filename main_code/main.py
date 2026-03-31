# === Configuration ===
VERSION = "V2.0.0"
BUTTON_PIN = 15  # GPIO15 (physical pin 10)
LONG_PRESS_THRESHOLD = 5.0  # seconds
SHORT_PRESS_MIN_DURATION = 0.05  # 50ms minimum to filter false detections
CSV_FILE = "goodVibes.csv"
IMAGE_FOLDER = "header_images"

import sys
import time
import logging
import os
import glob
import random

if sys.platform == "linux":
    import platform_linux as platform
else:
    import platform_windows as platform

from print_buffer import PrintBuffer


# === Helper Functions ===
def get_random_image():
    """Get a random .bin image file from the header_images folder."""
    image_files = glob.glob(os.path.join(IMAGE_FOLDER, "*.bin"))
    if not image_files:
        logging.warning(f"No .bin files found in {IMAGE_FOLDER} folder.")
        return None
    return random.choice(image_files)


# === Main Logic ===
def main():
    logging.basicConfig(
        filename='logfile.log', filemode='a', level=logging.INFO,
        format='%(asctime)s - %(levelname)s - %(message)s', datefmt='%d-%b-%y %H:%M:%S'
    )
    logging.getLogger().addHandler(logging.StreamHandler())
    logging.info(f"============================ {VERSION} ============================")
    logging.info("Start Main Application")

    platform.cleanup_printer_queue()
    logging.info("Good Vibes Printer ready.")
    platform.setup_gpio(BUTTON_PIN)

    buffer = PrintBuffer(CSV_FILE, platform.print_raw, platform.print_image)
    buffer.print_bootup_lines(VERSION)
    # buffer.print_all_quotes()

    try:
        while True:
            press_duration = platform.wait_for_button(BUTTON_PIN)

            if press_duration >= LONG_PRESS_THRESHOLD:
                logging.info("Long press detected: shutting down.")
                buffer.set_text("Shutting down all the good vibes...")
                buffer.print()
                platform.shutdown()
            elif press_duration >= SHORT_PRESS_MIN_DURATION:
                logging.info("Short press detected: printing coffee quote.")
                buffer.print_welcome_lines()
                
                random_image = get_random_image()
                if random_image:
                    buffer.print_image(random_image)
                    logging.info(f"Selected image: {random_image}")
                
                buffer.print_random_general_kaffee_quote()
                buffer.print_finish_line()
                logging.info(f"Selected line: {buffer.text}")
                time.sleep(3)
                platform.cleanup_printer_queue()

            time.sleep(0.5)  # Debounce delay

    except KeyboardInterrupt:
        logging.info("Interrupted by user.")
    finally:
        platform.gpio_cleanup()


if __name__ == "__main__":
    main()
