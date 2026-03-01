from PIL import Image
import os

def image_to_escpos_raster(image_path, output_bin_path, max_width=384):
    # Load the image and convert to 1-bit monochrome
    image = Image.open(image_path).convert("1")

    # Resize to printer width while maintaining aspect ratio
    if image.width > max_width:
        ratio = max_width / image.width
        new_height = int(image.height * ratio)
        image = image.resize((max_width, new_height), Image.LANCZOS)

    width_bytes = (image.width + 7) // 8
    height = image.height
    data = bytearray()

    # ESC/POS raster image format
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

    # ESC * command: GS v 0 m xL xH yL yH
    xL = width_bytes & 0xFF
    xH = (width_bytes >> 8) & 0xFF
    yL = height & 0xFF
    yH = (height >> 8) & 0xFF
    header = b'\x1D\x76\x30\x00' + bytes([xL, xH, yL, yH])

    full_data = header + data

    # Save to .bin file
    with open(output_bin_path, "wb") as f:
        f.write(full_data)

    print(f"Saved ESC/POS .bin file to: {output_bin_path}")

# Example usage
image_path = "header_in.png"
output_bin_path = "header_out.bin"
image_to_escpos_raster(image_path, output_bin_path)
