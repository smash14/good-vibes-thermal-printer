import logging
import os
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, os.path.dirname(__file__))

from PIL import Image

import image_converter


def make_image(path, size, color):
    Image.new("RGB", size, color=color).save(path)


class FindPendingConversionsTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.folder = self.tmp.name

    def tearDown(self):
        self.tmp.cleanup()

    def test_images_without_bin_are_pending(self):
        make_image(os.path.join(self.folder, "a.jpg"), (10, 10), (255, 0, 0))
        make_image(os.path.join(self.folder, "b.png"), (10, 10), (0, 255, 0))

        pending = image_converter.find_pending_conversions(self.folder)

        self.assertEqual(
            sorted(os.path.basename(p) for p in pending), ["a.jpg", "b.png"]
        )

    def test_image_with_matching_bin_is_excluded(self):
        make_image(os.path.join(self.folder, "a.jpg"), (10, 10), (255, 0, 0))
        open(os.path.join(self.folder, "a.bin"), "wb").close()

        pending = image_converter.find_pending_conversions(self.folder)

        self.assertEqual(pending, [])

    def test_extension_matching_is_case_insensitive(self):
        make_image(os.path.join(self.folder, "a.JPG"), (10, 10), (255, 0, 0))

        pending = image_converter.find_pending_conversions(self.folder)

        self.assertEqual([os.path.basename(p) for p in pending], ["a.JPG"])

    def test_no_pending_images_returns_empty_list(self):
        self.assertEqual(image_converter.find_pending_conversions(self.folder), [])


class ConvertPendingImagesTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.folder = self.tmp.name
        logging.disable(logging.CRITICAL)

    def tearDown(self):
        self.tmp.cleanup()
        logging.disable(logging.NOTSET)

    def test_successful_conversion_creates_bin_and_deletes_source(self):
        make_image(os.path.join(self.folder, "a.jpg"), (10, 10), (255, 0, 0))

        image_converter.convert_pending_images(self.folder)

        self.assertTrue(os.path.exists(os.path.join(self.folder, "a.bin")))
        self.assertFalse(os.path.exists(os.path.join(self.folder, "a.jpg")))

    def test_broken_image_is_deleted_without_raising(self):
        broken_path = os.path.join(self.folder, "broken.png")
        with open(broken_path, "w") as f:
            f.write("not an image")

        image_converter.convert_pending_images(self.folder)

        self.assertFalse(os.path.exists(broken_path))
        self.assertFalse(os.path.exists(os.path.join(self.folder, "broken.bin")))

    def test_broken_image_does_not_block_remaining_conversions(self):
        with open(os.path.join(self.folder, "broken.png"), "w") as f:
            f.write("not an image")
        make_image(os.path.join(self.folder, "good.jpg"), (10, 10), (0, 0, 255))

        image_converter.convert_pending_images(self.folder)

        self.assertFalse(os.path.exists(os.path.join(self.folder, "broken.png")))
        self.assertTrue(os.path.exists(os.path.join(self.folder, "good.bin")))

    def test_progress_callback_receives_correct_sequence(self):
        make_image(os.path.join(self.folder, "a.jpg"), (10, 10), (255, 0, 0))
        make_image(os.path.join(self.folder, "b.png"), (10, 10), (0, 255, 0))
        progress_calls = []

        image_converter.convert_pending_images(
            self.folder, on_progress=lambda current, total: progress_calls.append((current, total))
        )

        self.assertEqual(progress_calls, [(1, 2), (2, 2)])

    def test_no_progress_callback_invoked_when_nothing_pending(self):
        progress_calls = []

        image_converter.convert_pending_images(
            self.folder, on_progress=lambda current, total: progress_calls.append((current, total))
        )

        self.assertEqual(progress_calls, [])

    def test_partial_bin_is_removed_when_conversion_fails(self):
        make_image(os.path.join(self.folder, "a.jpg"), (10, 10), (255, 0, 0))
        bin_path = os.path.join(self.folder, "a.bin")

        def fake_raster(image_path, output_bin_path, max_width=384):
            open(output_bin_path, "wb").close()
            raise ValueError("simulated failure")

        with patch.object(image_converter, "image_to_escpos_raster", side_effect=fake_raster):
            image_converter.convert_pending_images(self.folder)

        self.assertFalse(os.path.exists(bin_path))
        self.assertFalse(os.path.exists(os.path.join(self.folder, "a.jpg")))


class ImageToEscposRasterTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.folder = self.tmp.name

    def tearDown(self):
        self.tmp.cleanup()

    def test_white_image_produces_zeroed_data_and_correct_header(self):
        image_path = os.path.join(self.folder, "white.png")
        bin_path = os.path.join(self.folder, "white.bin")
        make_image(image_path, (8, 2), (255, 255, 255))

        image_converter.image_to_escpos_raster(image_path, bin_path)

        with open(bin_path, "rb") as f:
            content = f.read()

        self.assertEqual(content[:4], b"\x1D\x76\x30\x00")
        self.assertEqual(content[4:8], bytes([1, 0, 2, 0]))  # width_bytes=1, height=2
        self.assertEqual(content[8:], b"\x00\x00")

    def test_black_image_sets_all_bits(self):
        image_path = os.path.join(self.folder, "black.png")
        bin_path = os.path.join(self.folder, "black.bin")
        make_image(image_path, (8, 1), (0, 0, 0))

        image_converter.image_to_escpos_raster(image_path, bin_path)

        with open(bin_path, "rb") as f:
            content = f.read()

        self.assertEqual(content[8:], b"\xFF")

    def test_wide_image_is_resized_to_max_width(self):
        image_path = os.path.join(self.folder, "wide.png")
        bin_path = os.path.join(self.folder, "wide.bin")
        make_image(image_path, (800, 100), (255, 255, 255))

        image_converter.image_to_escpos_raster(image_path, bin_path, max_width=384)

        with open(bin_path, "rb") as f:
            header = f.read(8)

        width_bytes = header[4] | (header[5] << 8)
        self.assertEqual(width_bytes, (384 + 7) // 8)


if __name__ == "__main__":
    unittest.main()
