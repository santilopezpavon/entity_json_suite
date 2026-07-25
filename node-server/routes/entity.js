/**
 * Serves a single entity translation JSON file by direct file lookup.
 *
 * Route: GET /json/:type/:id/:lang
 *
 * The path mirrors Drupal's FileStorageManager::getEntityFilePath():
 *   <exportDir>/<type>/<bucket>/<id>/<lang>.json
 *   where bucket = floor(id / 1000)
 */

import { entityFilePath } from '../lib/path-utils.js';
import { serveJsonFile, serveHydratedJson } from '../lib/response.js';
import { parseEmbedParam } from '../lib/hydrate.js';

const DEFAULT_MAX_DEPTH = 2;

function parseDepth(value) {
  const n = parseInt(value, 10);
  return Number.isFinite(n) ? Math.min(Math.max(n, 0), 10) : DEFAULT_MAX_DEPTH;
}

export default async function entityRoutes(fastify, opts) {
  const { paths, cache } = opts;

  fastify.get('/json/:type/:id/:lang', async (request, reply) => {
    const { type, id, lang } = request.params;

    let filePath;
    try {
      ({ file: filePath } = entityFilePath(paths.export, type, id, lang));
    }
    catch (err) {
      return reply.code(400).send({ error: err.message });
    }

    const embedTypes = parseEmbedParam(request.query.embed);
    if (embedTypes.length === 0) {
      return serveJsonFile(request, reply, filePath, cache.maxAge);
    }

    const maxDepth = parseDepth(request.query.depth);
    return serveHydratedJson(request, reply, filePath, paths.export, lang, embedTypes, maxDepth, cache.maxAge);
  });
}
