import csv
import json
import random
import logging
import os
import glob


class PrintBuffer:
    def __init__(self, filename, print_raw, print_image_file, strings_file="strings.json", image_folder="header_images"):
        self.lines = self._load_csv(filename)
        self._print_raw = print_raw
        self._print_image_file = print_image_file
        self.strings_file = strings_file
        self.image_folder = image_folder
        self.strings = self._load_strings()
        self._reset_font_styles()

    @staticmethod
    def _load_csv(filename):
        try:
            with open(filename, newline='', encoding='utf-8') as file:
                reader = csv.reader(file)
                lines = [", ".join(row) for row in reader if row]
                lines = [line.replace("\\n", "\n") for line in lines]
            logging.info(f"Loaded {len(lines)} lines from CSV.")
            return lines
        except Exception as e:
            logging.error(f"Failed to load CSV: {e}")
            return ["<CSV load error>"]

    def _load_strings(self):
        try:
            strings_path = os.path.join(os.path.dirname(__file__), self.strings_file)
            with open(strings_path, "r", encoding="utf-8") as file:
                strings = json.load(file)
            logging.info("Loaded strings from JSON.")
            return strings
        except Exception as e:
            logging.error(f"Failed to load strings.json: {e}")
            return {}

    def select_random_line(self):
        no_data_msg = self.strings.get("error_no_data", {}).get("text", "<No data available>")
        self.text = random.choice(self.lines) if self.lines else no_data_msg
        logging.info(f"Selected random line: {self.text}")

    def set_text(self, new_text):
        self.text = new_text

    def set_font_size(self, new_font_size="normal"):
        sizes = {
            "normal": b"\x1D\x21\x00",
            "big":    b"\x1D\x21\x10",
            "bigger": b"\x1D\x21\x33",
        }
        self.font_size = sizes.get(new_font_size, b"\x1D\x21\x00")

    def set_font_bold(self, bold_on):
        self.font_bold = b"\x1B\x45\x01" if bold_on else b"\x1B\x45\x00"

    def set_text_to_invert(self, invert_on):
        self.text_invert_style = b"\x1D\x42\x01" if invert_on else b"\x1D\x42\x00"

    def set_text_align(self, align="left"):
        alignments = {
            "left":   b"\x1B\x61\x00",
            "center": b"\x1B\x61\x01",
            "right":  b"\x1B\x61\x02",
        }
        self.text_align = alignments.get(align, b"\x1B\x61\x00")

    def set_feed_lines(self, n):
        if not (0 <= n <= 255):
            raise ValueError("Line count must be between 0 and 255")
        self.feed_lines = b'\x1B\x64' + bytes([n])

    def set_text_stars(self):
        separator = self.strings.get("separator", {}).get("text", "*****************************")
        self.text = separator

    def _reset_font_styles(self):
        self.text = ""
        self.text_invert_style = b"\x1D\x42\x00"
        self.text_align = b"\x1B\x61\x00"
        self.font_size = b"\x1D\x21\x00"
        self.font_bold = b"\x1B\x45\x00"
        self.feed_lines = b"\x1B\x64\x00"

    def _build(self) -> bytes:
        try:
            encoded = self.text.encode("cp850")
        except Exception as e:
            error_msg = self.strings.get("error_encoding", {}).get("text", "error transcoding text")
            encoded = error_msg.encode("cp850", errors="replace")
            logging.error(f"Text encoding failed: {e}")
        return (
            self.text_align
            + self.font_bold
            + self.text_invert_style
            + self.font_size
            + encoded
            + self.feed_lines
        )

    def print(self):
        self._print_raw(self._build())

    def print_image(self, filepath):
        self._print_image_file(filepath)
        self._reset_font_styles()
        self.set_feed_lines(2)
        self.print()

    def print_all_quotes(self):
        for line in self.lines:
            self._reset_font_styles()
            self.set_text_align("center")
            self.set_text(line)
            self.print()
            self.set_text_stars()
            self.print()

    def print_all_images(self):
        """Print all .bin image files from the image folder."""
        image_files = sorted(glob.glob(os.path.join(self.image_folder, "*.bin")))
        if not image_files:
            logging.warning(f"No .bin files found in {self.image_folder} folder.")
            return
        for image_file in image_files:
            self._reset_font_styles()
            self.print_image(image_file)
            logging.info(f"Printed image: {image_file}")

    def print_bootup_lines(self, version):
        bootup_msg = self.strings.get("bootup_message", {}).get("text", "All good vibes loaded\n{version}")
        self.set_text(bootup_msg.format(version=version))
        self.set_feed_lines(5)
        self.print()

    def print_welcome_lines(self):
        self._reset_font_styles()
        self.set_text_align("center")
        self.set_font_size("big")
        welcome_title = self.strings.get("welcome_title", {}).get("text", "Good Vibes Only\n")
        self.set_text(welcome_title)
        self.print()
        self.set_font_bold(False)
        self.set_font_size("normal")
        welcome_decoration = self.strings.get("welcome_decoration", {}).get("text", "~~~ <3 ~~~")
        self.set_text(welcome_decoration)
        self.set_feed_lines(2)
        self.print()
        self.set_feed_lines(0)

    def print_random_quote(self):
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
        adjectives = self.strings.get("adjectives", {}).get("text", ["wonderful", "fantastic", "beautiful"])
        finish_template = self.strings.get("finish_prefix", {}).get("text", "Have a\n{word} day! <3")
        self.set_text_align("center")
        self.set_text(finish_template.format(word=random.choice(adjectives)))
        self.set_feed_lines(5)
        self.print()
