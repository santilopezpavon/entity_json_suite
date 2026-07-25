/**
 * Resolves a path alias to an entity and serves its JSON file.
 *
 * Route:
 *   GET /json/path/{lang}/{alias}
 *
 * The language code is mandatory and validated against an ISO-like regex
 * before any filesystem access. This makes the URL self-describing and
 * the lookup deterministic — there is no ambiguity about which language
 * will be returned.
 *
 * Multi-segment aliases are supported via the wildcard `*`:
 *   /json/path/es/hola/mundo/3
 *
 * Resolution:
 *   1. Validate the lang code (regex: ^[a-z]{2,3}(-[A-Z]{2})?$).
 *   2. Sanitize the alias (matches Drupal's AliasBucketManager logic).
 *   3. Search <ALIAS_DIR>/<lang>/<bucket>/<sanitizedAlias>/data.json.
 *   4. Read the entity type and ID from data.json.
 *   5. Serve <EXPORT_DIR>/<type>/<bucket>/<id>/<lang>.json.
 */

import { resolveAlias } from '../lib/alias-resolver.js';
import { entityFilePath, isValidLangcode } from '../lib/path-utils.js';
import { serveJsonFile, serveHydratedJson } from '../lib/response.js';
import { parseEmbedParam } from '../lib/hydrate.js';

const DEFAULT_MAX_DEPTH = 2;

function parseDepth(value) {
  const n = parseInt(value, 10);
  return Number.isFinite(n) ? Math.min(Math.max(n, 0), 10) : DEFAULT_MAX_DEPTH;
}

export default async function aliasRoutes(fastify, opts) {
  const { paths, cache } = opts;

  fastify.get('/json/path/:lang/*', async (request, reply) => {
    const lang = request.params.lang;
    const alias = request.params['*'] ?? '';

    if (!isValidLangcode(lang)) {
      return reply.code(400).send({ error: 'Invalid language code.' });
    }

    const resolved = await resolveAlias(paths.alias, alias, lang);
    if (!resolved) {
      return reply.code(404).send({ error: 'Alias not found.' });
    }

    let filePath;
    try {
      ({ file: filePath } = entityFilePath(
        paths.export,
        resolved.entityType,
        resolved.entityId,
        resolved.langcode,
      ));
    }
    catch (err) {
      return reply.code(400).send({ error: err.message });
    }

    const extraHeaders = {
      'X-Entity-Type': resolved.entityType,
      'X-Entity-Id': resolved.entityId,
      'X-Entity-Lang': resolved.langcode,
    };

    const embedTypes = parseEmbedParam(request.query.embed);
    if (embedTypes.length === 0) {
      return serveJsonFile(request, reply, filePath, cache.maxAge, extraHeaders);
    }

    const maxDepth = parseDepth(request.query.depth);
    return serveHydratedJson(request, reply, filePath, paths.export, lang, embedTypes, maxDepth, cache.maxAge, extraHeaders);
  });
}
