/**
 * ETag helpers based on file modification time and size.
 *
 * Returns a strong ETag suitable for If-None-Match comparisons.
 * Format: `"<mtimeMs>-<size>"` (quoted, per RFC 7232).
 */

import { stat } from 'node:fs/promises';

/**
 * Builds a strong ETag from file metadata.
 *
 * @param {string} filePath
 * @returns {Promise<string|null>} The ETag string, or null on error.
 */
export async function buildEtag(filePath) {
  try {
    const stats = await stat(filePath);
    return `"${Math.floor(stats.mtimeMs)}-${stats.size}"`;
  }
  catch {
    return null;
  }
}

/**
 * Returns true if the request's If-None-Match header matches the ETag.
 * Supports both single ETag and comma-separated list.
 *
 * @param {string} ifNoneMatch
 * @param {string} etag
 * @returns {boolean}
 */
export function isNotModified(ifNoneMatch, etag) {
  if (!ifNoneMatch || !etag) {
    return false;
  }
  return ifNoneMatch === etag || ifNoneMatch === '*' || ifNoneMatch.split(',').map(s => s.trim()).includes(etag);
}
