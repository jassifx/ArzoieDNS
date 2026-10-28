<?php
declare(strict_types=1);
namespace Arzoie\Models;

use Arzoie\Core\Database;
use Arzoie\Core\Env;

final class Schema
{
    public static function migrate(): void
    {
        $id = Env::get('DB_DRIVER', 'pgsql') === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT AUTO_INCREMENT PRIMARY KEY';
        $tables = [
            "users (id $id, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TIMESTAMP NULL, created_at TIMESTAMP NOT NULL)",
            "zones (id $id, name VARCHAR(253) NOT NULL UNIQUE, primary_ns VARCHAR(253) NOT NULL, soa_email VARCHAR(253) NOT NULL, serial BIGINT NOT NULL DEFAULT 1, desired_revision BIGINT NULL, deleted_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL)",
            "records (id $id, zone_id BIGINT NOT NULL REFERENCES zones(id), owner VARCHAR(253) NOT NULL, type VARCHAR(10) NOT NULL, ttl INTEGER NOT NULL, value TEXT NOT NULL)",
            "zone_revisions (id $id, zone_id BIGINT NOT NULL REFERENCES zones(id), serial BIGINT NOT NULL, operation VARCHAR(16) NOT NULL, source TEXT NOT NULL, artifact_path TEXT NULL, checksum VARCHAR(64) NULL, ds_records TEXT NULL, signed_until TIMESTAMP NULL, created_at TIMESTAMP NOT NULL, UNIQUE(zone_id, serial))",
            "servers (id $id, name VARCHAR(100) NOT NULL UNIQUE, address VARCHAR(45) NOT NULL, ssh_port INTEGER NOT NULL DEFAULT 22, host_fingerprint VARCHAR(100) NOT NULL, location VARCHAR(100) NOT NULL, state VARCHAR(24) NOT NULL DEFAULT 'pending', os_name VARCHAR(100) NULL, health TEXT NULL, health_at TIMESTAMP NULL, bootstrap_secret TEXT NULL, secret_expires_at TIMESTAMP NULL, last_error TEXT NULL, created_at TIMESTAMP NOT NULL)",
            "deployments (id $id, server_id BIGINT NOT NULL REFERENCES servers(id), zone_id BIGINT NOT NULL REFERENCES zones(id), applied_revision BIGINT NULL, verified_at TIMESTAMP NULL, last_error TEXT NULL, UNIQUE(server_id, zone_id))",
            "jobs (id $id, kind VARCHAR(24) NOT NULL, server_id BIGINT NULL REFERENCES servers(id), zone_id BIGINT NULL REFERENCES zones(id), state VARCHAR(16) NOT NULL DEFAULT 'queued', attempts INTEGER NOT NULL DEFAULT 0, available_at TIMESTAMP NOT NULL, locked_at TIMESTAMP NULL, last_error TEXT NULL, created_at TIMESTAMP NOT NULL)",
            "audit_events (id $id, actor VARCHAR(254) NOT NULL, action VARCHAR(64) NOT NULL, subject VARCHAR(254) NOT NULL, details TEXT NOT NULL, created_at TIMESTAMP NOT NULL)",
        ];
        $engine = Env::get('DB_DRIVER', 'pgsql') === 'mysql' ? ' ENGINE=InnoDB' : '';
        foreach ($tables as $table) { Database::connect()->exec('CREATE TABLE IF NOT EXISTS ' . $table . $engine); }
    }
}
