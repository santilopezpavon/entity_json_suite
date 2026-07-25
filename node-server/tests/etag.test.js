import { describe, it, expect } from 'vitest';
import { isNotModified, buildEtag } from '../lib/etag.js';
import { mkdtemp, writeFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

describe('isNotModified', () => {
  it('returns true for exact ETag match', () => {
    const etag = '"12345-100"';
    expect(isNotModified(etag, etag)).toBe(true);
  });

  it('returns true for wildcard', () => {
    expect(isNotModified('*', '"any"')).toBe(true);
  });

  it('returns true for comma-separated list match', () => {
    const etag = '"abc-100"';
    expect(isNotModified(`"old", ${etag}, "other"`, etag)).toBe(true);
  });

  it('returns false for mismatch', () => {
    expect(isNotModified('"different"', '"abc"')).toBe(false);
  });

  it('returns false for empty input', () => {
    expect(isNotModified('', '"abc"')).toBe(false);
    expect(isNotModified('"abc"', '')).toBe(false);
  });
});

describe('buildEtag', () => {
  it('returns null for non-existent file', async () => {
    const etag = await buildEtag('/nonexistent/path/file.json');
    expect(etag).toBeNull();
  });

  it('returns quoted string with mtime and size', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'etag-'));
    const file = join(dir, 'test.json');
    await writeFile(file, '{"a":1}');
    try {
      const etag = await buildEtag(file);
      expect(etag).toMatch(/^"\d+-\d+"$/);
      const stats = await stat(file);
      const expected = `"${Math.floor(stats.mtimeMs)}-${stats.size}"`;
      expect(etag).toBe(expected);
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });
});
