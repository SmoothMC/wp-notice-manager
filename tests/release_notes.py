import sys
import unittest
from pathlib import Path
from unittest.mock import patch
import json
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from release_notes import extract, sections, sync, ROOT


class ReleaseNotes(unittest.TestCase):
    def test_all_versions_are_isolated(self):
        text = (ROOT / 'CHANGELOG.md').read_text()
        parsed = sections(text)
        self.assertGreaterEqual(len(parsed), 15)
        for version in parsed:
            notes = extract(text, 'v' + version)
            self.assertEqual(sum(line.startswith('## ') for line in notes.splitlines()), 1)
            self.assertTrue(notes.startswith('## ' + version + ' – '))
            self.assertIn('### ', notes)

    def test_invalid_missing_duplicate(self):
        text = '## 1.0.0 – 2026-10-25\n\n### New\n- Example.\n'
        for version in ['2.0.0', '../1.0.0', '1.0']:
            with self.assertRaises(ValueError): extract(text, version)
        with self.assertRaises(ValueError): sections(text + text)
        with self.assertRaises(ValueError): sections(text.replace('### New', '### Other'))

    def test_sync_only_patches_body(self):
        text = '## 1.0.0 – 2026-10-25\n\n### New\n- Example.\n'
        releases = [[{'id': 1, 'tag_name': 'v1.0.0', 'draft': False, 'body': 'old'},
                     {'id': 2, 'tag_name': 'v2.0.0', 'draft': True, 'body': 'draft'}]]
        with patch('release_notes.subprocess.check_output', return_value=json.dumps(releases)), patch('release_notes.subprocess.run') as run:
            sync(text, 'owner/repo')
            self.assertEqual(run.call_count, 1)
            self.assertEqual(json.loads(run.call_args.kwargs['input']), {'body': extract(text, '1.0.0')})
            self.assertIn('repos/owner/repo/releases/1', run.call_args.args[0])
        releases[0][0]['body'] = extract(text, '1.0.0')
        with patch('release_notes.subprocess.check_output', return_value=json.dumps(releases)), patch('release_notes.subprocess.run') as run:
            sync(text, 'owner/repo'); run.assert_not_called()


if __name__ == '__main__': unittest.main()
