/**
 * Path utilities for entity_json_suite Node.js server.
 *
 * Mirrors the logic of Drupal's AliasBucketManager so the Node.js server
 * can resolve aliases and entity paths using the same conventions.
 */

/**
 * Returns the bucket number for a numeric entity ID.
 * Matches FileStorageManager::getEntityDirectory():
 *   floor(entity_id / 1000)
 */
export function bucketFromId(entityId) {
  const id = parseInt(String(entityId), 10);
  if (Number.isNaN(id) || id < 0) {
    throw new Error(`Invalid entity ID: ${entityId}`);
  }
  return Math.floor(id / 1000);
}

/**
 * Mirrors AliasBucketManager::sanitizeAliasPath().
 *
 * Strips path traversal sequences, removes non-alphanumeric characters
 * (except dash and underscore), and removes empty/`.`/`..` segments.
 *
 * Always returns a leading slash.
 */
export function sanitizeAliasPath(alias) {
  const normalized = String(alias).replace(/\\/g, '/');
  const parts = normalized.split('/');
  const safe = [];

  for (const part of parts) {
    if (part === '' || part === '.' || part === '..') {
      continue;
    }
    const cleaned = part.replace(/[^a-zA-Z0-9\-_]/g, '');
    if (cleaned !== '') {
      safe.push(cleaned);
    }
  }

  return '/' + safe.join('/');
}

/**
 * Returns the entity JSON file path on disk.
 *
 * Pattern: <exportDir>/<entity_type>/<bucket>/<entity_id>/<langcode>.json
 */
export function entityFilePath(exportDir, entityType, entityId, langcode) {
  const bucket = bucketFromId(entityId);
  return {
    file: `${exportDir}/${entityType}/${bucket}/${entityId}/${langcode}.json`,
    bucket,
  };
}

/**
 * Returns the alias bucket directory for a given alias.
 *
 * Pattern: <aliasDir>/<langcode>/<bucket>/<sanitizedAlias>/data.json
 *
 * Note: bucket is the alias path's bucket (floor(pid/1000)),
 * but at serving time we don't know the pid, so the resolver iterates
 * all possible buckets. This function builds a path *given* a known bucket.
 */
export function aliasDataPath(aliasDir, langcode, bucket, sanitizedAlias) {
  return `${aliasDir}/${langcode}/${bucket}${sanitizedAlias}/data.json`;
}

/**
 * Validates a language code from the URL.
 *
 * Accepts: ISO 639-1 (en, es, fr), ISO 639-2/T (haw, yue), and
 * locale variants (en-GB, pt-BR, zh-CN).
 *
 * Returns false for non-string values, empty strings, or values containing
 * characters outside the allowed set (defends against path traversal).
 *
 * @param {unknown} lang
 * @returns {boolean}
 */
export function isValidLangcode(lang) {
  if (typeof lang !== 'string' || lang === '') {
    return false;
  }
  return /^[a-z]{2,3}(-[A-Z]{2})?$/.test(lang);
}
