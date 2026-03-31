import os
import subprocess
import sys
import threading
import time
import logging

import RPi.GPIO as GPIO


def print_raw(data: bytes):
    try:
        subprocess.run(["lp", "-o", "raw"], input=data, check=True)
        logging.info("Printed successfully.")
    except Exception as e:
        logging.error(f"Printing failed: {e}")


def print_image(filepath: str):
    subprocess.run(["lp", "-o", "raw", filepath])


def setup_gpio(pin: int):
    GPIO.setmode(GPIO.BCM)
    GPIO.setup(pin, GPIO.IN, pull_up_down=GPIO.PUD_UP)


def wait_for_button(pin: int) -> float:
    triggered = threading.Event()
    press_duration = [0.0]

    def gpio_listener():
        try:
            # Remove any existing edge detection before setting up new one
            GPIO.remove_event_detect(pin)
        except:
            pass  # No existing detection to remove
        
        GPIO.wait_for_edge(pin, GPIO.FALLING)
        press_start = time.time()
        while GPIO.input(pin) == GPIO.LOW:
            time.sleep(0.01)
        press_duration[0] = time.time() - press_start
        triggered.set()

    def stdin_listener():
        sys.stdin.readline()
        press_duration[0] = 0.2  # 200ms exceeds SHORT_PRESS_MIN_DURATION threshold
        triggered.set()

    threading.Thread(target=gpio_listener, daemon=True).start()
    threading.Thread(target=stdin_listener, daemon=True).start()

    triggered.wait()
    return press_duration[0]


def cleanup_printer_queue():
    try:
        result = subprocess.run(["cancel", "-a", "-x"], stdout=subprocess.PIPE).stdout.decode("utf-8")
    except Exception as e:
        logging.error(f"Error while deleting pending print jobs: {e}")
        raise
    logging.info(f"Pending print jobs cancelled: {result}")

    try:
        result = subprocess.run(["lpstat", "-o"], stdout=subprocess.PIPE).stdout.decode("utf-8")
    except Exception as e:
        logging.error(f"Error while gathering pending print jobs: {e}")
        raise
    if result:
        logging.warning(f"Still pending print jobs after cancel: {result}")
    else:
        logging.info("No pending print jobs left.")


def shutdown():
    time.sleep(1)
    os.system("sudo shutdown now")


def gpio_cleanup():
    GPIO.cleanup()
