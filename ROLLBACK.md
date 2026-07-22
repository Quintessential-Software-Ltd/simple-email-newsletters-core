# Rollback notes

## 2.0.0 — core/Pro split

The 2.0.0 restructure (uncapped free core + `addons/quintessential-newsletters-pro/` add-on) is a
single revertible commit on `main` — `git revert` it to restore the 1.8.4
capped/licensed single-plugin world. Data notes:

- All data is preserved either way: campaign `sender_id`, the `semnews_display`
  overlay settings, `archive_enabled` and the stored license key are simply
  ignored by whichever build lacks the feature, never deleted.
- The add-on stores its license key in `semnewsp_license_key` but falls back to
  the pre-2.0 location inside `semnews_settings`, so licensed sites keep working
  in both directions.

# Rolling back the 1.8.0 improvement pass

The 1.8.0 release was built as a series of **independently revertible commits**
on `main`, on top of the anchor commit below. Nothing in it is destructive:
every database change is **additive** (new columns and indexes via `dbDelta`),
so the 1.7.3 code runs unchanged against a database that 1.8.0 has touched.

## Anchor (the state before the pass)

- **v1.7.3 anchor commit:** `e9be3095af565e43395333476e7e37c4f5785204`
  (also tagged `v1.7.3` locally).

## Option 1 — reinstall the old zip (site-level rollback)

Rebuild the exact 1.7.3 plugin zip from the anchor and upload it over the
current install (Plugins → deactivate → delete → upload). **No data is lost** —
deleting the plugin only removes files; tables/settings stay unless the
"delete data on uninstall" setting was deliberately enabled.

```bash
git archive --format=zip --prefix=quintessential-newsletters/ \
    -o quintessential-newsletters-1.7.3.zip e9be3095af565e43395333476e7e37c4f5785204
```

After downgrading mid-send only: reset any in-flight claimed rows once
(1.7.3 doesn't know the `processing` status):

```sql
UPDATE wp_semnews_queue SET status='pending', claim_id=NULL, claimed_at=NULL
WHERE status='processing';
```

## Option 2 — revert a single change (repo-level rollback)

Each concern is one commit; revert just the one you want gone:

```bash
git log --oneline e9be309..HEAD   # list the pass's commits
git revert <sha>                  # undo exactly one cluster
```

The clusters, in order:

| Commit | Contents |
|---|---|
| Reliability | atomic lock, row claiming/reaper, retry backoff, batch time budget, send/edit guards, sent_at fix, queue schema (DB 1.4.0) |
| Scale | streamed CSV exports, batched pending purge, dead-code removal |
| Scheduling | schedule/pause/resume UI+cron, digest catch-up, cron-health warnings, campaigns pagination |
| Housekeeping | tracking-toggle removal, multisite activate/uninstall, block.json, uninstall completeness |
| Admin features | subscriber detail view, Art. 30 export, duplicate/preview, CSV upload, editable system emails, digest lint, preference-centre name edit |
| Archive/updates/tests | opt-in public archive, self-hosted update checker + server endpoint, test suite + CI, 1.8.0 bump |

## Database notes

- New columns/indexes (`queue.claim_id/claimed_at/next_attempt_at`,
  `subscribers` date indexes, `campaigns` usage of `scheduled_at`) are ignored
  by older code — safe to leave in place after any rollback.
- Reverting the schema commit does **not** drop columns (dbDelta never drops);
  that is intentional and harmless.
