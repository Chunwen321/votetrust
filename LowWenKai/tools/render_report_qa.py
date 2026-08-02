from __future__ import annotations

import argparse
from pathlib import Path

import fitz
from PIL import Image, ImageDraw


def render(pdf_path: Path, output_dir: Path) -> None:
    output_dir.mkdir(parents=True, exist_ok=True)
    document = fitz.open(pdf_path)
    page_paths: list[Path] = []

    for index, page in enumerate(document):
        pixmap = page.get_pixmap(matrix=fitz.Matrix(1.5, 1.5), alpha=False)
        page_path = output_dir / f"page-{index + 1:02d}.png"
        pixmap.save(page_path)
        page_paths.append(page_path)

    for batch_start in range(0, len(page_paths), 4):
        page_images: list[Image.Image] = []
        for page_path in page_paths[batch_start : batch_start + 4]:
            image = Image.open(page_path).convert("RGB")
            image.thumbnail((620, 880))
            canvas = Image.new("RGB", (640, 920), "#cbd5e1")
            canvas.paste(image, ((640 - image.width) // 2, 30))
            ImageDraw.Draw(canvas).text((16, 8), page_path.stem, fill="#13233a")
            page_images.append(canvas)

        sheet = Image.new("RGB", (1280, 1840), "#94a3b8")
        for index, image in enumerate(page_images):
            sheet.paste(image, ((index % 2) * 640, (index // 2) * 920))
        sheet.save(output_dir / f"contact-{batch_start // 4 + 1:02d}.png")

    print(f"pages={len(page_paths)} contacts={(len(page_paths) + 3) // 4}")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("output_dir", type=Path)
    arguments = parser.parse_args()
    render(arguments.pdf.resolve(), arguments.output_dir.resolve())


if __name__ == "__main__":
    main()
