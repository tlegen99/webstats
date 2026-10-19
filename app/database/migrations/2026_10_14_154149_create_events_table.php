<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE events (
                seq           bigserial,
                event_id      uuid        NOT NULL,
                site_id       integer     NOT NULL,
                ts            timestamptz NOT NULL,
                name          text        NOT NULL,
                visitor_id    bigint      NOT NULL,
                session_id    uuid        NOT NULL,
                hostname      text        NOT NULL,
                pathname      text        NOT NULL,
                referrer_host text        NOT NULL DEFAULT '',
                channel       text        NOT NULL DEFAULT '',
                utm_source    text        NOT NULL DEFAULT '',
                utm_medium    text        NOT NULL DEFAULT '',
                utm_campaign  text        NOT NULL DEFAULT '',
                country       char(2),
                region        text,
                city          text,
                browser       text,
                os            text,
                device        text,
                screen_w      smallint,
                engagement_ms integer     NOT NULL DEFAULT 0,
                scroll_depth  smallint    NOT NULL DEFAULT 0,
                props         jsonb       NOT NULL DEFAULT '{}',
                PRIMARY KEY (event_id, ts)
            ) PARTITION BY RANGE (ts)
        SQL);

        DB::statement('CREATE INDEX events_site_id_ts_index ON events (site_id, ts)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS events');
    }
};
