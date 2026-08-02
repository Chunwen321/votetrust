from __future__ import annotations

import argparse
import hashlib
import zipfile
from pathlib import Path

from docx import Document


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def package_hashes(path: Path, prefix: str) -> dict[str, str]:
    with zipfile.ZipFile(path) as archive:
        return {
            name: hashlib.sha256(archive.read(name)).hexdigest()
            for name in archive.namelist()
            if name.startswith(prefix)
        }


def audit(reference: Path, report: Path) -> None:
    document = Document(report)
    body_text = [paragraph.text for paragraph in document.paragraphs]
    for table in document.tables:
        for row in table.rows:
            body_text.extend(cell.text for cell in row.cells)
    text = "\n".join(body_text)
    headings = [
        paragraph.text
        for paragraph in document.paragraphs
        if paragraph.style and paragraph.style.name.startswith("Heading")
    ]

    with zipfile.ZipFile(report) as archive:
        names = archive.namelist()
        field_xml = "\n".join(
            archive.read(name).decode("utf-8")
            for name in names
            if name.startswith("word/") and name.endswith(".xml")
        )
        media = [name for name in names if name.startswith("word/media/")]

    reference_fonts = package_hashes(reference, "word/fonts/")
    report_fonts = package_hashes(report, "word/fonts/")
    markers = [marker for marker in ("Not supplied", "TODO", "Lorem ipsum", "[insert") if marker.lower() in text.lower()]

    print(f"reference_sha256={sha256(reference)}")
    print(f"report_sha256={sha256(report)}")
    print(f"paragraphs={len(document.paragraphs)} tables={len(document.tables)} media={len(media)}")
    print(f"toc_field={'TOC ' in field_xml} page_field={'PAGE' in field_xml and 'NUMPAGES' in field_xml}")
    print(f"embedded_fonts_retained={bool(report_fonts)} font_parts={len(report_fonts)} exact_font_package_match={reference_fonts == report_fonts}")
    print(f"markers={markers}")
    print("headings:")
    for heading in headings:
        print(f"- {heading}")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("reference", type=Path)
    parser.add_argument("report", type=Path)
    arguments = parser.parse_args()
    audit(arguments.reference.resolve(), arguments.report.resolve())


if __name__ == "__main__":
    main()
