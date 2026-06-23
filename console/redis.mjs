import { createClient } from 'redis';

const REDIS_URL = process.env.REDIS_URL ?? 'redis://redis:6379';
const KEY_PREFIX = 'pd:console:session:';

/** @type {import('redis').RedisClientType | null} */
let client = null;

export async function connectRedis() {
  if (client?.isOpen) {
    return client;
  }

  client = createClient({ url: REDIS_URL });
  client.on('error', (error) => console.error('Redis error:', error));
  await client.connect();
  return client;
}

/**
 * @returns {Promise<Record<string, unknown> | null>}
 */
export async function consumeConsoleSession(token) {
  if (!/^[a-f0-9]{64}$/.test(token)) {
    return null;
  }

  const redis = await connectRedis();
  const json = await redis.getDel(`${KEY_PREFIX}${token}`);
  if (!json) {
    return null;
  }

  return JSON.parse(json);
}
