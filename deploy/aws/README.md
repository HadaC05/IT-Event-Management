# AWS automatic deployment

The production server checks the public GitHub `main` branch about once per
minute. A new commit is unpacked into a separate release, PHP syntax is checked,
the live database is backed up, new additive SQL migrations are applied, and the
web symlink is switched. The new release stays live only if its homepage, session
API, protected API, and SQL-file access checks pass. Uploaded media and the
production database connection file live outside the Git releases.

The one-time server installation places `cite-events-deploy.sh` at
`/usr/local/sbin/cite-events-deploy` and the service/timer under
`/etc/systemd/system/`. Server credentials remain under `/etc/cite-events/` and
are never committed. The virtual-host templates in this directory contain no
secrets.

Pushes to branches other than `main` do not deploy. A successful GitHub push is
visible on the server within about a minute; it is not instantaneous. A failed
preflight or migration leaves the current code in place. A failed post-switch
health check restores the previous code release. MySQL schema changes are not
transactionally reversible, so migrations must be additive and compatible with
both the old and new code.

To add a database change, create a new immutable SQL file in
`database/deploy_migrations/` named like
`20260923_0003_add_example_index.sql`. New files run once in name order. Make
each file safe to retry, because MySQL DDL may commit before a later statement
fails. Never put full database dumps, roster imports, destructive SQL, or test
data in this folder. SQL files elsewhere in `database/` are never run by the
deployer. Live attendance and student records are not replaced by a code push.

Useful server commands:

```bash
systemctl status cite-events-deploy.timer
systemctl status cite-events-deploy.service
journalctl -u cite-events-deploy.service -n 100 --no-pager
cat /srv/cite-events/current_commit
```

An operator can disable automatic releases with
`sudo systemctl disable --now cite-events-deploy.timer`. The existing website
keeps running. Restart with `sudo systemctl enable --now
cite-events-deploy.timer` and run a pending release immediately with
`sudo systemctl start cite-events-deploy.service`.
