// @ts-check
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { serverEnv } from '../../playwright.config.js';

/**
 * Rebuild the dedicated browser database from scratch so every run starts from
 * the same seeded state (platform seed + deterministic browser accounts).
 */
export default async function globalSetup() {
    const dbPath = serverEnv.DB_DATABASE;
    try {
        if (fs.existsSync(dbPath)) {
            fs.unlinkSync(dbPath);
        }
    } catch (error) {
        // A previous server may still hold the file (EBUSY on Windows); migrate:fresh rebuilds it in place.
        if (error.code !== 'EBUSY') throw error;
    }
    if (!fs.existsSync(dbPath)) {
        fs.writeFileSync(dbPath, '');
    }

    const artisan = (args) =>
        execFileSync('php', ['artisan', ...args], { env: serverEnv, stdio: 'inherit', timeout: 300_000 });

    artisan(['config:clear']);
    artisan(['migrate:fresh', '--seed', '--force']);
    artisan(['db:seed', '--class=BrowserTestSeeder', '--force']);
}
