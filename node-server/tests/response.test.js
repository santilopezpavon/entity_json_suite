import { describe, it, expect } from 'vitest';
import { cacheControlHeader, serveJsonFile } from '../lib/response.js';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

describe('cacheControlHeader', () => {
  it('builds public max-age header', () => {
    expect(cacheControlHeader(3600)).toBe('public, max-age=3600');
  });

  it('handles zero', () => {
    expect(cacheControlHeader(0)).toBe('public, max-age=0');
  });
});

describe('serveJsonFile', () => {
  it('sends 200 with file content and cache headers when no If-None-Match', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'serve-'));
    const file = join(dir, 'data.json');
    await writeFile(file, '{"a":1}');

    try {
      const reply = makeReply();
      const request = makeRequest();
      const res = await serveJsonFile(request, reply, file, 3600);
      expect(res.statusCode).toBe(200);
      expect(res.headers['content-type']).toContain('application/json');
      expect(res.headers['cache-control']).toBe('public, max-age=3600');
      expect(res.headers['etag']).toMatch(/^"\d+-\d+"$/);
      expect(res.body).toBe('{"a":1}');
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });

  it('adds extra headers when provided', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'serve-'));
    const file = join(dir, 'data.json');
    await writeFile(file, '{"a":1}');

    try {
      const reply = makeReply();
      const request = makeRequest();
      const res = await serveJsonFile(request, reply, file, 60, {
        'X-Custom-Header': 'value',
      });
      expect(res.headers['x-custom-header']).toBe('value');
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });

  it('returns 304 when If-None-Match matches', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'serve-'));
    const file = join(dir, 'data.json');
    await writeFile(file, '{"a":1}');

    try {
      // First call to get the ETag.
      const firstReply = makeReply();
      const firstRequest = makeRequest();
      const first = await serveJsonFile(firstRequest, firstReply, file, 3600);
      const etag = first.headers['etag'];

      // Second call with matching If-None-Match.
      const reply = makeReply();
      const request = makeRequest({ 'if-none-match': etag });
      const res = await serveJsonFile(request, reply, file, 3600);
      expect(res.statusCode).toBe(304);
      expect(res.body).toBeUndefined();
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });

  it('returns 304 for wildcard If-None-Match', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'serve-'));
    const file = join(dir, 'data.json');
    await writeFile(file, '{"a":1}');

    try {
      const reply = makeReply();
      const request = makeRequest({ 'if-none-match': '*' });
      const res = await serveJsonFile(request, reply, file, 3600);
      expect(res.statusCode).toBe(304);
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });

  it('returns 404 for missing file', async () => {
    const reply = makeReply();
    const request = makeRequest();
    const res = await serveJsonFile(request, reply, '/nonexistent/file.json', 3600);
    expect(res.statusCode).toBe(404);
    expect(res.body).toEqual({ error: 'JSON file not found.' });
  });

  it('returns 200 when If-None-Match does not match', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'serve-'));
    const file = join(dir, 'data.json');
    await writeFile(file, '{"a":1}');

    try {
      const reply = makeReply();
      const request = makeRequest({ 'if-none-match': '"old-etag"' });
      const res = await serveJsonFile(request, reply, file, 3600);
      expect(res.statusCode).toBe(200);
      expect(res.body).toBe('{"a":1}');
    }
    finally {
      await rm(dir, { recursive: true });
    }
  });
});

/**
 * Minimal Fastify reply stub for unit testing — records header and body
 * mutations without doing real I/O.
 */
function makeReply() {
  const headers = {};
  let statusCode = 200;
  let body;

  return {
    get statusCode() {
      return statusCode;
    },
    get headers() {
      return headers;
    },
    get body() {
      return body;
    },
    code(c) {
      statusCode = c;
      return this;
    },
    header(name, value) {
      headers[name.toLowerCase()] = value;
      return this;
    },
    send(payload) {
      body = payload;
      return this;
    },
  };
}

/**
 * Minimal Fastify request stub.
 */
function makeRequest(headers = {}) {
  return { headers };
}
