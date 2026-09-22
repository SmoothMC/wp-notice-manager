"""Build an installable plugin ZIP and matching JSON release metadata."""
import argparse
import datetime
import html
import json
from pathlib import Path
import re
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
SLUG = "notice-manager-by-zzzooo"
REPOSITORY = "https://github.com/SmoothMC/wp-notice-manager"


def build(version, destination):
    if not re.fullmatch(r"\d+\.\d+\.\d+", version):
        raise ValueError("Release version must be a stable X.Y.Z version")
    source = (ROOT / f"{SLUG}.php").read_text()
    header = re.search(r"^ \* Version: (.+)$", source, re.M).group(1)
    constant = re.search(r"define\('ZZZNM_VERSION', '([^']+)'\)", source).group(1)
    if header != version or constant != version:
        raise ValueError(f"Tag {version} differs from plugin header/constant: {header}/{constant}")
    destination.mkdir(parents=True, exist_ok=True)
    package = destination / f"wp-notice-manager-{version}.zip"
    files = [ROOT / f"{SLUG}.php", ROOT / "README.md", ROOT / "CHANGELOG.md", ROOT / "LICENSE"]
    files += sorted((ROOT / "includes").glob("*.php"))
    files += sorted(file for file in (ROOT / "assets").rglob("*") if file.is_file() and not file.name.startswith("."))
    with ZipFile(package, "w", ZIP_DEFLATED) as archive:
        for file in files:
            archive.write(file, f"{SLUG}/{file.relative_to(ROOT).as_posix()}")
    with ZipFile(package) as archive:
        assert archive.testzip() is None
        assert f"{SLUG}/{SLUG}.php" in archive.namelist()
        assert all(name.startswith(SLUG + "/") for name in archive.namelist())
    changelog = (ROOT / "CHANGELOG.md").read_text()
    metadata = {
        "name": "WP Notice Manager by ZZZOOO", "slug": SLUG, "version": version,
        "url": REPOSITORY,
        "download_url": f"{REPOSITORY}/releases/download/v{version}/{package.name}",
        "requires": "6.0", "requires_php": "7.4", "tested": "",
        "last_updated": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d"),
        "description": "<p>Planbare Website-Hinweise als Standalone- oder Elementor-Popup sowie Ticker mit Laufband und wechselnden Meldungen. Mit Complianz-Banner-Sperre.</p>",
        "changelog": "<pre>" + html.escape(changelog) + "</pre>",
    }
    (destination / "update.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + "\n")
    print(f"Built {package} and update.json")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("version")
    parser.add_argument("--output", type=Path, default=ROOT / "build")
    args = parser.parse_args()
    build(args.version.removeprefix("v"), args.output)
