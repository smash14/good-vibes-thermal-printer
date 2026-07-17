import glob
import logging
import os

from PIL import Image

SUPPORTED_IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".tif", ".tiff"}


def image_to_escpos_raster(image_path, output_bin_path, max_width=384):
    """Convert an image to a 1-bit ESC/POS raster .bin file."""
    image = Image.open(image_path).convert("1")

    if image.width > max_width:
        ratio = max_width / image.width
        new_height = int(image.height * ratio)
        image = image.resize((max_width, new_height), Image.LANCZOS)

    width_bytes = (image.width + 7) // 8
    height = image.height
    data = bytearray()

    for y in range(height):
        for x in range(width_bytes):
            byte = 0
            for bit in range(8):
                px = x * 8 + bit
                if px < image.width:
                    color = image.getpixel((px, y))
                    if color == 0:  # Black pixel
                        byte |= (1 << (7 - bit))
            data.append(byte)

    xL = width_bytes & 0xFF
    xH = (width_bytes >> 8) & 0xFF
    yL = height & 0xFF
    yH = (height >> 8) & 0xFF
    header = b'\x1D\x76\x30\x00' + bytes([xL, xH, yL, yH])

    with open(output_bin_path, "wb") as f:
        f.write(header + data)


def find_pending_conversions(image_folder):
    """Return sorted paths of images in image_folder that have no matching .bin file yet."""
    pending = []
    for ext in SUPPORTED_IMAGE_EXTENSIONS:
        pattern_lower = os.path.join(image_folder, f"*{ext}")
        pattern_upper = os.path.join(image_folder, f"*{ext.upper()}")
        for image_path in glob.glob(pattern_lower) + glob.glob(pattern_upper):
            bin_path = os.path.splitext(image_path)[0] + ".bin"
            if not os.path.exists(bin_path):
                pending.append(image_path)
    return sorted(set(pending))


def convert_pending_images(image_folder, max_width=384, on_progress=None):
    """Convert every image in image_folder without a matching .bin into one.

    Successfully converted source images are deleted. Images that fail to
    convert (corrupt/unsupported content) are logged and deleted so they
    aren't retried on every startup.
    """
    pending = find_pending_conversions(image_folder)
    if not pending:
        logging.info("No pending image conversions.")
        return

    total = len(pending)
    logging.info(f"Found {total} image(s) pending conversion.")

    for index, image_path in enumerate(pending, start=1):
        filename = os.path.basename(image_path)
        logging.info(f"Converting image {index} of {total}: {filename}")
        if on_progress:
            on_progress(index, total)

        bin_path = os.path.splitext(image_path)[0] + ".bin"
        try:
            image_to_escpos_raster(image_path, bin_path, max_width)
            os.remove(image_path)
            logging.info(f"Converted and removed source image: {filename}")
        except Exception as e:
            logging.error(f"Failed to convert {filename}: {e}")
            if os.path.exists(bin_path):
                try:
                    os.remove(bin_path)
                except OSError:
                    pass
            try:
                os.remove(image_path)
                logging.warning(f"Deleted unconvertible image: {filename}")
            except OSError as remove_error:
                logging.error(f"Failed to delete unconvertible image {filename}: {remove_error}")
