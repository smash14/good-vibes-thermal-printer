import glob
import logging
import os

from PIL import Image, ImageEnhance, ImageOps

SUPPORTED_IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".tif", ".tiff"}


# Maps a clockwise rotation in degrees to the equivalent lossless PIL transpose
# (PIL's ROTATE_* constants rotate counter-clockwise, so 90 clockwise = ROTATE_270).
_ROTATION_TRANSPOSE = {
    90: Image.ROTATE_270,
    180: Image.ROTATE_180,
    270: Image.ROTATE_90,
}


def image_to_escpos_raster(
    image_path,
    output_bin_path,
    max_width=384,
    contrast=1.0,
    threshold=None,
    rotation=0,
    brightness=1.0,
    auto_contrast=False,
):
    """Convert an image to a 1-bit ESC/POS raster .bin file.

    contrast and brightness are multipliers applied before the black/white
    conversion (1.0 = no change). auto_contrast, if True, stretches the image's
    histogram to use the full black-white range (applied before brightness/contrast,
    so those still layer on top of it) - a one-click fix for flat/washed-out photos.
    threshold, if given (0-255), replaces Pillow's automatic Floyd-Steinberg dithering
    with a hard black/white cutoff at that grayscale value. rotation is a clockwise
    angle in degrees (0, 90, 180, or 270) applied before anything else - useful for
    printing wide/landscape images along the paper's length instead of squeezing them
    down to the printer's fixed dot width.
    """
    image = Image.open(image_path).convert("L")

    if rotation:
        image = image.transpose(_ROTATION_TRANSPOSE[rotation])

    if auto_contrast:
        image = ImageOps.autocontrast(image)

    if brightness != 1.0:
        image = ImageEnhance.Brightness(image).enhance(brightness)

    if contrast != 1.0:
        image = ImageEnhance.Contrast(image).enhance(contrast)

    if threshold is None:
        image = image.convert("1")
    else:
        image = image.point(lambda p: 255 if p >= threshold else 0).convert("1")

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


def escpos_raster_to_preview_image(bin_path, output_jpg_path):
    """Convert an ESC/POS GS v 0 raster .bin file back into a viewable JPEG preview."""
    with open(bin_path, "rb") as f:
        data = f.read()

    if data[:4] != b"\x1D\x76\x30\x00" or len(data) < 8:
        raise ValueError(f"Not a recognized ESC/POS raster file: {bin_path}")

    width_bytes = data[4] | (data[5] << 8)
    height = data[6] | (data[7] << 8)
    width = width_bytes * 8
    pixel_data = data[8:]

    image = Image.new("1", (width, height), 1)  # white background

    for y in range(height):
        row_offset = y * width_bytes
        for x in range(width_bytes):
            byte = pixel_data[row_offset + x]
            for bit in range(8):
                if byte & (1 << (7 - bit)):
                    image.putpixel((x * 8 + bit, y), 0)  # black pixel

    image.convert("L").save(output_jpg_path, "JPEG")


def find_pending_previews(image_folder):
    """Return sorted paths of .bin files in image_folder that have no matching preview .jpg yet."""
    pending = []
    for bin_path in glob.glob(os.path.join(image_folder, "*.bin")):
        jpg_path = os.path.splitext(bin_path)[0] + ".jpg"
        if not os.path.exists(jpg_path):
            pending.append(bin_path)
    return sorted(pending)


def generate_pending_previews(image_folder):
    """Generate a sibling preview .jpg for every .bin that doesn't have one yet.

    Each file is converted inside its own try/except so one corrupt/unrecognized
    .bin can't block the rest or crash startup. The .bin itself is never deleted
    here, even on failure - a missing preview just means the web UI falls back to
    a placeholder and retries on the next startup.
    """
    pending = find_pending_previews(image_folder)
    if not pending:
        return

    logging.info(f"Found {len(pending)} image(s) pending preview generation.")

    for bin_path in pending:
        filename = os.path.basename(bin_path)
        jpg_path = os.path.splitext(bin_path)[0] + ".jpg"
        try:
            escpos_raster_to_preview_image(bin_path, jpg_path)
            logging.info(f"Generated preview for: {filename}")
        except Exception as e:
            logging.error(f"Failed to generate preview for {filename}: {e}")


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


def _main():
    """Standalone CLI: convert a single image to a .bin (and optionally a preview .jpg).

    Used by the PHP admin page (server/images.php) to run interactive conversions
    with user-adjustable contrast/threshold, outside of the normal main.py polling flow.
    """
    import argparse

    parser = argparse.ArgumentParser(description="Convert an image to an ESC/POS raster .bin file.")
    parser.add_argument("input", help="Path to the source image")
    parser.add_argument("--output-bin", required=True, help="Path to write the .bin file to")
    parser.add_argument("--output-preview", help="Optional path to also write a preview .jpg to")
    parser.add_argument("--max-width", type=int, default=384)
    parser.add_argument("--contrast", type=float, default=1.0)
    parser.add_argument("--threshold", type=int, default=None, help="0-255; omit for automatic dithering")
    parser.add_argument("--rotation", type=int, default=0, choices=[0, 90, 180, 270], help="clockwise degrees")
    parser.add_argument("--brightness", type=float, default=1.0)
    parser.add_argument("--auto-contrast", action="store_true", help="stretch histogram to full black-white range")
    args = parser.parse_args()

    image_to_escpos_raster(
        args.input,
        args.output_bin,
        args.max_width,
        args.contrast,
        args.threshold,
        args.rotation,
        args.brightness,
        args.auto_contrast,
    )
    if args.output_preview:
        escpos_raster_to_preview_image(args.output_bin, args.output_preview)


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    _main()
