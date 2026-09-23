# AWS automatic deployment

Production site: https://cite-events.duckdns.org/ITEventManagement/

The production server checks the GitHub `main` branch about once per minute
using a read-only SSH deploy key. A new commit is unpacked into a separate
release, PHP syntax is checked,
the live database is backed up, new additive SQL migrations are applied, and the
web symlink is switched. The new release stays live only if its homepage, session
API, protected API, and SQL-file access checks pass. Uploaded media and the
production database connection file live outside the Git releases.
The server needs a read-only deploy key registered in the repository's GitHub
Settings → Deploy keys. The private key stays on the server; no GitHub token or
webhook is needed. The deployment service runs as root, so configure its SSH
identity and GitHub host key under `/root/.ssh/`. The deploy script expects the
private key at `/root/.ssh/cite-events-deploy`. Register only the matching
`.pub` file in GitHub; never upload or share the private key.

For an existing server, generate that key without overwriting an existing one,
then copy its public half into GitHub Settings → Deploy keys → Add deploy key.
Leave **Allow write access** unchecked. Add GitHub's published Ed25519 host key
to `/root/.ssh/known_hosts` before testing the SSH connection; see
[GitHub's SSH fingerprints](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints).

If an older installation still has the HTTPS repository URL, change the
`repository=` line in `/usr/local/sbin/cite-events-deploy` to
`git@github.com:HadaC05/IT-Event-Management.git` and add the
`GIT_SSH_COMMAND` export from this script after setting up the key.
The installed script is a separate copy: changing this repository file alone
cannot repair a deployment service that can no longer fetch GitHub.

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
