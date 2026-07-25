import { describe, it, expect } from 'vitest';
import { bucketFromId, sanitizeAliasPath, entityFilePath, aliasDataPath, isValidLangcode } from '../lib/path-utils.js';

describe('bucketFromId', () => {
  it('returns floor(id / 1000)', () => {
    expect(bucketFromId(1)).toBe(0);
    expect(bucketFromId(999)).toBe(0);
    expect(bucketFromId(1000)).toBe(1);
    expect(bucketFromId(4523)).toBe(4);
    expect(bucketFromId(9999)).toBe(9);
  });

  it('throws on invalid id', () => {
    expect(() => bucketFromId('abc')).toThrow();
    expect(() => bucketFromId(-1)).toThrow();
  });
});

describe('sanitizeAliasPath', () => {
  it('removes path traversal', () => {
    expect(sanitizeAliasPath('../../etc/passwd')).toBe('/etc/passwd');
    expect(sanitizeAliasPath('././etc/passwd')).toBe('/etc/passwd');
    expect(sanitizeAliasPath('/about/../contact/./form')).toBe('/about/contact/form');
    expect(sanitizeAliasPath('..\\..\\windows')).toBe('/windows');
  });

  it('removes unsafe characters', () => {
    expect(sanitizeAliasPath('/test<script>alert/foo')).toBe('/testscriptalert/foo');
    expect(sanitizeAliasPath('/my-page/about_us')).toBe('/my-page/about_us');
  });

  it('collapses empty segments', () => {
    expect(sanitizeAliasPath('/about//us/')).toBe('/about/us');
    expect(sanitizeAliasPath('/')).toBe('/');
    expect(sanitizeAliasPath('')).toBe('/');
  });

  it('always returns leading slash', () => {
    expect(sanitizeAliasPath('about-us')).toBe('/about-us');
    expect(sanitizeAliasPath('/about-us')).toBe('/about-us');
  });

  it('preserves multi-segment paths', () => {
    expect(sanitizeAliasPath('/hola/mundo')).toBe('/hola/mundo');
    expect(sanitizeAliasPath('/products/shoes/running')).toBe('/products/shoes/running');
  });
});

describe('entityFilePath', () => {
  it('builds correct path', () => {
    const result = entityFilePath('/var/export', 'node', 4523, 'es');
    expect(result.file).toBe('/var/export/node/4/4523/es.json');
    expect(result.bucket).toBe(4);
  });
});

describe('aliasDataPath', () => {
  it('builds correct path', () => {
    const result = aliasDataPath('/var/aliases', 'en', 0, '/about-us');
    expect(result).toBe('/var/aliases/en/0/about-us/data.json');
  });

  it('builds correct path for multi-segment alias', () => {
    const result = aliasDataPath('/var/aliases', 'en', 0, '/hola/mundo');
    expect(result).toBe('/var/aliases/en/0/hola/mundo/data.json');
  });
});

describe('isValidLangcode', () => {
  it('accepts ISO 639-1 codes', () => {
    expect(isValidLangcode('en')).toBe(true);
    expect(isValidLangcode('es')).toBe(true);
    expect(isValidLangcode('fr')).toBe(true);
    expect(isValidLangcode('de')).toBe(true);
  });

  it('accepts ISO 639-2/T codes (3 letters)', () => {
    expect(isValidLangcode('haw')).toBe(true);
    expect(isValidLangcode('yue')).toBe(true);
  });

  it('accepts locale variants', () => {
    expect(isValidLangcode('en-GB')).toBe(true);
    expect(isValidLangcode('pt-br')).toBe(false); // lowercase region
    expect(isValidLangcode('zh-CN')).toBe(true);
  });

  it('rejects empty or non-string values', () => {
    expect(isValidLangcode('')).toBe(false);
    expect(isValidLangcode(null)).toBe(false);
    expect(isValidLangcode(undefined)).toBe(false);
    expect(isValidLangcode(123)).toBe(false);
  });

  it('rejects uppercase base code', () => {
    expect(isValidLangcode('EN')).toBe(false);
  });

  it('rejects path traversal attempts', () => {
    expect(isValidLangcode('..')).toBe(false);
    expect(isValidLangcode('../etc')).toBe(false);
    expect(isValidLangcode('en/../etc')).toBe(false);
    expect(isValidLangcode('en/..')).toBe(false);
  });

  it('rejects too short or too long', () => {
    expect(isValidLangcode('e')).toBe(false);
    expect(isValidLangcode('en-US-extra')).toBe(false);
  });

  it('rejects special characters', () => {
    expect(isValidLangcode('en_US')).toBe(false); // underscore
    expect(isValidLangcode('en US')).toBe(false); // space
    expect(isValidLangcode('en.us')).toBe(false); // dot
  });
});
