const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

// Safe environment loading from .env if present
const envPath = path.join(__dirname, '..', '.env');
if (fs.existsSync(envPath)) {
  const envContent = fs.readFileSync(envPath, 'utf8');
  for (const line of envContent.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIdx = trimmed.indexOf('=');
    if (eqIdx !== -1) {
      const key = trimmed.slice(0, eqIdx).trim();
      const val = trimmed.slice(eqIdx + 1).trim().replace(/^['"]|['"]$/g, '');
      if (!process.env[key]) process.env[key] = val;
    }
  }
}

async function run() {
  const host = process.env.DB_HOST || process.env.MYSQLHOST || 'localhost';
  const port = parseInt(process.env.DB_PORT || process.env.MYSQLPORT || '3306', 10);
  const user = process.env.DB_USER || process.env.MYSQLUSER || 'root';
  const password = process.env.DB_PASS !== undefined ? process.env.DB_PASS : (process.env.MYSQLPASSWORD !== undefined ? process.env.MYSQLPASSWORD : '');
  const database = process.env.DB_NAME || process.env.MYSQLDATABASE || 'fincart';
  const ssl = (host !== 'localhost' && host !== '127.0.0.1') ? { minVersion: 'TLSv1.2', rejectUnauthorized: true } : undefined;

  const conn = await mysql.createConnection({
    host,
    port,
    user,
    password,
    database,
    ssl,
    multipleStatements: true
  });

  const [appliedRows] = await conn.execute('SELECT migration_name FROM migrations');
  const appliedSet = new Set(appliedRows.map(r => r.migration_name));

  const migrationsDir = path.join(__dirname, '..', 'database', 'migrations');
  const files = fs.readdirSync(migrationsDir).filter(f => f.endsWith('.sql')).sort();

  for (const file of files) {
    if (appliedSet.has(file)) {
      console.log(`[ALREADY APPLIED] ${file}`);
      continue;
    }

    console.log(`[MIGRATING] ${file}...`);
    const sql = fs.readFileSync(path.join(migrationsDir, file), 'utf-8');
    await conn.query(sql);
    await conn.execute('INSERT INTO migrations (migration_name) VALUES (?)', [file]);
    console.log(`[SUCCESS] ${file}`);
  }

  console.log('All migrations checked and synced with TiDB!');
  await conn.end();
}

run().catch(console.error);

