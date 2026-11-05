-- Jalankan HANYA pada PostgreSQL ob2_dev untuk pengembangan.
-- Tidak ada perubahan skema pada database produksi.
CREATE TABLE IF NOT EXISTS mobile_delivery_devices (
    id BIGSERIAL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    activation_code VARCHAR(10) NOT NULL UNIQUE,
    device_label VARCHAR(120) NOT NULL DEFAULT '',
    platform VARCHAR(20) NOT NULL DEFAULT 'unknown',
    user_agent TEXT NOT NULL DEFAULT '',
    status VARCHAR(12) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','approved','revoked')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    approved_at TIMESTAMPTZ NULL,
    last_seen_at TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS idx_mobile_delivery_devices_status ON mobile_delivery_devices(status, created_at DESC);
