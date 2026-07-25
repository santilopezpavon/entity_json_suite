/**
 * Shared response helpers for serving static JSON files with HTTP cache support.
 *
 * Centralizes the ETag generation, 304 Not Modified handling, and 200 response
 * shape that is shared by all routes (entity, view, alias).
 */

import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { buildEtag, isNotModified } from './etag.js';
import { hydrateEntity } from './hydrate.js';

/**
 * Standard cache headers applied to all JSON responses.
 *
 * @param {number} maxAge  Cache-Control max-age in seconds.
 * @returns {string}
 */
export function cacheControlHeader(maxAge) {
  return `public, max-age=${maxAge}`;
}

/**
 * Serves a JSON file from disk with ETag-based conditional caching.
 *
 * Implements the standard pattern used by all routes:
 *   1. If `If-None-Match` matches the current ETag → 304 Not Modified.
 *   2. Otherwise → 200 with the file content and cache headers.
 *
 * Returns a Fastify response (does not throw). If the file is missing
 * or unreadable, returns a 404 response.
 *
 * @param {import('fastify').FastifyRequest} request
 * @param {import('fastify').FastifyReply} reply
 * @param {string} filePath
 *   Absolute path to the JSON file on disk.
 * @param {number} maxAge
 *   Cache-Control max-age in seconds.
 * @param {Record<string, string>} [extraHeaders={}]
 *   Additional response headers to add (e.g. X-Entity-Type).
 * @returns {Promise<import('fastify').FastifyReply>}
 */
export async function serveJsonFile(request, reply, filePath, maxAge, extraHeaders = {}) {
  const etag = await buildEtag(filePath);
  if (!etag) {
    return reply.code(404).send({ error: 'JSON file not found.' });
  }

  if (isNotModified(request.headers['if-none-match'], etag)) {
    return reply.code(304).header('ETag', etag).send();
  }

  const content = await readFile(filePath, 'utf-8');
  const response = reply
    .code(200)
    .header('Content-Type', 'application/json')
    .header('Cache-Control', cacheControlHeader(maxAge))
    .header('ETag', etag);

  for (const [name, value] of Object.entries(extraHeaders)) {
    response.header(name, value);
  }

  return response.send(content);
}

/**
 * Serves a hydrated JSON file with embedded entity references.
 *
 * Reads the raw file, parses it, hydrates entity references matching the
 * given embed types recursively, then serves the result with an ETag
 * based on the hydrated content (not file metadata).
 *
 * @param {import('fastify').FastifyRequest} request
 * @param {import('fastify').FastifyReply} reply
 * @param {string} filePath
 *   Absolute path to the raw JSON file on disk.
 * @param {string} exportDir
 *   Base export directory for resolving referenced entities.
 * @param {string} lang
 *   Language code for resolving referenced entities.
 * @param {string[]} embedTypes
 *   Entity type IDs to hydrate (from parseEmbedParam).
 * @param {number} maxDepth
 *   Maximum recursion depth.
 * @param {number} maxAge
 *   Cache-Control max-age in seconds.
 * @param {Record<string, string>} [extraHeaders={}]
 *   Additional response headers.
 * @returns {Promise<import('fastify').FastifyReply>}
 */
export async function serveHydratedJson(request, reply, filePath, exportDir, lang, embedTypes, maxDepth, maxAge, extraHeaders = {}) {
  let content;
  try {
    content = await readFile(filePath, 'utf-8');
  }
  catch {
    return reply.code(404).send({ error: 'JSON file not found.' });
  }

  const parsed = JSON.parse(content);
  const hydrated = await hydrateEntity(parsed, embedTypes, exportDir, lang, 0, maxDepth);
  const json = JSON.stringify(hydrated);

  const etag = `"${createHash('md5').update(json).digest('hex')}"`;

  if (isNotModified(request.headers['if-none-match'], etag)) {
    return reply.code(304).header('ETag', etag).send();
  }

  const response = reply
    .code(200)
    .header('Content-Type', 'application/json')
    .header('Cache-Control', cacheControlHeader(maxAge))
    .header('ETag', etag);

  for (const [name, value] of Object.entries(extraHeaders)) {
    response.header(name, value);
  }

  return response.send(json);
}
