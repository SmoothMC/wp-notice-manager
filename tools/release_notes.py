"""Extract one changelog version, or synchronize published GitHub release bodies."""
import argparse
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def sections(text):
    headings = list(re.finditer(r'^## (\d+\.\d+\.\d+) – (\d{4}-\d{2}-\d{2})\s*$', text, re.M))
    result = {}
    for i, match in enumerate(headings):
        version = match.group(1)
        if version in result:
            raise ValueError(f'Duplicate changelog version: {version}')
        end = headings[i + 1].start() if i + 1 < len(headings) else len(text)
        body = text[match.start():end].strip() + '\n'
        categories = re.findall(r'^### (.+)$', body, re.M)
        if not categories or any(c not in ('New', 'Fix', 'Change', 'Remove') for c in categories):
            raise ValueError(f'Missing or invalid categories for {version}')
        result[version] = body
    if not result:
        raise ValueError('No version sections found')
    return result


def extract(text, version):
    version = version.removeprefix('v')
    if not re.fullmatch(r'\d+\.\d+\.\d+', version):
        raise ValueError('Expected stable X.Y.Z version')
    parsed = sections(text)
    if version not in parsed:
        raise ValueError(f'No changelog entry for {version}')
    return parsed[version]


def sync(text, repository):
    parsed = sections(text)
    pages = json.loads(subprocess.check_output(
        ['gh', 'api', '--paginate', '--slurp', f'repos/{repository}/releases?per_page=100'], text=True))
    releases = [r for page in pages for r in page if not r['draft'] and re.fullmatch(r'v\d+\.\d+\.\d+', r['tag_name'])]
    # Validate all versions before editing any release. Only body is patched.
    updates = [(r, parsed[r['tag_name'][1:]]) for r in releases]
    for release, body in updates:
        if (release.get('body') or '').strip() == body.strip():
            print(f"Unchanged: {release['tag_name']}")
            continue
        subprocess.run(['gh', 'api', '--method', 'PATCH', f"repos/{repository}/releases/{release['id']}", '--input', '-'],
                       input=json.dumps({'body': body}), text=True, check=True, stdout=subprocess.DEVNULL)
        print(f"Updated notes: {release['tag_name']}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--version')
    group.add_argument('--sync-repository')
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    changelog = (ROOT / 'CHANGELOG.md').read_text()
    if args.sync_repository:
        sync(changelog, args.sync_repository)
    else:
        notes = extract(changelog, args.version)
        if args.output:
            args.output.parent.mkdir(parents=True, exist_ok=True)
            args.output.write_text(notes)
        else:
            print(notes, end='')
