const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

async function run() {
  const conn = await mysql.createConnection({
    host: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com',
    port: 4000,
    user: '3vgQ8HSxHamFERy.root',
    password: 'iPhUpnLxUlXO1HXs',
    database: 'fincart',
    ssl: { minVersion: 'TLSv1.2', rejectUnauthorized: true },
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

