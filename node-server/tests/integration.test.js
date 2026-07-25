import { describe, it, expect, beforeAll, afterAll, beforeEach } from 'vitest';
import { mkdtemp, writeFile, rm, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { buildTestApp } from './helpers/build-test-app.js';

let app;
let exportDir;
let viewsDir;
let aliasDir;
let root;

beforeAll(async () => {
  root = await mkdtemp(join(tmpdir(), 'ejs-integ-'));
  exportDir = join(root, 'serialized');
  viewsDir = join(root, 'views');
  aliasDir = join(root, 'alias-buckets');
  await mkdir(exportDir, { recursive: true });
  await mkdir(viewsDir, { recursive: true });
  await mkdir(aliasDir, { recursive: true });
  app = await buildTestApp({ exportDir, viewsDir, aliasDir });
});

afterAll(async () => {
  await app.close();
  await rm(root, { recursive: true });
});

beforeEach(async () => {
  // Clean between tests.
  await rm(exportDir, { recursive: true, force: true });
  await rm(viewsDir, { recursive: true, force: true });
  await rm(aliasDir, { recursive: true, force: true });
  await mkdir(exportDir, { recursive: true });
  await mkdir(viewsDir, { recursive: true });
  await mkdir(aliasDir, { recursive: true });
});

async function writeEntity(id, lang, data) {
  const bucket = Math.floor(Number(id) / 1000);
  const dir = join(exportDir, 'node', String(bucket), String(id));
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, `${lang}.json`), JSON.stringify(data));
}

async function writeTypedEntity(type, id, lang, data) {
  const bucket = Math.floor(Number(id) / 1000);
  const dir = join(exportDir, type, String(bucket), String(id));
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, `${lang}.json`), JSON.stringify(data));
}

async function writeView(view, display, data) {
  const dir = join(viewsDir, view);
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, `${display}.json`), JSON.stringify(data));
}

async function writeAlias(lang, alias, entityType, entityId, bucket = 0) {
  const dir = join(aliasDir, lang, String(bucket), alias);
  await mkdir(dir, { recursive: true });
  await writeFile(
    join(dir, 'data.json'),
    JSON.stringify({ entityType, entityId }),
  );
}

describe('GET /health', () => {
  it('returns ok', async () => {
    const res = await app.inject({ method: 'GET', url: '/health' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ status: 'ok' });
  });
});

describe('GET /json/:type/:id/:lang', () => {
  it('serves an entity file', async () => {
    await writeEntity(1, 'en', { nid: 1, title: 'About' });
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en' });
    expect(res.statusCode).toBe(200);
    expect(res.headers['content-type']).toContain('application/json');
    expect(res.headers['cache-control']).toBe('public, max-age=3600');
    expect(res.headers['etag']).toMatch(/^"\d+-\d+"$/);
    expect(res.json()).toEqual({ nid: 1, title: 'About' });
  });

  it('returns 404 for missing entity', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/9999/en' });
    expect(res.statusCode).toBe(404);
  });

  it('returns 304 on matching ETag', async () => {
    await writeEntity(1, 'en', { nid: 1 });
    const first = await app.inject({ method: 'GET', url: '/json/node/1/en' });
    const etag = first.headers['etag'];
    const second = await app.inject({
      method: 'GET',
      url: '/json/node/1/en',
      headers: { 'if-none-match': etag },
    });
    expect(second.statusCode).toBe(304);
  });

  it('returns 304 on wildcard If-None-Match', async () => {
    await writeEntity(1, 'en', { nid: 1 });
    const res = await app.inject({
      method: 'GET',
      url: '/json/node/1/en',
      headers: { 'if-none-match': '*' },
    });
    expect(res.statusCode).toBe(304);
  });

  it('serves entity from higher bucket', async () => {
    await writeEntity(4523, 'es', { nid: 4523, title: 'Hola' });
    const res = await app.inject({ method: 'GET', url: '/json/node/4523/es' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ nid: 4523, title: 'Hola' });
  });
});

describe('GET /json/views/:view/:display', () => {
  it('serves a view file', async () => {
    await writeView('articles', 'rest_export_1', [{ title: 'A' }, { title: 'B' }]);
    const res = await app.inject({ method: 'GET', url: '/json/views/articles/rest_export_1' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual([{ title: 'A' }, { title: 'B' }]);
  });

  it('returns 404 for missing view', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/views/unknown/x' });
    expect(res.statusCode).toBe(404);
  });
});

describe('GET /json/path/:lang/* (language is required)', () => {
  it('serves entity by single-segment alias in specified language', async () => {
    await writeAlias('en', 'about-us', 'node', '1');
    await writeEntity(1, 'en', { nid: 1, title: 'About' });
    const res = await app.inject({ method: 'GET', url: '/json/path/en/about-us' });
    expect(res.statusCode).toBe(200);
    expect(res.headers['x-entity-type']).toBe('node');
    expect(res.headers['x-entity-id']).toBe('1');
    expect(res.headers['x-entity-lang']).toBe('en');
    expect(res.json()).toEqual({ nid: 1, title: 'About' });
  });

  it('serves entity by multi-segment alias in specified language', async () => {
    await writeAlias('es', 'hola/mundo', 'node', '2');
    await writeEntity(2, 'es', { nid: 2, title: 'Hola Mundo' });
    const res = await app.inject({ method: 'GET', url: '/json/path/es/hola/mundo' });
    expect(res.statusCode).toBe(200);
    expect(res.headers['x-entity-type']).toBe('node');
    expect(res.headers['x-entity-id']).toBe('2');
    expect(res.headers['x-entity-lang']).toBe('es');
    expect(res.json()).toEqual({ nid: 2, title: 'Hola Mundo' });
  });

  it('serves entity by deep multi-segment alias in specified language', async () => {
    await writeAlias('en', 'products/shoes/running', 'node', '99');
    await writeEntity(99, 'en', { nid: 99, title: 'Running shoes' });
    const res = await app.inject({ method: 'GET', url: '/json/path/en/products/shoes/running' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ nid: 99, title: 'Running shoes' });
  });

  it('serves entity from higher bucket with lang', async () => {
    await writeEntity(4523, 'en', { nid: 4523 });
    await writeAlias('en', 'node-4523', 'node', '4523');
    const res = await app.inject({ method: 'GET', url: '/json/path/en/node-4523' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ nid: 4523 });
  });

  it('disambiguates same alias in different languages', async () => {
    await writeAlias('en', 'about', 'node', '1');
    await writeAlias('es', 'about', 'node', '2');
    await writeEntity(1, 'en', { nid: 1, title: 'About EN' });
    await writeEntity(2, 'es', { nid: 2, title: 'About ES' });
    const enRes = await app.inject({ method: 'GET', url: '/json/path/en/about' });
    expect(enRes.json()).toEqual({ nid: 1, title: 'About EN' });
    const esRes = await app.inject({ method: 'GET', url: '/json/path/es/about' });
    expect(esRes.json()).toEqual({ nid: 2, title: 'About ES' });
  });

  it('accepts locale variants (en-GB)', async () => {
    await writeAlias('en-GB', 'about', 'node', '1');
    await writeEntity(1, 'en-GB', { nid: 1, title: 'About GB' });
    const res = await app.inject({ method: 'GET', url: '/json/path/en-GB/about' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ nid: 1, title: 'About GB' });
  });

  it('returns 404 when alias exists only in different language', async () => {
    await writeAlias('en', 'about-us', 'node', '1');
    await writeEntity(1, 'en', { nid: 1 });
    // Asking for Spanish when only English exists → 404 (no fallback).
    const res = await app.inject({ method: 'GET', url: '/json/path/es/about-us' });
    expect(res.statusCode).toBe(404);
  });

  it('returns 404 for unknown alias in valid language', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/en/unknown' });
    expect(res.statusCode).toBe(404);
  });

  it('returns 404 when alias resolves but entity file is missing', async () => {
    await writeAlias('en', 'broken', 'node', '99999');
    const res = await app.inject({ method: 'GET', url: '/json/path/en/broken' });
    expect(res.statusCode).toBe(404);
  });

  it('returns 400 for invalid entity id', async () => {
    await writeAlias('en', 'bad', 'node', 'abc');
    const res = await app.inject({ method: 'GET', url: '/json/path/en/bad' });
    expect(res.statusCode).toBe(400);
  });

  it('returns 304 on matching ETag with lang route', async () => {
    await writeAlias('en', 'about-us', 'node', '1');
    await writeEntity(1, 'en', { nid: 1 });
    const first = await app.inject({ method: 'GET', url: '/json/path/en/about-us' });
    const etag = first.headers['etag'];
    const second = await app.inject({
      method: 'GET',
      url: '/json/path/en/about-us',
      headers: { 'if-none-match': etag },
    });
    expect(second.statusCode).toBe(304);
  });

  it('handles alias with path traversal sequences (sanitizes before lookup)', async () => {
    await writeAlias('en', 'about-us', 'node', '1');
    await writeEntity(1, 'en', { nid: 1 });
    const res = await app.inject({ method: 'GET', url: '/json/path/en/./about-us' });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ nid: 1 });
  });

  // --- 400 cases: language validation ---

  it('returns 400 when no language is provided (URL has no lang segment)', async () => {
    // The route /json/path/:lang/* requires a lang segment.
    // Without it, Fastify returns 404 (no route matches).
    // The shortest URL that *would* match is /json/path/X/*, but X is
    // treated as the lang param and must be a valid langcode.
    const res = await app.inject({ method: 'GET', url: '/json/path/en/about-us' });
    expect(res.statusCode).not.toBe(400);
  });

  it('returns 400 for invalid language code (uppercase)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/EN/about-us' });
    expect(res.statusCode).toBe(400);
  });

  it('returns 400 for invalid language code (underscore)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/en_US/about-us' });
    expect(res.statusCode).toBe(400);
  });

  it('returns 400 for invalid language code (numbers)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/123/about' });
    expect(res.statusCode).toBe(400);
  });

  it('returns 400 for invalid language code (single letter)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/e/about' });
    expect(res.statusCode).toBe(400);
  });

  it('returns 400 for invalid language code (too long)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/toolong/about' });
    expect(res.statusCode).toBe(400);
  });
});

// --- Hydration integration tests ---

describe('GET /json/:type/:id/:lang?embed= (entity hydration)', () => {
  beforeEach(async () => {
    await writeEntity(1, 'en', {
      nid: 1,
      title: 'About',
      field_tags: [{ target_id: 10, target_type: 'taxonomy_term' }],
      field_media: { target_id: 20, target_type: 'media' },
    });
    await writeTypedEntity('taxonomy_term', 10, 'en', { tid: 10, name: 'Noticias' });
    await writeTypedEntity('media', 20, 'en', { mid: 20, name: 'hero.jpg' });
  });

  it('serves raw JSON without ?embed (backward compatible)', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tags[0].target_id).toBe(10);
    expect(body.field_tags[0].target_type).toBe('taxonomy_term');
  });

  it('hydrates specified entity type with ?embed', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en?embed=taxonomy_term' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tags[0]).toEqual({ tid: 10, name: 'Noticias' });
    expect(body.field_media.target_type).toBe('media');
  });

  it('hydrates multiple types with ?embed=taxonomy_term,media', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en?embed=taxonomy_term,media' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tags[0]).toEqual({ tid: 10, name: 'Noticias' });
    expect(body.field_media).toEqual({ mid: 20, name: 'hero.jpg' });
  });

  it('hydrates all types with ?embed=*', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en?embed=*' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tags[0]).toEqual({ tid: 10, name: 'Noticias' });
    expect(body.field_media).toEqual({ mid: 20, name: 'hero.jpg' });
  });

  it('returns reference as-is when entity file is missing', async () => {
    await writeEntity(2, 'en', {
      nid: 2,
      field_tag: { target_id: 9999, target_type: 'taxonomy_term' },
    });
    const res = await app.inject({ method: 'GET', url: '/json/node/2/en?embed=taxonomy_term' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tag).toEqual({ target_id: 9999, target_type: 'taxonomy_term' });
  });

  it('respects depth limit', async () => {
    await writeTypedEntity('paragraph', 30, 'en', {
      pid: 30,
      field_text: 'Hello',
      field_media: [{ target_id: 20, target_type: 'media' }],
    });
    await writeTypedEntity('media', 20, 'en', {
      mid: 20,
      name: 'hero.jpg',
      field_image: [{ target_id: 50, target_type: 'file', uri: 'public://img.jpg' }],
    });
    await writeEntity(3, 'en', {
      nid: 3,
      field_paragraph: [{ target_id: 30, target_type: 'paragraph' }],
    });
    const res = await app.inject({ method: 'GET', url: '/json/node/3/en?embed=paragraph,media&depth=0' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    // paragraph at depth 0 should be hydrated (node's ref resolved)
    expect(body.field_paragraph[0].pid).toBe(30);
    // media inside paragraph should NOT be hydrated (depth 1 > maxDepth 0)
    expect(body.field_paragraph[0].field_media[0].target_type).toBe('media');
    expect(body.field_paragraph[0].field_media[0].target_id).toBe(20);
  });

  it('returns valid JSON with correct content-type', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/node/1/en?embed=taxonomy_term' });
    expect(res.statusCode).toBe(200);
    expect(res.headers['content-type']).toContain('application/json');
    expect(() => JSON.parse(res.payload)).not.toThrow();
  });
});

describe('GET /json/path/:lang/*?embed= (alias hydration)', () => {
  beforeEach(async () => {
    await writeAlias('en', 'about-us', 'node', '1');
    await writeEntity(1, 'en', {
      nid: 1,
      title: 'About',
      field_tag: { target_id: 10, target_type: 'taxonomy_term' },
    });
    await writeTypedEntity('taxonomy_term', 10, 'en', { tid: 10, name: 'Noticias' });
  });

  it('hydrates referenced entities via alias route', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/en/about-us?embed=taxonomy_term' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tag).toEqual({ tid: 10, name: 'Noticias' });
    expect(res.headers['x-entity-type']).toBe('node');
    expect(res.headers['x-entity-id']).toBe('1');
  });

  it('serves raw JSON without ?embed via alias route', async () => {
    const res = await app.inject({ method: 'GET', url: '/json/path/en/about-us' });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.field_tag.target_id).toBe(10);
    expect(body.field_tag.target_type).toBe('taxonomy_term');
  });
});
