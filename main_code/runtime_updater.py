import glob
import logging
import os
import threading
import time

from image_converter import convert_pending_images, generate_pending_previews

DEFAULT_POLL_INTERVAL_SECONDS = 30.0
DISABLED_RECHECK_SECONDS = 5.0


def _setting_text(strings, key, default):
    return strings.get(key, {}).get("text", default)


def _poll_interval_seconds(buffer):
    raw = _setting_text(buffer.strings, "runtime_poll_interval_seconds", str(DEFAULT_POLL_INTERVAL_SECONDS))
    try:
        return float(raw)
    except (TypeError, ValueError):
        return DEFAULT_POLL_INTERVAL_SECONDS


def _print_on_update_enabled(buffer):
    raw = _setting_text(buffer.strings, "runtime_print_on_update", "true")
    return str(raw).strip().lower() in ("1", "true", "yes", "on")


def _current_bin_files(image_folder):
    return set(glob.glob(os.path.join(image_folder, "*.bin")))


def run_update_check(buffer, image_folder, image_max_width, known_quotes, known_images, print_lock, cleanup_printer_queue):
    """Run one poll cycle: convert pending uploads, reload quotes, print anything new.

    known_quotes/known_images are updated in place so the caller's baseline stays
    current across calls, regardless of whether printing is enabled.
    """
    buffer.reload_strings()

    convert_pending_images(image_folder, image_max_width)
    generate_pending_previews(image_folder)

    buffer.reload_quotes()
    current_quotes = set(buffer.lines)
    new_quotes = current_quotes - known_quotes
    known_quotes.clear()
    known_quotes.update(current_quotes)

    current_images = _current_bin_files(image_folder)
    new_images = current_images - known_images
    known_images.clear()
    known_images.update(current_images)

    if not new_quotes and not new_images:
        return

    if not _print_on_update_enabled(buffer):
        logging.info(
            f"Detected {len(new_quotes)} new quote(s) and {len(new_images)} new image(s); "
            "printing on update is disabled."
        )
        return

    with print_lock:
        for quote in sorted(new_quotes):
            logging.info(f"Printing newly detected quote: {quote}")
            buffer.print_new_quote(quote)
            time.sleep(1)
        for image_path in sorted(new_images):
            logging.info(f"Printing newly detected image: {image_path}")
            buffer.print_new_image(image_path)
            time.sleep(2)
        cleanup_printer_queue()


def start_background_updater(buffer, image_folder, image_max_width, cleanup_printer_queue, print_lock):
    """Start a daemon thread that periodically checks for new quotes/images.

    The poll interval and the print-on-update toggle are re-read from strings.json
    on every cycle, so changes made through the web UI take effect without a restart.
    """
    known_quotes = set(buffer.lines)
    known_images = _current_bin_files(image_folder)
    stop_event = threading.Event()

    def loop():
        while not stop_event.is_set():
            interval = _poll_interval_seconds(buffer)
            if interval <= 0:
                if stop_event.wait(DISABLED_RECHECK_SECONDS):
                    break
                continue

            if stop_event.wait(interval):
                break

            try:
                run_update_check(
                    buffer, image_folder, image_max_width, known_quotes, known_images, print_lock, cleanup_printer_queue
                )
            except Exception as e:
                logging.error(f"Runtime update check failed: {e}")

    thread = threading.Thread(target=loop, daemon=True)
    thread.start()
    return thread, stop_event
