import Fastify from 'fastify';
import cors from '@fastify/cors';
import entityRoutes from '../../routes/entity.js';
import viewRoutes from '../../routes/view.js';
import aliasRoutes from '../../routes/alias.js';

/**
 * Builds a Fastify app instance for testing.
 * @param {object} opts
 * @param {string} opts.exportDir
 * @param {string} opts.viewsDir
 * @param {string} opts.aliasDir
 * @param {number} [opts.maxAge=3600]
 */
export async function buildTestApp({ exportDir, viewsDir, aliasDir, maxAge = 3600 }) {
  const fastify = Fastify({ logger: false });

  await fastify.register(cors, { origin: '*' });
  await fastify.register(entityRoutes, {
    paths: { export: exportDir, views: viewsDir, alias: aliasDir },
    cache: { maxAge },
  });
  await fastify.register(viewRoutes, {
    paths: { export: exportDir, views: viewsDir, alias: aliasDir },
    cache: { maxAge },
  });
  await fastify.register(aliasRoutes, {
    paths: { export: exportDir, views: viewsDir, alias: aliasDir },
    cache: { maxAge },
  });

  fastify.get('/health', async () => ({ status: 'ok' }));

  await fastify.ready();
  return fastify;
}
