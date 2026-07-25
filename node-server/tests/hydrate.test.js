import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { mkdtemp, writeFile, rm, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { isEntityReference, parseEmbedParam, resolveReference, hydrateEntity } from '../lib/hydrate.js';

let root;
let exportDir;

beforeAll(async () => {
  root = await mkdtemp(join(tmpdir(), 'ejs-hydrate-'));
  exportDir = join(root, 'serialized');
  await mkdir(exportDir, { recursive: true });
});

afterAll(async () => {
  await rm(root, { recursive: true });
});

async function writeEntity(type, id, lang, data) {
  const bucket = Math.floor(Number(id) / 1000);
  const dir = join(exportDir, type, String(bucket), String(id));
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, `${lang}.json`), JSON.stringify(data));
}

// --- isEntityReference ---

describe('isEntityReference', () => {
  it('returns true for valid entity reference', () => {
    expect(isEntityReference({ target_id: 1, target_type: 'node' })).toBe(true);
  });

  it('returns true with extra fields', () => {
    expect(isEntityReference({
      target_id: 5,
      target_type: 'taxonomy_term',
      target_uuid: 'abc-123',
      url: '/taxonomy/term/5',
    })).toBe(true);
  });

  it('returns false for null', () => {
    expect(isEntityReference(null)).toBe(false);
  });

  it('returns false for undefined', () => {
    expect(isEntityReference(undefined)).toBe(false);
  });

  it('returns false for string', () => {
    expect(isEntityReference('not a ref')).toBe(false);
  });

  it('returns false for number', () => {
    expect(isEntityReference(42)).toBe(false);
  });

  it('returns false for array', () => {
    expect(isEntityReference([{ target_id: 1 }])).toBe(false);
  });

  it('returns false for object missing target_type', () => {
    expect(isEntityReference({ target_id: 1 })).toBe(false);
  });

  it('returns false for object missing target_id', () => {
    expect(isEntityReference({ target_type: 'node' })).toBe(false);
  });

  it('returns false for empty object', () => {
    expect(isEntityReference({})).toBe(false);
  });
});

// --- parseEmbedParam ---

describe('parseEmbedParam', () => {
  it('returns empty array for undefined', () => {
    expect(parseEmbedParam(undefined)).toEqual([]);
  });

  it('returns empty array for empty string', () => {
    expect(parseEmbedParam('')).toEqual([]);
  });

  it('returns empty array for whitespace only', () => {
    expect(parseEmbedParam('   ')).toEqual([]);
  });

  it('returns wildcard for *', () => {
    expect(parseEmbedParam('*')).toEqual(['*']);
  });

  it('parses single type', () => {
    expect(parseEmbedParam('paragraph')).toEqual(['paragraph']);
  });

  it('parses multiple types', () => {
    expect(parseEmbedParam('paragraph,media')).toEqual(['paragraph', 'media']);
  });

  it('trims whitespace around types', () => {
    expect(parseEmbedParam(' paragraph , media ')).toEqual(['paragraph', 'media']);
  });

  it('filters empty segments', () => {
    expect(parseEmbedParam('paragraph,,media,')).toEqual(['paragraph', 'media']);
  });
});

// --- resolveReference ---

describe('resolveReference', () => {
  beforeAll(async () => {
    await writeEntity('taxonomy_term', 5, 'en', { tid: 5, name: 'Noticias' });
    await writeEntity('media', 42, 'en', { mid: 42, name: 'hero.jpg' });
  });

  it('resolves existing entity reference', async () => {
    const result = await resolveReference('taxonomy_term', 5, 'en', exportDir);
    expect(result).toEqual({ tid: 5, name: 'Noticias' });
  });

  it('resolves another entity type', async () => {
    const result = await resolveReference('media', 42, 'en', exportDir);
    expect(result).toEqual({ mid: 42, name: 'hero.jpg' });
  });

  it('returns null for missing entity', async () => {
    const result = await resolveReference('taxonomy_term', 9999, 'en', exportDir);
    expect(result).toBeNull();
  });

  it('returns null for wrong language', async () => {
    const result = await resolveReference('taxonomy_term', 5, 'es', exportDir);
    expect(result).toBeNull();
  });

  it('returns null for invalid entity type', async () => {
    const result = await resolveReference('nonexistent', 1, 'en', exportDir);
    expect(result).toBeNull();
  });
});

// --- hydrateEntity ---

describe('hydrateEntity', () => {
  beforeAll(async () => {
    await writeEntity('taxonomy_term', 10, 'en', { tid: 10, name: 'Tag A' });
    await writeEntity('taxonomy_term', 11, 'en', { tid: 11, name: 'Tag B' });
    await writeEntity('media', 20, 'en', {
      mid: 20,
      name: 'image.jpg',
      field_media_image: [{ target_id: 50, target_type: 'file', uri: 'public://img.jpg' }],
    });
    await writeEntity('paragraph', 30, 'en', {
      pid: 30,
      field_text: 'Hello',
      field_image: [{ target_id: 20, target_type: 'media' }],
    });
  });

  it('returns unchanged entity when embedTypes is empty', async () => {
    const entity = {
      nid: 1,
      field_tags: [{ target_id: 10, target_type: 'taxonomy_term' }],
    };
    const result = await hydrateEntity(entity, [], exportDir, 'en');
    expect(result.field_tags[0].target_id).toBe(10);
    expect(result.field_tags[0].target_type).toBe('taxonomy_term');
  });

  it('hydrates single reference', async () => {
    const entity = {
      nid: 1,
      field_tag: { target_id: 10, target_type: 'taxonomy_term' },
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.field_tag).toEqual({ tid: 10, name: 'Tag A' });
  });

  it('hydrates array of references', async () => {
    const entity = {
      nid: 1,
      field_tags: [
        { target_id: 10, target_type: 'taxonomy_term' },
        { target_id: 11, target_type: 'taxonomy_term' },
      ],
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.field_tags).toEqual([
      { tid: 10, name: 'Tag A' },
      { tid: 11, name: 'Tag B' },
    ]);
  });

  it('only hydrates configured types, leaves others as references', async () => {
    const entity = {
      nid: 1,
      field_tag: { target_id: 10, target_type: 'taxonomy_term' },
      field_media: { target_id: 20, target_type: 'media' },
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.field_tag).toEqual({ tid: 10, name: 'Tag A' });
    expect(result.field_media).toEqual({ target_id: 20, target_type: 'media' });
  });

  it('hydrates with wildcard * for all types', async () => {
    const entity = {
      nid: 1,
      field_tag: { target_id: 10, target_type: 'taxonomy_term' },
      field_media: { target_id: 20, target_type: 'media' },
    };
    const result = await hydrateEntity(entity, ['*'], exportDir, 'en');
    expect(result.field_tag).toEqual({ tid: 10, name: 'Tag A' });
    expect(result.field_media.mid).toBe(20);
  });

  it('preserves non-reference fields unchanged', async () => {
    const entity = {
      nid: 1,
      title: 'About Us',
      status: true,
      field_tags: [{ target_id: 10, target_type: 'taxonomy_term' }],
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.nid).toBe(1);
    expect(result.title).toBe('About Us');
    expect(result.status).toBe(true);
  });

  it('hydrates recursively within depth limit', async () => {
    const entity = {
      pid: 30,
      field_image: [{ target_id: 20, target_type: 'media' }],
    };
    const result = await hydrateEntity(entity, ['media', 'paragraph'], exportDir, 'en', 0, 2);
    // media should be hydrated
    expect(result.field_image[0].mid).toBe(20);
    // media has field_media_image referencing file, but 'file' not in embedTypes
    expect(result.field_image[0].field_media_image[0].target_type).toBe('file');
  });

  it('stops recursion at maxDepth=0 (no nested hydration)', async () => {
    const entity = {
      pid: 30,
      field_image: [{ target_id: 20, target_type: 'media' }],
    };
    const result = await hydrateEntity(entity, ['media', 'paragraph'], exportDir, 'en', 0, 0);
    // depth starts at 0, maxDepth is 0, depth <= maxDepth → processes fields
    // media at depth 0 is resolved, then hydrateEntity called with depth=1 > maxDepth=0
    // → media is returned as-is (its inner refs NOT hydrated)
    expect(result.field_image[0].mid).toBe(20);
    expect(result.field_image[0].field_media_image[0].target_type).toBe('file');
    expect(result.field_image[0].field_media_image[0].target_id).toBe(50);
  });

  it('returns reference as-is when referenced entity file is missing', async () => {
    const entity = {
      nid: 1,
      field_tag: { target_id: 9999, target_type: 'taxonomy_term' },
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.field_tag).toEqual({ target_id: 9999, target_type: 'taxonomy_term' });
  });

  it('handles null and undefined field values', async () => {
    const entity = {
      nid: 1,
      title: null,
      field_empty: undefined,
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.title).toBeNull();
    expect(result.field_empty).toBeUndefined();
  });

  it('handles nested arrays of mixed content', async () => {
    const entity = {
      nid: 1,
      field_items: [
        { target_id: 10, target_type: 'taxonomy_term' },
        'just a string',
        42,
        { target_id: 9999, target_type: 'nonexistent' },
      ],
    };
    const result = await hydrateEntity(entity, ['taxonomy_term'], exportDir, 'en');
    expect(result.field_items[0]).toEqual({ tid: 10, name: 'Tag A' });
    expect(result.field_items[1]).toBe('just a string');
    expect(result.field_items[2]).toBe(42);
    expect(result.field_items[3]).toEqual({ target_id: 9999, target_type: 'nonexistent' });
  });
});
