/**
 * Entity hydration — replaces entity references with full entity data.
 *
 * When a client requests an entity with `?embed=paragraph,media`, the server
 * parses the JSON, finds references to the configured entity types, reads
 * their JSON files from disk, and replaces the reference objects with the
 * full entity data. This is recursive up to a configurable depth limit.
 *
 * A value is considered an entity reference if it is an object containing
 * both `target_id` and `target_type` keys (matching Drupal's
 * EntityReferenceFieldItemNormalizer output).
 */

import { readFile } from 'node:fs/promises';
import { entityFilePath } from './path-utils.js';

/**
 * Determines whether a value is a Drupal entity reference.
 *
 * Entity references produced by Drupal's EntityReferenceFieldItemNormalizer
 * always contain `target_id` and `target_type`.
 *
 * @param {unknown} value
 * @returns {boolean}
 */
export function isEntityReference(value) {
  return value !== null
    && typeof value === 'object'
    && !Array.isArray(value)
    && 'target_id' in value
    && 'target_type' in value;
}

/**
 * Reads and parses a single referenced entity JSON file from disk.
 *
 * @param {string} entityType
 *   Entity type ID (e.g. 'media', 'paragraph').
 * @param {string|number} entityId
 *   Entity ID.
 * @param {string} lang
 *   Language code.
 * @param {string} exportDir
 *   Base export directory.
 * @returns {Promise<object|null>}
 *   Parsed JSON object, or null if the file doesn't exist.
 */
export async function resolveReference(entityType, entityId, lang, exportDir) {
  try {
    const { file } = entityFilePath(exportDir, entityType, entityId, lang);
    const content = await readFile(file, 'utf-8');
    return JSON.parse(content);
  }
  catch {
    return null;
  }
}

/**
 * Parses the `?embed` query parameter into an array of entity type IDs.
 *
 * Accepts:
 *   - `?embed=*`           → all types (returned as ['*'])
 *   - `?embed=paragraph`   → single type
 *   - `?embed=paragraph,media` → multiple types
 *
 * @param {string|undefined} embedParam
 *   Raw query parameter value.
 * @returns {string[]}
 *   Array of entity type IDs to hydrate. Empty array means no hydration.
 */
export function parseEmbedParam(embedParam) {
  if (!embedParam || typeof embedParam !== 'string') {
    return [];
  }
  const trimmed = embedParam.trim();
  if (trimmed === '') {
    return [];
  }
  if (trimmed === '*') {
    return ['*'];
  }
  return trimmed.split(',').map(s => s.trim()).filter(s => s.length > 0);
}

/**
 * Recursively hydrates entity references in a parsed JSON object.
 *
 * For each top-level or nested value:
 *   - If it is an entity reference whose `target_type` is in `embedTypes`
 *     (or if embedTypes is `['*']`), the referenced entity is loaded from
 *     disk and its full JSON replaces the reference object.
 *   - The replacement is itself hydrated recursively, up to `maxDepth`.
 *   - Values that are not matching references are returned unchanged.
 *
 * @param {object} parsed
 *   The parsed JSON of the entity to hydrate.
 * @param {string[]} embedTypes
 *   Entity type IDs to hydrate. `['*']` hydrates all reference types.
 * @param {string} exportDir
 *   Base export directory for entity JSON files.
 * @param {string} lang
 *   Language code for resolving referenced entities.
 * @param {number} depth
 *   Current recursion depth (starts at 0).
 * @param {number} maxDepth
 *   Maximum recursion depth.
 * @returns {Promise<object>}
 *   The hydrated entity object.
 */
export async function hydrateEntity(parsed, embedTypes, exportDir, lang, depth = 0, maxDepth = 2) {
  if (depth > maxDepth) {
    return parsed;
  }

  const result = { ...parsed };

  for (const [key, value] of Object.entries(result)) {
    result[key] = await hydrateValue(value, embedTypes, exportDir, lang, depth, maxDepth);
  }

  return result;
}

/**
 * Hydrates a single value, handling both individual references and arrays.
 *
 * @param {unknown} value
 *   The value to potentially hydrate.
 * @param {string[]} embedTypes
 *   Entity type IDs to hydrate.
 * @param {string} exportDir
 *   Base export directory.
 * @param {string} lang
 *   Language code.
 * @param {number} depth
 *   Current recursion depth.
 * @param {number} maxDepth
 *   Maximum recursion depth.
 * @returns {Promise<unknown>}
 *   The (possibly hydrated) value.
 */
async function hydrateValue(value, embedTypes, exportDir, lang, depth, maxDepth) {
  if (Array.isArray(value)) {
    return Promise.all(
      value.map(item => hydrateValue(item, embedTypes, exportDir, lang, depth, maxDepth)),
    );
  }

  if (!isEntityReference(value)) {
    return value;
  }

  const shouldEmbed = embedTypes.includes('*') || embedTypes.includes(value.target_type);
  if (!shouldEmbed) {
    return value;
  }

  const refEntity = await resolveReference(value.target_type, value.target_id, lang, exportDir);
  if (!refEntity) {
    return value;
  }

  return hydrateEntity(refEntity, embedTypes, exportDir, lang, depth + 1, maxDepth);
}
