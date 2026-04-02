# === Configuration ===
VERSION = "V3.0.0"
BUTTON_PIN = 15  # GPIO15 (physical pin 10)
LONG_PRESS_THRESHOLD = 5.0  # seconds
SHORT_PRESS_MIN_DURATION = 0.05  # 50ms minimum to filter false detections
CSV_FILE = "goodVibes.csv"
IMAGE_FOLDER = "header_images"
STRINGS_FILE = "strings.json"
LOGFILE = "logfile.log"

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
def resolve_file_paths():
    """Resolve file paths. On Linux, prefer /var/www/html if files exist there. On Windows, prefer C:\\xampp\\htdocs."""
    global CSV_FILE, IMAGE_FOLDER, STRINGS_FILE, LOGFILE
    
    if sys.platform == "linux":
        www_path = "/var/www/html"
    else:  # Windows
        www_path = "C:\\xampp\\htdocs"
    
    csv_candidate = os.path.join(www_path, CSV_FILE)
    image_candidate = os.path.join(www_path, IMAGE_FOLDER)
    strings_candidate = os.path.join(www_path, STRINGS_FILE)
    logfile_candidate = os.path.join(www_path, LOGFILE)
    
    # Check each file/folder independently in www_path
    # Can not use logger here, as it is not set up yet
    if os.path.exists(csv_candidate):
        CSV_FILE = csv_candidate
        print(f"Using CSV file from {www_path}: {CSV_FILE}")
    if os.path.exists(image_candidate):
        IMAGE_FOLDER = image_candidate
        print(f"Using image folder from {www_path}: {IMAGE_FOLDER}")
    if os.path.exists(strings_candidate):
        STRINGS_FILE = strings_candidate
        print(f"Using strings file from {www_path}: {STRINGS_FILE}")
    if os.path.exists(www_path):
        LOGFILE = logfile_candidate
        print(f"Using logfile from {www_path}: {LOGFILE}")


def get_random_image():
    """Get a random .bin image file from the header_images folder."""
    image_files = glob.glob(os.path.join(IMAGE_FOLDER, "*.bin"))
    if not image_files:
        logging.warning(f"No .bin files found in {IMAGE_FOLDER} folder.")
        return None
    return random.choice(image_files)


# === Main Logic ===
def main():
    resolve_file_paths()
    logging.basicConfig(
        filename=LOGFILE, filemode='a', level=logging.INFO,
        format='%(asctime)s - %(levelname)s - %(message)s', datefmt='%d-%b-%y %H:%M:%S'
    )
    logging.getLogger().addHandler(logging.StreamHandler())
    logging.info(f"============================ {VERSION} ============================")
    logging.info("Start Main Application")

    platform.cleanup_printer_queue()
    logging.info("Good Vibes Printer ready.")
    platform.setup_gpio(BUTTON_PIN)

    buffer = PrintBuffer(CSV_FILE, platform.print_raw, platform.print_image, STRINGS_FILE, IMAGE_FOLDER)
    buffer.print_bootup_lines(VERSION)

    startup_time = time.time()
    bootup_window = 20  # 20 seconds window
                    
    try:
        while True:
            press_duration = platform.wait_for_button(BUTTON_PIN)
            time_since_startup = time.time() - startup_time

            if press_duration >= LONG_PRESS_THRESHOLD:
                if time_since_startup < bootup_window:
                    logging.info("Long press detected during bootup window: printing all quotes and images")
                    buffer.print_all_quotes()
                    buffer.print_all_images()
                    time.sleep(3)
                    platform.cleanup_printer_queue()
                else:
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
                
                buffer.print_random_quote()
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
