import 'dotenv/config';
import { resolve } from 'node:path';

const required = (name, fallback) => {
  const value = process.env[name] ?? fallback;
  if (value === undefined || value === '') {
    throw new Error(`Missing required env var: ${name}`);
  }
  return value;
};

export const config = {
  port: parseInt(required('PORT', '3000'), 10),
  host: required('HOST', '0.0.0.0'),
  paths: {
    export: resolve(required('EXPORT_DIR', './public/serialized')),
    views: resolve(required('VIEWS_DIR', './public/views')),
    alias: resolve(required('ALIAS_DIR', './public/alias-buckets')),
  },
  cache: {
    maxAge: parseInt(required('CACHE_MAX_AGE', '3600'), 10),
  },
  cors: {
    origin: required('CORS_ORIGIN', '*'),
  },
  log: {
    level: required('LOG_LEVEL', 'info'),
  },
};
