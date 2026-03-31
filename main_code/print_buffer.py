import csv
import random
import logging


class PrintBuffer:
    def __init__(self, filename, print_raw, print_image_file):
        self.lines = self._load_csv(filename)
        self._print_raw = print_raw
        self._print_image_file = print_image_file
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

    def select_random_line(self):
        self.text = random.choice(self.lines) if self.lines else "<No data available>"

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
        self.text = "*****************************"

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
            encoded = b"error transcoding text"
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

    def print_bootup_lines(self, version):
        self.set_text(f"All good vibes loaded\n{version}")
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
        words = [
            "wonderful", "fantastic", "beautiful", "really good",
            "outstanding", "friendly", "sunny", "successful",
        ]
        self.set_text_align("center")
        self.set_text(f"Have a\n{random.choice(words)} day! <3")
        self.set_feed_lines(5)
        self.print()
