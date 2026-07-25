import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtemp, writeFile, rm, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { resolveAlias } from '../lib/alias-resolver.js';

let root;

beforeEach(async () => {
  root = await mkdtemp(join(tmpdir(), 'aliases-'));
});

afterEach(async () => {
  await rm(root, { recursive: true });
});

/**
 * Helper to create a data.json mapping for an alias.
 * Writes the file at <root>/<lang>/<bucket>/<sanitizedAlias>/data.json.
 */
async function createAlias(lang, bucket, alias, entityType, entityId) {
  await mkdir(join(root, lang, String(bucket), alias), { recursive: true });
  await writeFile(
    join(root, lang, String(bucket), alias, 'data.json'),
    JSON.stringify({ entityType, entityId }),
  );
}

describe('resolveAlias', () => {
  it('resolves alias in the specified language', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    const result = await resolveAlias(root, 'about-us', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });
  });

  it('resolves multi-segment alias in the specified language', async () => {
    await createAlias('es', 0, 'hola/mundo', 'node', '42');
    const result = await resolveAlias(root, 'hola/mundo', 'es');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '42',
      langcode: 'es',
    });
  });

  it('resolves deep multi-segment alias in the specified language', async () => {
    await createAlias('en', 0, 'products/shoes/running', 'node', '99');
    const result = await resolveAlias(root, 'products/shoes/running', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '99',
      langcode: 'en',
    });
  });

  it('sanitizes alias before lookup (removes path traversal)', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    // '/./about-us' sanitizes to '/about-us' which exists.
    const result = await resolveAlias(root, '/./about-us', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });
  });

  it('returns null when alias only exists in a different language', async () => {
    await createAlias('en', 0, 'about', 'node', '1');
    const result = await resolveAlias(root, 'about', 'fr');
    expect(result).toBeNull();
  });

  it('returns null when language directory does not exist', async () => {
    const result = await resolveAlias(root, 'about', 'de');
    expect(result).toBeNull();
  });

  it('returns null for non-existent alias directory', async () => {
    const result = await resolveAlias('/totally/nonexistent/path', 'about', 'en');
    expect(result).toBeNull();
  });

  it('finds alias in higher bucket (1+)', async () => {
    await createAlias('en', 1, 'another', 'user', '5');
    const result = await resolveAlias(root, 'another', 'en');
    expect(result).toEqual({
      entityType: 'user',
      entityId: '5',
      langcode: 'en',
    });
  });

  it('returns null for unknown alias in valid language', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    const result = await resolveAlias(root, 'does-not-exist', 'en');
    expect(result).toBeNull();
  });

  it('ignores data.json with missing fields', async () => {
    await mkdir(join(root, 'en', '0', 'bad'), { recursive: true });
    await writeFile(
      join(root, 'en', '0', 'bad', 'data.json'),
      JSON.stringify({ entityType: 'node' }),
    );
    const result = await resolveAlias(root, 'bad', 'en');
    expect(result).toBeNull();
  });

  it('disambiguates same alias in different languages', async () => {
    await createAlias('en', 0, 'about', 'node', '1');
    await createAlias('es', 0, 'about', 'node', '2');

    const enResult = await resolveAlias(root, 'about', 'en');
    expect(enResult).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });

    const esResult = await resolveAlias(root, 'about', 'es');
    expect(esResult).toEqual({
      entityType: 'node',
      entityId: '2',
      langcode: 'es',
    });
  });

  it('does not fall back to other languages when alias not found in target', async () => {
    // Alias only exists in 'en'. Asking for 'es' must return null, not the 'en' version.
    await createAlias('en', 0, 'about', 'node', '1');
    const result = await resolveAlias(root, 'about', 'es');
    expect(result).toBeNull();
  });

  it('skips non-directory entries in language root', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    await writeFile(join(root, 'stray.txt'), 'stray');
    const result = await resolveAlias(root, 'about-us', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });
  });

  it('skips non-directory entries in bucket directory', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    await writeFile(join(root, 'en', '0', 'stray.txt'), 'stray');
    const result = await resolveAlias(root, 'about-us', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });
  });

  it('handles empty alias (returns null)', async () => {
    const result = await resolveAlias(root, '', 'en');
    expect(result).toBeNull();
  });

  it('handles alias with leading slash', async () => {
    await createAlias('en', 0, 'about-us', 'node', '1');
    const result = await resolveAlias(root, '/about-us', 'en');
    expect(result).toEqual({
      entityType: 'node',
      entityId: '1',
      langcode: 'en',
    });
  });
});
