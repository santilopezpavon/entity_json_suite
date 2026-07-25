/**
 * Serves an exported view JSON file by direct file lookup.
 *
 * Route: GET /json/views/:view/:display
 *
 * Storage: <viewsDir>/<view_id>/<display_id>.json
 */

import { join } from 'node:path';
import { serveJsonFile } from '../lib/response.js';

export default async function viewRoutes(fastify, opts) {
  const { paths, cache } = opts;

  fastify.get('/json/views/:view/:display', async (request, reply) => {
    const { view, display } = request.params;
    const filePath = join(paths.views, view, `${display}.json`);
    return serveJsonFile(request, reply, filePath, cache.maxAge);
  });
}
