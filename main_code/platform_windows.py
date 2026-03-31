import logging


def print_raw(data: bytes):
    logging.info(f"[win stub] print_raw: {data}")


def print_image(filepath: str):
    logging.info(f"[win stub] print_image: {filepath}")


def setup_gpio(pin: int):
    logging.info(f"[win stub] setup_gpio: pin {pin}")


def wait_for_button(pin: int) -> float:
    input("Press Enter to print a quote... ")
    return 0.2  # 200ms exceeds SHORT_PRESS_MIN_DURATION threshold


def cleanup_printer_queue():
    logging.info("[win stub] cleanup_printer_queue: no-op")


def shutdown():
    logging.info("[win stub] shutdown: no-op")


def gpio_cleanup():
    logging.info("[win stub] gpio_cleanup: no-op")
